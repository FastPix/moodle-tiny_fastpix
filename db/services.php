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
 * Web service definitions for tiny_fastpix.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tiny_fastpix_get_my_videos' => [
        'classname'    => 'tiny_fastpix\external\get_my_videos',
        'methodname'   => 'execute',
        'description'  => 'List the current user\'s ready, embeddable FastPix videos for the editor picker.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'mod/fastpix:uploadmedia',
    ],
    'tiny_fastpix_log_shortcode_inserted' => [
        'classname'    => 'tiny_fastpix\external\log_shortcode_inserted',
        'methodname'   => 'execute',
        'description'  => 'Record that an author inserted a FastPix shortcode from the picker.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/fastpix:uploadmedia',
    ],
];
