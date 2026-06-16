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
const searchSelector = '[data-region="fastpix-video-search"]';
const searchWrapSelector = '[data-region="fastpix-video-search-wrap"]';
const noMatchesSelector = '[data-region="fastpix-video-nomatches"]';

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
 * Record that the author inserted a shortcode. Best-effort telemetry: a failure
 * here must never block the insertion, so the rejection is swallowed.
 *
 * @param {number} contextid
 * @param {string} playbackid
 */
const logInsertion = (contextid, playbackid) => {
    fetchMany([{
        methodname: 'tiny_fastpix_log_shortcode_inserted',
        args: {contextid, playbackid},
    }])[0].catch(() => {
        // Logging is non-critical; ignore failures so the editor is unaffected.
        return null;
    });
};

/**
 * Insert the shortcode for the chosen video and close the modal.
 *
 * @param {TinyMCE} editor
 * @param {object} modal
 * @param {string} bookmark
 * @param {number} contextid
 * @param {string} playbackid
 */
const insertShortcode = (editor, modal, bookmark, contextid, playbackid) => {
    editor.selection.moveToBookmark(bookmark);
    editor.execCommand('mceInsertContent', false, `{fastpix:pb_${playbackid}}`);
    editor.selection.moveToBookmark(bookmark);
    logInsertion(contextid, playbackid);
    modal.destroy();
};

/**
 * Wire the client-side title filter. The list is course-scoped and small, so the
 * already-fetched cards are filtered in the browser by their title text — no
 * extra web service call. The search box and the "no matches" notice live
 * outside the list region so they survive the list render.
 *
 * @param {HTMLElement} root The modal root element.
 * @param {HTMLElement} region The list region holding the rendered cards.
 */
const wireSearch = (root, region) => {
    const search = root.querySelector(searchSelector);
    const wrap = root.querySelector(searchWrapSelector);
    const nomatches = root.querySelector(noMatchesSelector);
    const cards = region.querySelectorAll('[data-playbackid]');

    // With nothing to filter, leave the search box hidden — the empty-state
    // message already explains why the list is empty.
    if (!search || !cards.length) {
        return;
    }
    if (wrap) {
        wrap.classList.remove('d-none');
    }

    search.addEventListener('input', () => {
        const needle = search.value.trim().toLowerCase();
        let visible = 0;
        cards.forEach((card) => {
            const match = needle === '' || card.textContent.trim().toLowerCase().includes(needle);
            card.closest('li').classList.toggle('d-none', !match);
            if (match) {
                visible += 1;
            }
        });
        if (nomatches) {
            nomatches.classList.toggle('d-none', !(needle !== '' && visible === 0));
        }
    });
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
        insertShortcode(editor, modal, bookmark, contextid, choice.dataset.playbackid);
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

    wireSearch(root, region);
};
