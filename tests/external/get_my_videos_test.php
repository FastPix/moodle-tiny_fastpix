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
 * The picker lists ONLY videos that (a) the caller owns, (b) belong to the
 * editor's course (referenced by a mod_fastpix activity in it), (c) are ready
 * and (d) are embeddable (public + has a playback id). These tests lock each
 * facet, with the owner-scope and course-scope cases as the P0 security floor.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tiny_fastpix\external\get_my_videos
 */
final class get_my_videos_test extends \externallib_advanced_testcase {
    /**
     * Insert an asset and return it, including the generated id and fastpix_id
     * (needed to link mod_fastpix activities).
     *
     * @param array $overrides Column overrides; owner_userid + playback_id matter.
     * @return \stdClass The inserted asset (with ->id and ->fastpix_id set).
     */
    private function make_asset(array $overrides): \stdClass {
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
     * Insert a mod_fastpix activity row carrying the author-typed name and the
     * link columns the resolver reads.
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
     * Run the web service and clean the return value.
     *
     * @param \context $context The editor context.
     * @return array The cleaned result.
     */
    private function list_videos(\context $context): array {
        $result = get_my_videos::execute($context->id);
        return \core_external\external_api::clean_returnvalue(get_my_videos::execute_returns(), $result);
    }

    /**
     * The happy path: an owned, ready, public video linked to an activity in the
     * editor's course is listed, labelled with the activity name.
     */
    public function test_lists_owned_ready_public_video_in_course(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        $asset = $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => 'keepme01', 'title' => 'zzz']);
        $this->make_activity($course->id, 'Photosynthesis lecture', ['fastpix_asset_id' => $asset->id]);

        $result = $this->list_videos($context);

        $this->assertCount(1, $result['videos']);
        $this->assertSame('keepme01', $result['videos'][0]['playbackid']);
        $this->assertSame('Photosynthesis lecture', $result['videos'][0]['title']);
    }

    /**
     * The activity may link to the asset only through its upload session (the
     * direct fastpix_asset_id was never set); the video must still be listed.
     */
    public function test_resolves_asset_via_upload_session(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        $asset = $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => 'sess0001', 'title' => 'zzz']);
        $sessionid = $this->make_upload_session($asset->fastpix_id, $teacher->id);
        $this->make_activity($course->id, 'Mitosis recap', ['upload_session_id' => $sessionid]);

        $result = $this->list_videos($context);

        $this->assertCount(1, $result['videos']);
        $this->assertSame('sess0001', $result['videos'][0]['playbackid']);
        $this->assertSame('Mitosis recap', $result['videos'][0]['title']);
    }

    /**
     * Owner scope (P0): a video owned by another user, even when its activity is
     * in this course, must never appear.
     */
    public function test_excludes_video_owned_by_other_user(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $otherasset = $this->make_asset(['owner_userid' => $other->id, 'playback_id' => 'leak0001', 'title' => 'zzz']);
        $this->make_activity($course->id, 'Confidential briefing', ['fastpix_asset_id' => $otherasset->id]);

        $result = $this->list_videos($context);

        $this->assertCount(0, $result['videos']);
    }

    /**
     * Course scope (P0): the caller's own video linked to an activity in a
     * different course must not appear in this course's picker.
     */
    public function test_excludes_video_in_other_course(): void {
        $this->resetAfterTest();
        [$context, $teacher] = $this->teacher_in_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $this->setUser($teacher);

        $asset = $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => 'elsew001', 'title' => 'zzz']);
        $this->make_activity($othercourse->id, 'Lecture in another course', ['fastpix_asset_id' => $asset->id]);

        $result = $this->list_videos($context);

        $this->assertCount(0, $result['videos']);
    }

    /**
     * An owned, ready, public video that is not referenced by any activity in
     * the course is out of scope (course membership comes from the activity).
     */
    public function test_excludes_video_with_no_activity(): void {
        $this->resetAfterTest();
        [$context, $teacher] = $this->teacher_in_course();
        $this->setUser($teacher);

        $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => 'noact001', 'title' => 'Orphan']);

        $result = $this->list_videos($context);

        $this->assertCount(0, $result['videos']);
    }

    /**
     * Non-ready, DRM/non-public, and playback-less assets are all excluded even
     * when correctly owned and linked in the course.
     */
    public function test_excludes_unembeddable_states(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        $notready = $this->make_asset([
            'owner_userid' => $teacher->id, 'playback_id' => 'prep0001', 'status' => 'preparing',
        ]);
        $drm = $this->make_asset([
            'owner_userid' => $teacher->id, 'playback_id' => 'drmone01',
            'drm_required' => 1, 'access_policy' => 'drm',
        ]);
        $noplayback = $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => null]);

        $this->make_activity($course->id, 'Not ready', ['fastpix_asset_id' => $notready->id]);
        $this->make_activity($course->id, 'DRM', ['fastpix_asset_id' => $drm->id]);
        $this->make_activity($course->id, 'No playback', ['fastpix_asset_id' => $noplayback->id]);

        $result = $this->list_videos($context);

        $this->assertCount(0, $result['videos']);
    }

    /**
     * A user without mod/fastpix:uploadmedia in the course is refused.
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
     * Outside a course (e.g. a user context) there is nothing in scope, so the
     * service returns an empty list rather than leaking anything.
     */
    public function test_non_course_context_returns_empty(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = $this->list_videos(\context_user::instance($user->id));

        $this->assertCount(0, $result['videos']);
    }

    /**
     * A capability holder in a course who owns no ready videos gets an empty
     * list (the owner-list short-circuit).
     */
    public function test_returns_empty_when_user_owns_no_videos(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        // An activity exists in the course, but it references no owned asset.
        $this->make_activity($course->id, 'Empty activity');

        $result = $this->list_videos($context);

        $this->assertCount(0, $result['videos']);
    }

    /**
     * An activity in the course that links to no asset (neither a direct asset
     * id nor an upload session) is skipped without affecting valid results.
     */
    public function test_ignores_activity_with_no_asset_link(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        $asset = $this->make_asset(['owner_userid' => $teacher->id, 'playback_id' => 'valid001']);
        $this->make_activity($course->id, 'Good video', ['fastpix_asset_id' => $asset->id]);
        // No fastpix_asset_id and no upload_session_id -> resolves to nothing.
        $this->make_activity($course->id, 'Dangling activity');

        $result = $this->list_videos($context);

        $this->assertCount(1, $result['videos']);
        $this->assertSame('valid001', $result['videos'][0]['playbackid']);
    }

    /**
     * When the activity name is blank, the label falls back to the asset's own
     * title (provided it is a real title, not the "Asset <id>" placeholder).
     */
    public function test_label_falls_back_to_asset_title(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        $asset = $this->make_asset([
            'owner_userid' => $teacher->id, 'playback_id' => 'fallbk01', 'title' => 'Real asset title',
        ]);
        $this->make_activity($course->id, '   ', ['fastpix_asset_id' => $asset->id]);

        $result = $this->list_videos($context);

        $this->assertCount(1, $result['videos']);
        $this->assertSame('Real asset title', $result['videos'][0]['title']);
    }

    /**
     * When the activity name is blank and the asset only carries the projector's
     * "Asset <fastpix_id>" placeholder title, the label is the localised
     * "Untitled video" rather than a raw id.
     */
    public function test_label_falls_back_to_untitled(): void {
        $this->resetAfterTest();
        [$context, $teacher, $course] = $this->teacher_in_course();
        $this->setUser($teacher);

        $asset = $this->make_asset([
            'owner_userid' => $teacher->id, 'playback_id' => 'untitl01',
            'fastpix_id'   => 'fpplaceholder', 'title' => 'Asset fpplaceholder',
        ]);
        $this->make_activity($course->id, '', ['fastpix_asset_id' => $asset->id]);

        $result = $this->list_videos($context);

        $this->assertCount(1, $result['videos']);
        $this->assertSame(get_string('untitledvideo', 'tiny_fastpix'), $result['videos'][0]['title']);
    }
}
