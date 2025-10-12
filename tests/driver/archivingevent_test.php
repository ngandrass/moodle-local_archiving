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

namespace local_archiving\driver;

/**
 * Tests for the archivingevent driver base.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingevent driver base.
 *
 * @runTestsInSeparateProcesses Prevent sharing of mock objects between tests.
 */
final class archivingevent_test extends \advanced_testcase {
    /**
     * Helper to get the test data generator for local_archiving
     *
     * @return \local_archiving_generator
     */
    private function generator(): \local_archiving_generator {
        /** @var \local_archiving_generator */ // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        return self::getDataGenerator()->get_plugin_generator('local_archiving');
    }

    /**
     * This is just a stub test because the archivingevent class currently has
     * no concrete methods.
     *
     * @covers \local_archiving\driver\archivingevent
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_stub(): void {
        $mock = $this->getMockForAbstractClass(archivingevent::class, [], 'archivingevent_mock');
        $this->assertSame('archivingevent', $mock->get_plugin_type());
    }
}
