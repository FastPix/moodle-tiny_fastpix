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

namespace tiny_fastpix\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tiny_fastpix_get_my_videos web service.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tiny_fastpix\external\get_my_videos
 */
final class get_my_videos_test extends \externallib_advanced_testcase {
    /**
     * Insert an asset row into local_fastpix_asset.
     *
     * @param array $overrides Column overrides; owner_userid + playback_id matter.
     * @return void
     */
    private function make_asset(array $overrides): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_fastpix_asset', (object)array_merge([
            'fastpix_id'           => 'fp' . random_string(10),
            'playback_id'          => 'play' . random_string(8),
            'owner_userid'         => 0,
            'title'                => 'Untitled',
            'status'               => 'ready',
            'access_policy'        => 'public',
            'drm_required'         => 0,
            'no_skip_required'     => 0,
            'has_captions'         => 0,
            'gdpr_delete_attempts' => 0,
            'timecreated'          => $now,
            'timemodified'         => $now,
        ], $overrides));
    }

    /**
     * Insert an asset and return it, including the generated id and fastpix_id
     * (needed to link mod_fastpix activities for the name-search tests).
     *
     * @param array $overrides Column overrides.
     * @return \stdClass The inserted asset (with ->id and ->fastpix_id set).
     */
    private function make_asset_linked(array $overrides): \stdClass {
        global $DB;
        $now = time();
        $record = (object)array_merge([
            'fastpix_id'           => 'fp' . random_string(10),
            'playback_id'          => 'play' . random_string(8),
            'owner_userid'         => 0,
            'title'                => 'Untitled',
            'status'               => 'ready',
            'access_policy'        => 'public',
            'drm_required'         => 0,
            'no_skip_required'     => 0,
            'has_captions'         => 0,
            'gdpr_delete_attempts' => 0,
            'timecreated'          => $now,
            'timemodified'         => $now,
        ], $overrides);
        $record->id = $DB->insert_record('local_fastpix_asset', $record);
        return $record;
    }

    /**
     * Insert a mod_fastpix activity row carrying the author-typed name. Only the
     * fields the name search reads are populated.
     *
     * @param int $courseid The course the activity lives in.
     * @param string $name The author-typed activity name.
     * @param array $overrides Link columns: fastpix_asset_id or upload_session_id.
     * @return void
     */
    private function make_activity(int $courseid, string $name, array $overrides = []): void {
        global $DB;
        $now = time();
        $DB->insert_record('fastpix', (object)array_merge([
            'course'                   => $courseid,
            'name'                     => $name,
            'intro'                    => '',
            'introformat'              => FORMAT_HTML,
            'fastpix_asset_id'         => null,
            'upload_session_id'        => null,
            'completion_watch_percent' => 0,
            'no_skip_required'         => 0,
            'default_show_captions'    => 0,
            'grademax'                 => 0,
            'timecreated'              => $now,
            'timemodified'             => $now,
        ], $overrides));
    }

    /**
     * Insert an upload session linking an asset fastpix_id to a session id.
     *
     * @param string $fastpixid The asset fastpix_id this session produced.
     * @param int $userid The uploader.
     * @return int The new session id.
     */
    private function make_upload_session(string $fastpixid, int $userid): int {
        global $DB;
        $now = time();
        return $DB->insert_record('local_fastpix_upload_session', (object)[
            'userid'      => $userid,
            'upload_id'   => 'up' . random_string(8),
            'upload_url'  => 'https://example.com/upload',
            'fastpix_id'  => $fastpixid,
            'source_url'  => '',
            'state'       => 'ready',
            'timecreated' => $now,
            'expires_at'  => $now + 3600,
        ]);
    }

    /**
     * Create a course with an editing teacher enrolled.
     *
     * @return array [\context_course $context, \stdClass $teacher, \stdClass $course]
     */
    private function teacher_in_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        return [\context_course::instance($course->id), $teacher, $course];
    }

    /**
     * Run the web service with a search term and clean the return value.
     *
     * @param \context $context The editor context.
     * @param string $query The search term.
     * @return array The cleaned result.
     */
    private function run_search(\context $context, string $query): array {
        $result = get_my_videos::execute($context->id, $query);
        return \core_external\external_api::clean_returnvalue(get_my_videos::execute_returns(), $result);
    }

    /**
     * The service returns only the caller's own ready, non-DRM, embeddable videos.
     */
    public function test_returns_only_own_embeddable_videos(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        // Should appear: own, ready, public, has playback id.
        $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => 'keepme01', 'title' => 'Keep me']);
        // Excluded: DRM-required (filter can't play it).
        $this->make_asset([
            'owner_userid' => $teacher->id, 'playback_id' => 'drmone01',
            'drm_required' => 1, 'access_policy' => 'drm',
        ]);
        // Excluded: not ready yet.
        $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => 'prep0001', 'status' => 'preparing']);
        // Excluded: no playback id.
        $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => null]);
        // Excluded: belongs to another user.
        $this->make_asset(['owner_userid' => $other->id, 'playback_id' => 'other001']);

        $result = get_my_videos::execute($context->id);
        $result = \core_external\external_api::clean_returnvalue(get_my_videos::execute_returns(), $result);

        $this->assertCount(1, $result['videos']);
        $this->assertSame('keepme01', $result['videos'][0]['playbackid']);
        $this->assertSame('Keep me', $result['videos'][0]['title']);
    }

    /**
     * A user without mod/fastpix:uploadmedia is refused.
     */
    public function test_requires_uploadmedia_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_my_videos::execute($context->id);
    }

    /**
     * Search matches the asset title.
     */
    public function test_search_matches_asset_title(): void {
        $this->resetAfterTest();
        [$context, $teacher] = $this->teacher_in_course();
        $this->setUser($teacher);

        $this->make_asset_linked(['owner_userid' => $teacher->id, 'playback_id' => 'titlem01', 'title' => 'Annual report 2026']);
        $this->make_asset_linked(['owner_userid' => $teacher->id, 'playback_id' => 'titlem02', 'title' => 'Holiday party']);

        $result = $this->run_search($context, 'annual');

        $this->assertCount(1, $result['videos']);
        $this->assertSame('titlem01', $result['videos'][0]['playbackid']);
    }

    /**
     * Search matches the mod_fastpix activity name via the direct asset link,
     * even when the asset title itself does not contain the term.
     */
    public function test_search_matches_activity_name_direct(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        $asset = $this->make_asset_linked([
            'owner_userid' => $teacher->id, 'playback_id' => 'namedir1', 'title' => 'zzz',
        ]);
        $this->make_activity($course->id, 'Photosynthesis lecture', ['fastpix_asset_id' => $asset->id]);

        $result = $this->run_search($context, 'photosynthesis');

        $this->assertCount(1, $result['videos']);
        $this->assertSame('namedir1', $result['videos'][0]['playbackid']);
        $this->assertSame('Photosynthesis lecture', $result['videos'][0]['title']);
    }

    /**
     * Search matches the activity name reached only through the upload session
     * (the direct fastpix_asset_id link was never set).
     */
    public function test_search_matches_activity_name_via_upload_session(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        $asset = $this->make_asset_linked([
            'owner_userid' => $teacher->id, 'playback_id' => 'namesess', 'title' => 'zzz',
        ]);
        $sessionid = $this->make_upload_session($asset->fastpix_id, $teacher->id);
        $this->make_activity($course->id, 'Mitosis recap', ['upload_session_id' => $sessionid]);

        $result = $this->run_search($context, 'mitosis');

        $this->assertCount(1, $result['videos']);
        $this->assertSame('namesess', $result['videos'][0]['playbackid']);
        $this->assertSame('Mitosis recap', $result['videos'][0]['title']);
    }

    /**
     * Search is owner-scoped: another user's asset must never surface, even when
     * its activity name matches the search term. (P0 security invariant.)
     */
    public function test_search_is_owner_scoped(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $otherasset = $this->make_asset_linked([
            'owner_userid' => $other->id, 'playback_id' => 'leak0001', 'title' => 'zzz',
        ]);
        $this->make_activity($course->id, 'Confidential briefing', ['fastpix_asset_id' => $otherasset->id]);

        $result = $this->run_search($context, 'confidential');

        $this->assertCount(0, $result['videos']);
    }

    /**
     * A search term that matches nothing returns an empty list.
     */
    public function test_search_no_match_returns_empty(): void {
        $this->resetAfterTest();
        [$context, $teacher] = $this->teacher_in_course();
        $this->setUser($teacher);
        $this->make_asset_linked(['owner_userid' => $teacher->id, 'playback_id' => 'present1', 'title' => 'Welcome video']);

        $result = $this->run_search($context, 'nonexistentterm');

        $this->assertCount(0, $result['videos']);
    }
}
