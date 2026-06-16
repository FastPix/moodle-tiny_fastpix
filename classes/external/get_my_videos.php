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
 * External function: list the current course's videos the caller uploaded.
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
 * Returns the signed-in user's ready, embeddable FastPix videos that belong to
 * the course the editor is being used in.
 *
 * Scope (all must hold):
 *   - owner == the current user (asset_service::list_for_owner),
 *   - course == the editor's course (the asset is referenced by a mod_fastpix
 *     activity in that course),
 *   - state == ready,
 *   - embeddable == public with a playback id (filter_fastpix plays public
 *     videos in casual embeds but renders "unavailable" for private/DRM, so
 *     offering those would only insert a dead embed).
 *
 * Asset data is read exclusively through local_fastpix's service methods; the
 * only direct DB read is against mod_fastpix's own {fastpix} table, because the
 * course association lives there (local_fastpix assets have no course).
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_my_videos extends external_api {
    /** @var int Upper bound on the owner list scanned; the course scope keeps the real count small. */
    private const MAX_VIDEOS = 500;

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context the editor is used in.'),
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
     * List the caller's ready, embeddable videos that belong to this course.
     *
     * @param int $contextid The context the editor is used in.
     * @return array The list of videos.
     */
    public static function execute(int $contextid): array {
        global $USER, $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid]
        );

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        // validate_context() already enforces login, but the auth chain is kept
        // explicit to match the suite-wide external-function contract:
        // validate_parameters -> validate_context -> require_login -> capability.
        require_login(null, false);

        // Course scope: the picker only lists videos belonging to the course the
        // editor lives in. Outside a course (user/system context) nothing is in
        // scope, so return empty rather than leak anything cross-course.
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext) {
            return ['videos' => []];
        }
        require_capability('mod/fastpix:uploadmedia', $coursecontext);
        $courseid = (int)$coursecontext->instanceid;

        // The picker opens client-side, but this read is the server-side moment
        // its list loads, so it is where we record the "picker opened" signal.
        \tiny_fastpix\event\picker_opened::create_from_context($context, $courseid)->trigger();

        // Owner + ready scope from local_fastpix's own list method (no direct
        // local_fastpix table queries).
        $ownedbyid = [];
        foreach (\local_fastpix\service\asset_service::list_for_owner((int)$USER->id, 'ready', self::MAX_VIDEOS) as $asset) {
            $ownedbyid[(int)$asset->id] = $asset;
        }
        if (empty($ownedbyid)) {
            return ['videos' => []];
        }

        // Walk this course's FastPix activities (mod_fastpix's own {fastpix}
        // table — the course lives there, not in local_fastpix). id DESC so the
        // most recent activity name wins; $seen dedupes a reused asset.
        $activities = $DB->get_records(
            'fastpix',
            ['course' => $courseid],
            'id DESC',
            'id, name, fastpix_asset_id, upload_session_id'
        );

        $videos = [];
        $seen = [];
        foreach ($activities as $activity) {
            $assetid = self::resolve_asset_id($activity);
            if ($assetid === 0 || isset($seen[$assetid]) || !isset($ownedbyid[$assetid])) {
                continue;
            }
            $asset = $ownedbyid[$assetid];
            // Embeddable = public + has a playback id. Private/DRM render
            // "unavailable" in filter_fastpix, so offering them would only let an
            // author insert a dead embed.
            if (empty($asset->playback_id) || ($asset->access_policy ?? '') !== 'public') {
                continue;
            }
            $seen[$assetid] = true;
            $videos[] = [
                'playbackid' => (string)$asset->playback_id,
                'title'      => self::display_title($asset, trim((string)$activity->name)),
            ];
        }

        return ['videos' => $videos];
    }

    /**
     * Resolve a mod_fastpix activity row to its local_fastpix asset id.
     *
     * Prefers the direct link (fastpix_asset_id); falls back to the upload
     * session that produced the asset. The session lookup goes through
     * local_fastpix's sanctioned service method, never a direct table query.
     *
     * @param \stdClass $activity A {fastpix} row (needs ->fastpix_asset_id, ->upload_session_id).
     * @return int The local_fastpix_asset id, or 0 when the activity links to no asset.
     */
    private static function resolve_asset_id(\stdClass $activity): int {
        if (!empty($activity->fastpix_asset_id)) {
            return (int)$activity->fastpix_asset_id;
        }
        if (!empty($activity->upload_session_id)) {
            $asset = \local_fastpix\service\asset_service::get_by_upload_session_id((int)$activity->upload_session_id);
            if ($asset) {
                return (int)$asset->id;
            }
        }
        return 0;
    }

    /**
     * Pick the label shown in the picker for one asset.
     *
     * Preference order: the activity name; then the asset's own title if it is a
     * real one; then a localised "Untitled video" placeholder. The local_fastpix
     * projector writes the literal "Asset <fastpix_id>" when an upload arrives
     * without a title, so we treat exactly that value as "no title" rather than
     * show a raw UUID to authors.
     *
     * @param \stdClass $asset The asset record (needs ->title and ->fastpix_id).
     * @param string $activityname The activity name, or '' when unavailable.
     * @return string The label to display.
     */
    private static function display_title(\stdClass $asset, string $activityname): string {
        if ($activityname !== '') {
            return $activityname;
        }

        $title = (string)$asset->title;
        $placeholder = 'Asset ' . (string)$asset->fastpix_id;
        if ($title !== '' && $title !== $placeholder) {
            return $title;
        }

        return get_string('untitledvideo', 'tiny_fastpix');
    }
}
