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

namespace local_archiving\util;


/**
 * Tests for the time util class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the time util class.
 */
final class time_util_test extends \advanced_testcase {

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
     * Test conversion of seconds to short human-readable representations.
     *
     * @dataProvider human_readable_data_provider
     * @covers \local_archiving\util\time_util
     */
    public function test_duration_to_human_readable(int $duration, string $expected) {
        $this->assertEquals($expected, time_util::duration_to_human_readable($duration));
    }

    /**
     * Data provider for test_duration_to_human_readable.
     *
     * @return array<string, array{int, string}> Data sets with input duration
     * in seconds and expected human-readable string.
     */
    public static function human_readable_data_provider(): array {
        return [
            'zero seconds' => [0, '0s'],
            'only seconds' => [45, '45s'],
            'only minutes' => [MINSECS, '1m'],
            'minutes and seconds' => [MINSECS + 30, '1m 30s'],
            'only hours' => [HOURSECS, '1h'],
            'hours and minutes' => [HOURSECS + MINSECS, '1h 1m'],
            'only days' => [DAYSECS, '1d'],
            'days and hours' => [DAYSECS + HOURSECS, '1d 1h'],
            'only months' => [YEARSECS / 12, '1m'],
            'months and days' => [(YEARSECS / 12) + DAYSECS, '1m 1d'],
            'only years' => [YEARSECS, '1y'],
            'complex' => [YEARSECS + 2 * (YEARSECS / 12) + 3 * DAYSECS + 4 * HOURSECS + 5 * MINSECS + 6, '1y 2m 3d 4h 5m 6s'],
        ];
    }

    /**
     * Test conversion of seconds to largest fitting time unit.
     *
     * @dataProvider duration_to_unit_data_provider
     * @covers \local_archiving\util\time_util
     */
    public function test_duration_to_unit(int $duration, array $expected) {
        $this->assertEquals($expected, time_util::duration_to_unit($duration));
    }

    /**
     * Data provider for test_duration_to_unit.
     *
     * @return array<string, array{int, array{int, string}}> Data sets with input
     * duration in seconds and expected [value, unit] combination.
     * @throws \coding_exception
     */
    public static function duration_to_unit_data_provider(): array {
        return [
            'weeks' => [2 * WEEKSECS, [2, get_string('weeks')]],
            'days' => [3 * DAYSECS, [3, get_string('days')]],
            'hours' => [4 * HOURSECS, [4, get_string('hours')]],
            'minutes' => [5 * MINSECS, [5, get_string('minutes')]],
            'seconds' => [123, [123, get_string('seconds')]],
        ];
    }

}
