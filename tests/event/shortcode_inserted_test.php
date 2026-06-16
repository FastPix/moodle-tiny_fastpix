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

namespace tiny_fastpix\event;

/**
 * Tests for the shortcode_inserted event.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tiny_fastpix\event\shortcode_inserted
 */
final class shortcode_inserted_test extends \advanced_testcase {
    /**
     * The event triggers with the editor context and the playback id, and
     * exposes a localised name and a description naming the playback id.
     */
    public function test_event_payload_and_strings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $sink = $this->redirectEvents();
        shortcode_inserted::create_from_context($context, 'keepme01')->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(shortcode_inserted::class, $event);
        $this->assertSame($context->id, $event->contextid);
        $this->assertSame('keepme01', $event->other['playbackid']);
        $this->assertNotEmpty(shortcode_inserted::get_name());
        $this->assertStringContainsString('keepme01', $event->get_description());
    }
}
