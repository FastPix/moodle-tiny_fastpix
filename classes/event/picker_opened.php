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

namespace tiny_fastpix\event;

/**
 * Picker-opened event for the FastPix TinyMCE plugin.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fired when a capable author opens the picker in a course (the get_my_videos
 * web service is the server-side moment the picker loads its list). Conceptually
 * the suite's "tiny.picker.opened" signal.
 */
class picker_opened extends \core\event\base {
    /**
     * Build the event for an editor context, carrying the resolved course id.
     *
     * @param \context $context The editor context the picker opened in.
     * @param int $courseid The course the picker is scoped to.
     * @return self The created event.
     */
    public static function create_from_context(\context $context, int $courseid): self {
        return self::create([
            'context' => $context,
            'other'   => ['courseid' => $courseid],
        ]);
    }

    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string The event name.
     */
    public static function get_name() {
        return get_string('eventpickeropened', 'tiny_fastpix');
    }

    /**
     * Return a human-readable description of the event.
     *
     * @return string The event description.
     */
    public function get_description() {
        $courseid = (int)($this->other['courseid'] ?? 0);
        return "The user with id '$this->userid' opened the FastPix video picker " .
            "in the course with id '$courseid'.";
    }
}
