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
 * Tiny FastPix UI — opens the picker, lists the author's videos and inserts the
 * {fastpix:pb_<id>} shortcode for the chosen one.
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
const searchSelector = '[data-region="fastpix-search"]';

// Wait for a pause in typing before hitting the web service, and ignore very
// short terms (a single letter would match almost everything).
const searchDebounceMs = 300;
const searchMinChars = 2;

/**
 * Open the picker for the current editor.
 *
 * @param {TinyMCE} editor
 */
export const handleAction = (editor) => {
    displayDialogue(editor);
};

/**
 * Fetch the current user's embeddable videos via the web service.
 *
 * @param {number} contextid
 * @param {string} query Optional search term; empty returns the recent list.
 * @returns {Promise<Array<{playbackid: string, title: string}>>}
 */
const fetchVideos = (contextid, query = '') => fetchMany([{
    methodname: 'tiny_fastpix_get_my_videos',
    args: {contextid, query},
}])[0].then((result) => result.videos);

/**
 * Debounce a function: only run it after `delay` ms pass without a new call.
 *
 * @param {Function} fn
 * @param {number} delay
 * @returns {Function}
 */
const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

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
    const search = root.querySelector(searchSelector);

    // Tracks the most recent query so an out-of-order web service response (a
    // slow early request landing after a later one) can't overwrite the list.
    let currentQuery = '';

    const renderList = async(query) => {
        currentQuery = query;

        let videos;
        try {
            videos = await fetchVideos(contextid, query);
        } catch (error) {
            if (currentQuery !== query) {
                return;
            }
            const message = await getString('loaderror', component);
            region.textContent = message;
            return;
        }
        if (currentQuery !== query) {
            return;
        }

        const {html, js} = await renderForPromise(`${component}/videos`, {
            videos,
            hasvideos: videos.length > 0,
            issearch: query !== '',
        });
        if (currentQuery !== query) {
            return;
        }
        replaceNodeContents(region, html, js);
    };

    // The list region persists across re-renders, so one delegated click
    // listener covers every (re)rendered card.
    region.addEventListener('click', (e) => {
        const choice = e.target.closest('[data-playbackid]');
        if (!choice) {
            return;
        }
        e.preventDefault();
        insertShortcode(editor, modal, bookmark, choice.dataset.playbackid);
    });

    if (search) {
        search.addEventListener('input', debounce(() => {
            const term = search.value.trim();
            // Below the minimum length, fall back to the recent list.
            renderList(term.length >= searchMinChars ? term : '');
        }, searchDebounceMs));
    }

    // Initial load: the most recent videos.
    await renderList('');
};
