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
 * Behat data generator for tiny_fastpix.
 *
 * Wires the Gherkin step:
 *
 *   Given the following "tiny_fastpix > assets" exist:
 *     | user     | playback_id | title      |
 *     | teacher1 | keepme01    | My lecture |
 *
 * into tiny_fastpix_generator::create_asset() which inserts a row into
 * local_fastpix_asset (the upstream local_fastpix plugin is frozen; this
 * generator writes directly to the table rather than calling internal APIs).
 *
 * The 'user' column holds a username string.  The 'switchids' mechanism in
 * behat_generator_base calls get_user_id($username) — defined on the base
 * class — which looks up mdl_user.id and stores the result in 'owner_userid'.
 * This is the same pattern used by mod_forum's behat generator for its 'user'
 * column (see mod/forum/tests/generator/behat_mod_forum_generator.php).
 *
 * Source confirmed: lib/behat/classes/behat_generator_base.php §236-245 (the
 * switchids loop) and §330-337 (get_user_id).
 *
 * @package    tiny_fastpix
 * @category   test
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator for tiny_fastpix.
 *
 * @package    tiny_fastpix
 * @category   test
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tiny_fastpix_generator extends behat_generator_base {
    /**
     * Declare the entities that can be created via Behat tables.
     *
     * The 'assets' entity maps to tiny_fastpix_generator::create_asset().
     *
     * Required columns:
     *   - playback_id  The bare playback id that will appear in the shortcode.
     *   - user         A Moodle username.  The switchids mechanism translates
     *                  this to owner_userid before create_asset() is called, so
     *                  the asset is owned by the correct user and will appear in
     *                  that user's picker list.
     *
     * Optional columns: title, status, access_policy, drm_required — all have
     * safe defaults in tiny_fastpix_generator::create_asset().
     *
     * @return array<string, array<string, mixed>>
     */
    protected function get_creatable_entities(): array {
        return [
            'assets' => [
                'singular'      => 'asset',
                'datagenerator' => 'asset',
                'required'      => ['playback_id', 'user'],
                // The 'user' column (a username) resolves to 'owner_userid'; the
                // optional 'course' column (a shortname) resolves to 'courseid',
                // which makes the generator link the asset to a mod_fastpix
                // activity in that course so the course-scoped picker lists it.
                // Resolution is the switchids mechanism in behat_generator_base.
                'switchids'     => ['user' => 'owner_userid', 'course' => 'courseid'],
            ],
        ];
    }
}
