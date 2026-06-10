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

namespace tiny_fastpix;

/**
 * Tests for the tiny_fastpix plugininfo capability gate.
 *
 * The button is only ever emitted for users who hold mod/fastpix:uploadmedia in
 * the editing context. is_enabled() is the server-side half of that gate (the
 * JS canUpload() check is the client half); these tests lock the server half so
 * the button can never appear for an unauthorised user even if the JS changes.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tiny_fastpix\plugininfo
 */
final class plugininfo_test extends \advanced_testcase {
    /**
     * A user without mod/fastpix:uploadmedia does not get the button.
     */
    public function test_button_hidden_without_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->assertFalse(plugininfo::is_enabled($context, [], []));
        $this->assertFalse(
            plugininfo::get_plugin_configuration_for_context($context, [], [])['canembed']
        );
    }

    /**
     * A user with mod/fastpix:uploadmedia (editing teacher) does get the button.
     */
    public function test_button_visible_with_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertTrue(plugininfo::is_enabled($context, [], []));
        $this->assertTrue(
            plugininfo::get_plugin_configuration_for_context($context, [], [])['canembed']
        );
    }
}
