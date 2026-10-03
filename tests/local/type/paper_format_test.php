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
 * Tests for the paper_format class.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the paper_format class.
 */
final class paper_format_test extends \advanced_testcase {
    /**
     * Tests that all paper formats provide a valid correction margin size.
     *
     * @covers \local_archiving\local\type\paper_format
     * @dataProvider paper_formats_data_provider
     *
     * @param paper_format $format The paper format to test
     * @return void
     */
    public function test_correction_margin_percent(paper_format $format): void {
        $percent = $format->correction_margin_percent();
        $this->assertGreaterThan(0, $percent, "Paper format {$format->name} must have a positive correction margin.");
        $this->assertLessThan(100, $percent, "Paper format {$format->name} must have a correction margin below 100%.");
    }

    /**
     * Test data provider for test_correction_margin_percent.
     *
     * @return array<string, array{paper_format}> All paper formats.
     */
    public static function paper_formats_data_provider(): array {
        $res = [];

        foreach (paper_format::cases() as $format) {
            $res[$format->name] = [$format];
        }

        return $res;
    }
}
