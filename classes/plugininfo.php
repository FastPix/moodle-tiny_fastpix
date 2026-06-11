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
 * TinyMCE plugin definition for tiny_fastpix.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_fastpix;

use context;
use editor_tiny\plugin;
use editor_tiny\plugin_with_buttons;
use editor_tiny\plugin_with_configuration;
use editor_tiny\plugin_with_menuitems;

/**
 * The FastPix "Insert video" TinyMCE plugin.
 *
 * Adds a toolbar button / menu item that opens a picker of the author's ready
 * FastPix videos and inserts the matching {fastpix:pb_<id>} shortcode. It does
 * not mint its own capability — it reuses mod/fastpix:uploadmedia, exactly as
 * filter_fastpix reuses mod/fastpix:view.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugininfo extends plugin implements plugin_with_buttons, plugin_with_configuration, plugin_with_menuitems {
    /**
     * Whether the plugin is enabled for this context.
     *
     * Only authors who may upload/manage FastPix media get the picker.
     *
     * @param context $context The context the editor is used in.
     * @param array $options The editor options.
     * @param array $fpoptions The filepicker options.
     * @param \editor_tiny\editor|null $editor The editor instance.
     * @return bool
     */
    public static function is_enabled(
        context $context,
        array $options,
        array $fpoptions,
        ?\editor_tiny\editor $editor = null
    ): bool {
        return has_capability('mod/fastpix:uploadmedia', $context);
    }

    /**
     * The buttons this plugin provides.
     *
     * @return string[]
     */
    public static function get_available_buttons(): array {
        return [
            'tiny_fastpix/insertvideo',
        ];
    }

    /**
     * The menu items this plugin provides.
     *
     * @return string[]
     */
    public static function get_available_menuitems(): array {
        // The menu item exposes the same single "Insert FastPix Video" action as
        // the toolbar button, so it mirrors get_available_buttons() rather than
        // duplicating the list.
        return self::get_available_buttons();
    }

    /**
     * Per-context configuration passed to the JS plugin.
     *
     * @param context $context The context the editor is used in.
     * @param array $options The editor options.
     * @param array $fpoptions The filepicker options.
     * @param \editor_tiny\editor|null $editor The editor instance.
     * @return array
     */
    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?\editor_tiny\editor $editor = null
    ): array {
        $coursecontext = $context->get_course_context(false);

        return [
            'contextid'  => $context->id,
            'courseid'   => $coursecontext ? $coursecontext->instanceid : 0,
            'canembed'   => has_capability('mod/fastpix:uploadmedia', $context),
        ];
    }
}
