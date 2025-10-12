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

namespace local_archiving\type;

/**
 * Tests for the archive_job_status class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archive_job_status class.
 */
final class archive_job_status_test extends \advanced_testcase {
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
     * Tests that every state within archive_job_status is classified exactly once as idle, active or final.
     *
     * @covers \local_archiving\type\archive_job_status
     *
     * @return void
     */
    public function test_state_classification(): void {
        // Iterate over all states and check their classification.
        $states = [];

        // Check idle states.
        foreach (archive_job_status::get_idle_states() as $state) {
            $this->assertTrue($state->is_idle(), "State {$state->name} must be classified as idle.");
            $this->assertFalse($state->is_active(), "State {$state->name} must not be classified as active.");
            $this->assertFalse($state->is_final(), "State {$state->name} must not be classified as final.");
            $states[] = $state;
        }

        // Check active states.
        foreach (archive_job_status::get_active_states() as $state) {
            $this->assertFalse($state->is_idle(), "State {$state->name} must not be classified as idle.");
            $this->assertTrue($state->is_active(), "State {$state->name} must be classified as active.");
            $this->assertFalse($state->is_final(), "State {$state->name} must not be classified as final.");
            $states[] = $state;
        }

        // Check final states.
        foreach (archive_job_status::get_final_states() as $state) {
            $this->assertFalse($state->is_idle(), "State {$state->name} must not be classified as idle.");
            $this->assertFalse($state->is_active(), "State {$state->name} must not be classified as active.");
            $this->assertTrue($state->is_final(), "State {$state->name} must be classified as final.");
            $states[] = $state;
        }

        // Ensure that all existing states are covered exactly once.
        $this->assertEqualsCanonicalizing(archive_job_status::cases(), $states, 'All states must be classified exactly once.');
    }

    /**
     * Tests that status display args are present for all existing states.
     *
     * @covers \local_archiving\type\archive_job_status
     * @dataProvider status_display_args_data_provider
     *
     * @param archive_job_status $status
     * @return void
     * @throws \coding_exception
     */
    public function test_status_display_args(archive_job_status $status): void {
        $args = $status->status_display_args();
        $this->assertNotEmpty($args->text, 'Status text must not be empty.');
        $this->assertNotEmpty($args->help, 'Status help must not be empty.');
        $this->assertNotEmpty($args->color, 'Status color must not be empty.');
    }

    /**
     * Test data provider for test_status_display_args.
     *
     * @return array<string, array{archive_job_status}> Test cases with all archive_job_status values.
     */
    public static function status_display_args_data_provider(): array {
        $data = [];
        foreach (archive_job_status::cases() as $status) {
            $data[$status->name] = [$status];
        }

        return $data;
    }
}
