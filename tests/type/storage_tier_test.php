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
 * Tests for the storage_tier class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the storage_tier class.
 */
final class storage_tier_test extends \advanced_testcase {

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
     * Tests that status display args are present for all existing storage tiers.
     *
     * @covers \local_archiving\type\storage_tier
     * @dataProvider status_display_args_data_provider
     *
     * @param storage_tier $tier
     * @return void
     * @throws \coding_exception
     */
    public function test_status_display_args(storage_tier $tier): void {
        $this->assertNotEmpty($tier->name(), 'Tier name must not be empty.');
        $this->assertNotEmpty($tier->help(), 'Tier help must not be empty.');
        $this->assertNotEmpty($tier->color(), 'Tier color must not be empty.');
    }

    /**
     * Test data provider for test_status_display_args.
     *
     * @return array<string, array{storage_tier}> Test cases with all storage_tier values.
     */
    public static function status_display_args_data_provider(): array {
        $data = [];
        foreach (storage_tier::cases() as $tier) {
            $data[$tier->name] = [$tier];
        }

        return $data;
    }

}

