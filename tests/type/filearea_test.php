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
 * Tests for the filearea class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the filearea class.
 */
final class filearea_test extends \advanced_testcase {
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
     * Tests all file areas for correct properties.
     *
     * @covers \local_archiving\type\filearea
     * @dataProvider case_properties_data_provider
     *
     * @param filearea $filearea The filearea to test
     * @param bool $isvirtual Expected isvirtual property
     * @return void
     */
    public function test_case_properties(filearea $filearea, bool $isvirtual): void {
        $this->assertSame(
            'local_archiving',
            $filearea->get_component(),
            "File area {$filearea->name} must have component 'local_archiving'."
        );
        $this->assertSame($isvirtual, $filearea->is_virtual(), "File area {$filearea->name} has wrong isvirtual property.");
    }

    /**
     * Test data provider for test_case_properties.
     *
     * @return array<int, array{filearea, bool}> All file areas with expected isvirtual property.
     */
    public static function case_properties_data_provider(): array {
        $res = [];

        foreach (filearea::cases() as $area) {
            $res[$area->name] = [$area, $area === filearea::TSP];
        }

        return $res;
    }
}
