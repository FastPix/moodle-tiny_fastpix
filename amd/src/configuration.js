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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tiny FastPix configuration — adds the button to the toolbar and menu.
 *
 * @module      tiny_fastpix/configuration
 * @copyright   2026 FastPix Inc. <support@fastpix.io>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {buttonName} from './common';
import {
    addMenubarItem,
    addToolbarButton,
} from 'editor_tiny/utils';

export const configure = (instanceConfig) => {
    return {
        toolbar: addToolbarButton(instanceConfig.toolbar, 'content', buttonName),
        menu: addMenubarItem(instanceConfig.menu, 'insert', buttonName),
    };
};
