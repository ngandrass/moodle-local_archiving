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

namespace local_archiving\local\type;

/**
 * Tests for the archive_job_status_filter class.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archive_job_status_filter class.
 */
final class archive_job_status_filter_test extends \advanced_testcase {
    /**
     * Tests that every archive_job_status is assigned to exactly one status filter category.
     *
     * @covers \local_archiving\local\type\archive_job_status_filter
     *
     * @return void
     */
    public function test_every_state_is_categorized_exactly_once(): void {
        $states = [];
        foreach (archive_job_status_filter::cases() as $filter) {
            foreach ($filter->states() as $state) {
                $this->assertNotContains($state, $states, "State {$state->name} must only be in one category.");
                $states[] = $state;
            }
        }

        foreach (archive_job_status::cases() as $state) {
            $this->assertContains($state, $states, "State {$state->name} must be in a category.");
        }
    }

    /**
     * Tests that all status filter categories have a localized name.
     *
     * @covers \local_archiving\local\type\archive_job_status_filter
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_name(): void {
        foreach (archive_job_status_filter::cases() as $filter) {
            $this->assertNotEmpty($filter->name());
            $this->assertStringNotContainsString('[[', $filter->name());
        }
    }
}
