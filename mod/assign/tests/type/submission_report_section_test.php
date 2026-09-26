<?php
// This file is part of Moodle - http://moodle.org/
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

namespace archivingmod_assign\type;


use archivingmod_assign\local\type\submission_report_section;

/**
 * Tests for the submission_report_section type
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the submission_report_section type
 */
final class submission_report_section_test extends \advanced_testcase {
    /**
     * Basic execution test for dependencies() method.
     *
     * @covers \archivingmod_assign\local\type\submission_report_section
     *
     * @return void
     */
    public function test_dependencies(): void {
        foreach (submission_report_section::cases() as $case) {
            $this->assertNotNull($case->dependencies());
        }
    }
}
