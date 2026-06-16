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
 * Tests for the tiny_fastpix_log_shortcode_inserted web service.
 *
 * The service records the "shortcode inserted" signal as a Moodle event. It is
 * gated by mod/fastpix:uploadmedia at the course context and protected by
 * require_sesskey(); these tests lock that gate plus the event payload.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tiny_fastpix\external\log_shortcode_inserted
 */
final class log_shortcode_inserted_test extends \externallib_advanced_testcase {
    /**
     * Create a course with an editing teacher enrolled, set as the current user,
     * with a valid sesskey ready for the state-changing call.
     *
     * @return array [\context_course $context, \stdClass $teacher, \stdClass $course]
     */
    private function teacher_in_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $_GET['sesskey'] = sesskey();
        return [\context_course::instance($course->id), $teacher, $course];
    }

    /**
     * Run the service and clean the return value.
     *
     * @param \context $context The editor context.
     * @param string $playbackid The playback id inserted.
     * @return array The cleaned result.
     */
    private function log(\context $context, string $playbackid): array {
        $result = log_shortcode_inserted::execute($context->id, $playbackid);
        return \core_external\external_api::clean_returnvalue(
            log_shortcode_inserted::execute_returns(),
            $result
        );
    }

    /**
     * The happy path: a capable teacher's insertion is logged and fires the
     * shortcode_inserted event carrying the context and playback id.
     */
    public function test_logs_and_fires_event(): void {
        $this->resetAfterTest();
        [$context, $teacher] = $this->teacher_in_course();

        $sink = $this->redirectEvents();
        $result = $this->log($context, 'keepme01');
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($result['logged']);

        $inserted = array_values(array_filter($events, static function ($e) {
            return $e instanceof \tiny_fastpix\event\shortcode_inserted;
        }));
        $this->assertCount(1, $inserted);
        $this->assertSame($context->id, $inserted[0]->contextid);
        $this->assertSame((int)$teacher->id, (int)$inserted[0]->userid);
        $this->assertSame('keepme01', $inserted[0]->other['playbackid']);
        // Exercise the event's display strings (name + description).
        $this->assertNotEmpty(\tiny_fastpix\event\shortcode_inserted::get_name());
        $this->assertStringContainsString('keepme01', $inserted[0]->get_description());
    }

    /**
     * Outside a course there is nothing to log: the call returns logged=false
     * and fires no event.
     */
    public function test_non_course_context_does_not_log(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $_GET['sesskey'] = sesskey();

        $sink = $this->redirectEvents();
        $result = $this->log(\context_user::instance($user->id), 'keepme01');
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($result['logged']);
        $inserted = array_filter($events, static function ($e) {
            return $e instanceof \tiny_fastpix\event\shortcode_inserted;
        });
        $this->assertCount(0, $inserted);
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
        $_GET['sesskey'] = sesskey();

        $this->expectException(\required_capability_exception::class);
        log_shortcode_inserted::execute($context->id, 'keepme01');
    }

    /**
     * A missing/incorrect sesskey is rejected (CSRF guard on the write call).
     */
    public function test_requires_sesskey(): void {
        $this->resetAfterTest();
        [$context] = $this->teacher_in_course();
        unset($_GET['sesskey']);
        $_POST['sesskey'] = 'definitely-wrong';

        $this->expectException(\moodle_exception::class);
        log_shortcode_inserted::execute($context->id, 'keepme01');
    }
}
