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
 * Shortcode-inserted event for the FastPix TinyMCE plugin.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fired when an author picks a video and the {fastpix:pb_<id>} shortcode is
 * inserted at the cursor. Conceptually the suite's "tiny.shortcode.inserted"
 * signal. The insertion itself is client-side, so the editor reports it through
 * the tiny_fastpix_log_shortcode_inserted web service.
 */
class shortcode_inserted extends \core\event\base {
    /**
     * Build the event for an editor context and the chosen playback id.
     *
     * @param \context $context The editor context the shortcode was inserted in.
     * @param string $playbackid The bare playback id (no pb_ prefix).
     * @return self The created event.
     */
    public static function create_from_context(\context $context, string $playbackid): self {
        return self::create([
            'context' => $context,
            'other'   => ['playbackid' => $playbackid],
        ]);
    }

    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string The event name.
     */
    public static function get_name() {
        return get_string('eventshortcodeinserted', 'tiny_fastpix');
    }

    /**
     * Return a human-readable description of the event.
     *
     * @return string The event description.
     */
    public function get_description() {
        $playbackid = (string)($this->other['playbackid'] ?? '');
        return "The user with id '$this->userid' inserted a FastPix video shortcode " .
            "for the playback id '$playbackid'.";
    }
}
