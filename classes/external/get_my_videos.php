<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External function: list the author's embeddable FastPix videos.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_fastpix\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns the current user's ready, public FastPix videos for the picker.
 *
 * The list is sourced from local_fastpix (asset_service::list_for_owner). Only
 * PUBLIC videos are offered: filter_fastpix plays public videos in casual
 * embeds, but renders the "unavailable" placeholder for private and DRM ones,
 * so offering anything non-public would only let an author insert a shortcode
 * that never plays.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_my_videos extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context the editor is used in.'),
            'query'     => new external_value(
                PARAM_TEXT,
                'Optional search term. Empty returns the most recent videos; otherwise the '
                    . 'whole owned library is searched by activity name and asset title.',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Return-value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'videos' => new external_multiple_structure(
                new external_single_structure([
                    'playbackid' => new external_value(PARAM_ALPHANUMEXT, 'The bare playback id (no pb_ prefix).'),
                    'title'      => new external_value(PARAM_TEXT, 'The video title.'),
                ])
            ),
        ]);
    }

    /**
     * List the current user's ready, embeddable videos.
     *
     * @param int $contextid The context the editor is used in.
     * @param string $query Optional search term; empty returns the recent list.
     * @return array The list of videos.
     */
    public static function execute(int $contextid, string $query = ''): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'query' => $query]
        );

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('mod/fastpix:uploadmedia', $context);

        $search = trim($params['query']);
        if ($search === '') {
            // No search: the most-recent owned, ready assets.
            $assets = \local_fastpix\service\asset_service::list_for_owner((int)$USER->id, 'ready');
        } else {
            // Search the whole owned library by activity name and asset title.
            $assets = self::search_assets((int)$USER->id, $search);
        }

        // The local_fastpix asset title is frequently the "Asset <uuid>"
        // fallback, which tells an author nothing in the picker. The name they
        // actually typed lives on the mod_fastpix activity that uploaded the
        // asset, so resolve a display name from there. Read-only lookup — this
        // plugin writes nothing to mod_fastpix or local_fastpix.
        $names = self::resolve_display_names($assets);

        $videos = [];
        foreach ($assets as $asset) {
            // Embeddable = has a playback id AND is public. Private and DRM
            // videos are excluded: filter_fastpix only plays public videos in
            // casual embeds (a private/DRM shortcode renders "unavailable"), so
            // offering them here would only let an author insert a dead embed.
            if (empty($asset->playback_id) || ($asset->access_policy ?? '') !== 'public') {
                continue;
            }
            $videos[] = [
                'playbackid' => (string)$asset->playback_id,
                'title'      => self::display_title($asset, $names[(int)$asset->id] ?? ''),
            ];
        }

        return ['videos' => $videos];
    }

    /**
     * Pick the label shown in the picker for one asset.
     *
     * Preference order: the resolved activity name; then the asset's own title
     * if it is a real one; then a localised "Untitled video" placeholder. The
     * local_fastpix projector writes the literal "Asset <fastpix_id>" when an
     * upload arrives without a title, so we treat exactly that value as "no
     * title" rather than show a raw UUID to authors.
     *
     * @param \stdClass $asset The asset record (needs ->title and ->fastpix_id).
     * @param string $resolvedname The activity name resolved upstream, or ''.
     * @return string The label to display.
     */
    private static function display_title(\stdClass $asset, string $resolvedname): string {
        if ($resolvedname !== '') {
            return $resolvedname;
        }

        $title = (string)$asset->title;
        $placeholder = 'Asset ' . (string)$asset->fastpix_id;
        if ($title !== '' && $title !== $placeholder) {
            return $title;
        }

        return get_string('untitledvideo', 'tiny_fastpix');
    }

    /**
     * Resolve a human display name for each asset, asset id => name.
     *
     * The author-facing name lives on mod_fastpix.name; the local_fastpix asset
     * title is often the "Asset <uuid>" fallback. We read the name here so the
     * picker shows something a human recognises. Two read-only lookups, in
     * order of reliability — no writes to mod_fastpix or local_fastpix:
     *
     *   1. Direct link: the activity points at the asset
     *      (mod_fastpix.fastpix_asset_id = asset.id).
     *   2. Upload-session fallback: used when the direct link was never set
     *      (fastpix_asset_id NULL). Recover the name through the upload session
     *      that produced the asset
     *      (asset.fastpix_id -> upload_session.id -> activity.upload_session_id).
     *
     * When several activities resolve to one asset, the most recent name wins.
     * Assets with no activity on either path keep the "Asset <uuid>" fallback.
     *
     * @param array $assets Asset records from asset_service::list_for_owner.
     * @return array<int, string> Asset id => display name (only non-empty names).
     */
    private static function resolve_display_names(array $assets): array {
        global $DB;

        if (empty($assets)) {
            return [];
        }

        // Seed every asset id as unresolved, and index fastpix_id -> asset id
        // for the second-pass session lookup.
        $names = [];
        $assetidbyfpid = [];
        foreach ($assets as $asset) {
            $names[(int)$asset->id] = '';
            if (!empty($asset->fastpix_id)) {
                $assetidbyfpid[(string)$asset->fastpix_id] = (int)$asset->id;
            }
        }

        // Pass 1 — direct activity link. Ascending id order means a later (more
        // recent) activity overwrites an earlier one for a reused asset.
        [$insql, $params] = $DB->get_in_or_equal(array_keys($names), SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            'fastpix',
            "fastpix_asset_id {$insql}",
            $params,
            'id ASC',
            'id, fastpix_asset_id, name'
        );
        foreach ($rows as $row) {
            $name = trim((string)$row->name);
            if ($name !== '') {
                $names[(int)$row->fastpix_asset_id] = $name;
            }
        }

        // Pass 2 — upload-session fallback for assets the direct link missed.
        $unresolved = array_filter($assetidbyfpid, static fn($aid) => $names[$aid] === '');
        if (!empty($unresolved)) {
            [$insql2, $params2] = $DB->get_in_or_equal(array_keys($unresolved), SQL_PARAMS_NAMED);
            $sessrows = $DB->get_records_sql(
                "SELECT m.id, us.fastpix_id, m.name
                   FROM {local_fastpix_upload_session} us
                   JOIN {fastpix} m ON m.upload_session_id = us.id
                  WHERE us.fastpix_id {$insql2}
               ORDER BY m.id ASC",
                $params2
            );
            foreach ($sessrows as $row) {
                $name = trim((string)$row->name);
                $aid = $unresolved[(string)$row->fastpix_id] ?? null;
                if ($name !== '' && $aid !== null) {
                    $names[$aid] = $name;
                }
            }
        }

        return array_filter($names, static fn($name) => $name !== '');
    }

    /**
     * Search the user's whole library for ready assets matching a term.
     *
     * Real search (not just the loaded recent list). Three owner-safe sources
     * are merged, deduplicated by asset id and capped, most-recent first:
     *   1. Asset title — via local_fastpix's own search
     *      (asset_service::list_for_owner_paginated), already owner-scoped.
     *   2. Activity name, direct link — mod_fastpix.name LIKE term, mapped to
     *      the asset through mod_fastpix.fastpix_asset_id.
     *   3. Activity name, upload-session fallback — mod_fastpix.name LIKE term,
     *      mapped through mod_fastpix.upload_session_id -> upload_session.fastpix_id.
     *
     * The author-typed name lives on mod_fastpix and may have been typed by any
     * teacher, but the matched assets are loaded with owner_userid = $userid in
     * load_owned_ready_assets(), so search can never surface another user's
     * videos. The embeddable filter (playback id, non-DRM) is applied by the
     * caller's loop. Read-only — no writes to mod_fastpix or local_fastpix.
     *
     * @param int $userid The owner whose library is searched.
     * @param string $query The (already trimmed, non-empty) search term.
     * @param int $limit Maximum assets to return.
     * @return array Asset records, most-recent first.
     */
    private static function search_assets(int $userid, string $query, int $limit = 50): array {
        global $DB;

        // 1. Title matches, straight from local_fastpix (owner-scoped, ready).
        $assets = [];
        $bytitle = \local_fastpix\service\asset_service::list_for_owner_paginated(
            $userid,
            'ready',
            0,
            $limit,
            $query
        );
        foreach ($bytitle as $asset) {
            $assets[(int)$asset->id] = $asset;
        }

        // 2 + 3. Activity-name matches -> candidate asset identifiers. Owner
        // scoping is enforced when these are loaded, not here.
        $like = '%' . $DB->sql_like_escape($query) . '%';
        $namelike = $DB->sql_like('m.name', ':q', false);

        $directids = $DB->get_fieldset_sql(
            "SELECT DISTINCT m.fastpix_asset_id
               FROM {fastpix} m
              WHERE {$namelike} AND m.fastpix_asset_id IS NOT NULL AND m.fastpix_asset_id <> 0",
            ['q' => $like]
        );

        $sessfpids = $DB->get_fieldset_sql(
            "SELECT DISTINCT us.fastpix_id
               FROM {fastpix} m
               JOIN {local_fastpix_upload_session} us ON us.id = m.upload_session_id
              WHERE {$namelike} AND us.fastpix_id IS NOT NULL",
            ['q' => $like]
        );

        foreach (self::load_owned_ready_assets($userid, $directids, $sessfpids) as $asset) {
            $assets[(int)$asset->id] = $asset;
        }

        // Most-recent first, then cap.
        $assets = array_values($assets);
        usort($assets, static fn($a, $b) => ($b->timecreated <=> $a->timecreated));

        return array_slice($assets, 0, $limit);
    }

    /**
     * Load owned, ready, live asset records by id and by fastpix_id.
     *
     * This is the owner-scope gate for name search: candidate identifiers come
     * from mod_fastpix (any author), but only assets owned by $userid are
     * returned. Soft-deleted assets are excluded.
     *
     * @param int $userid The owner.
     * @param array $ids Candidate local_fastpix_asset ids (direct link).
     * @param array $fpids Candidate fastpix_id values (upload-session fallback).
     * @return array<int, \stdClass> Asset id => asset record.
     */
    private static function load_owned_ready_assets(int $userid, array $ids, array $fpids): array {
        global $DB;

        $out = [];

        if (!empty($ids)) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'aid');
            $params['userid'] = $userid;
            $params['st'] = 'ready';
            $rows = $DB->get_records_select(
                'local_fastpix_asset',
                "id {$insql} AND owner_userid = :userid AND status = :st "
                    . "AND (deleted_at IS NULL OR deleted_at = 0)",
                $params
            );
            foreach ($rows as $row) {
                $out[(int)$row->id] = $row;
            }
        }

        if (!empty($fpids)) {
            [$insql, $params] = $DB->get_in_or_equal($fpids, SQL_PARAMS_NAMED, 'fp');
            $params['userid'] = $userid;
            $params['st'] = 'ready';
            $rows = $DB->get_records_select(
                'local_fastpix_asset',
                "fastpix_id {$insql} AND owner_userid = :userid AND status = :st "
                    . "AND (deleted_at IS NULL OR deleted_at = 0)",
                $params
            );
            foreach ($rows as $row) {
                $out[(int)$row->id] = $row;
            }
        }

        return $out;
    }
}
