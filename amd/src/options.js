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
 * Options helper for the Tiny FastPix plugin.
 *
 * @module      tiny_fastpix/options
 * @copyright   2026 FastPix Inc. <support@fastpix.io>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getPluginOptionName} from 'editor_tiny/options';
import {pluginName} from './common';

const contextIdName = getPluginOptionName(pluginName, 'contextid');
const courseIdName = getPluginOptionName(pluginName, 'courseid');
const canEmbedName = getPluginOptionName(pluginName, 'canembed');

/**
 * Register the plugin options (populated from plugininfo).
 *
 * @param {TinyMCE} editor
 */
export const register = (editor) => {
    const registerOption = editor.options.register;

    registerOption(contextIdName, {
        processor: 'number',
        "default": 0,
    });

    registerOption(courseIdName, {
        processor: 'number',
        "default": 0,
    });

    registerOption(canEmbedName, {
        processor: 'boolean',
        "default": false,
    });
};

/**
 * The context id the editor is used in.
 *
 * @param {TinyMCE} editor
 * @returns {number}
 */
export const getContextId = (editor) => editor.options.get(contextIdName);

/**
 * The course id the editor is used in (0 outside a course).
 *
 * @param {TinyMCE} editor
 * @returns {number}
 */
export const getCourseId = (editor) => editor.options.get(courseIdName);

/**
 * Whether the current user may embed FastPix videos (gates the toolbar button).
 *
 * @param {TinyMCE} editor
 * @returns {boolean}
 */
export const canEmbed = (editor) => editor.options.get(canEmbedName);
