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
 * External function: record that an author inserted a FastPix shortcode.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_fastpix\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Records the "shortcode inserted" signal for the picker.
 *
 * The insertion happens entirely in the editor (client-side), so the JS calls
 * this after it writes {fastpix:pb_<id>} at the cursor. It stores nothing of its
 * own — it only triggers a Moodle event for the site log. It is gated by the
 * same mod/fastpix:uploadmedia capability (resolved at the course context) as
 * the picker itself, and require_sesskey() guards the state-changing call.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_shortcode_inserted extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid'  => new external_value(PARAM_INT, 'The context the editor is used in.'),
            'playbackid' => new external_value(PARAM_ALPHANUMEXT, 'The bare playback id that was inserted (no pb_ prefix).'),
        ]);
    }

    /**
     * Return-value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'logged' => new external_value(PARAM_BOOL, 'Whether the event was recorded.'),
        ]);
    }

    /**
     * Record the shortcode insertion as a Moodle event.
     *
     * @param int $contextid The context the editor is used in.
     * @param string $playbackid The bare playback id that was inserted.
     * @return array Whether the event was recorded.
     */
    public static function execute(int $contextid, string $playbackid): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'playbackid' => $playbackid]
        );

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        // Explicit auth chain (validate_context already logs in, but the contract
        // is kept visible): validate_context -> require_login -> require_sesskey
        // (write) -> require_capability.
        require_login(null, false);
        require_sesskey();

        // Insertion only makes sense inside a course (the picker is course-scoped
        // and the capability lives there); outside one there is nothing to log.
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext) {
            return ['logged' => false];
        }
        require_capability('mod/fastpix:uploadmedia', $coursecontext);

        \tiny_fastpix\event\shortcode_inserted::create_from_context($context, $params['playbackid'])->trigger();

        return ['logged' => true];
    }
}
