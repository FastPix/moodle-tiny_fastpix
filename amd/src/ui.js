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
 * Tiny FastPix UI — opens the picker, lists this course's videos the author
 * uploaded and inserts the {fastpix:pb_<id>} shortcode for the chosen one.
 *
 * @module      tiny_fastpix/ui
 * @copyright   2026 FastPix Inc. <support@fastpix.io>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {renderForPromise, replaceNodeContents} from 'core/templates';
import {getString} from 'core/str';
import {component} from './common';
import {getContextId} from './options';
import FastpixModal from './modal';

const listRegionSelector = '[data-region="fastpix-video-list"]';

/**
 * Open the picker for the current editor.
 *
 * @param {TinyMCE} editor
 */
export const handleAction = (editor) => {
    displayDialogue(editor);
};

/**
 * Fetch the videos this course holds for the current user via the web service.
 * The server derives the course from the context, so only the context is sent.
 *
 * @param {number} contextid
 * @returns {Promise<Array<{playbackid: string, title: string}>>}
 */
const fetchVideos = (contextid) => fetchMany([{
    methodname: 'tiny_fastpix_get_my_videos',
    args: {contextid},
}])[0].then((result) => result.videos);

/**
 * Insert the shortcode for the chosen video and close the modal.
 *
 * @param {TinyMCE} editor
 * @param {object} modal
 * @param {string} bookmark
 * @param {string} playbackid
 */
const insertShortcode = (editor, modal, bookmark, playbackid) => {
    editor.selection.moveToBookmark(bookmark);
    editor.execCommand('mceInsertContent', false, `{fastpix:pb_${playbackid}}`);
    editor.selection.moveToBookmark(bookmark);
    modal.destroy();
};

/**
 * Build and show the picker dialogue.
 *
 * @param {TinyMCE} editor
 */
const displayDialogue = async(editor) => {
    // Remember where the cursor was so the shortcode lands there.
    const bookmark = editor.selection.getBookmark();
    const contextid = getContextId(editor);

    const modal = await FastpixModal.create({
        templateContext: {
            elementid: editor.id,
        },
    });

    const root = modal.getRoot()[0];
    const region = root.querySelector(listRegionSelector);

    // The list region persists, so one delegated click listener covers every
    // rendered card.
    region.addEventListener('click', (e) => {
        const choice = e.target.closest('[data-playbackid]');
        if (!choice) {
            return;
        }
        e.preventDefault();
        insertShortcode(editor, modal, bookmark, choice.dataset.playbackid);
    });

    let videos;
    try {
        videos = await fetchVideos(contextid);
    } catch (error) {
        const message = await getString('loaderror', component);
        region.textContent = message;
        return;
    }

    const {html, js} = await renderForPromise(`${component}/videos`, {
        videos,
        hasvideos: videos.length > 0,
    });
    replaceNodeContents(region, html, js);
};
