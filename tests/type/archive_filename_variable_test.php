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
 * Tests for the archive_filename_variable class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archive_filename_variable class.
 */
final class archive_filename_variable_test extends \advanced_testcase {
    /**
     * Tests convenience access methods for retrieving enum cases by key or value as plain arrays.
     *
     * @covers \local_archiving\type\archive_filename_variable
     * @covers \local_archiving\trait\enum_listable
     *
     * @return void
     */
    public function test_listing(): void {
        // Get values and keys as retrieved by convenience functions of the enum_listable trait.
        $values = archive_filename_variable::values();
        $keys = archive_filename_variable::keys();

        // Check that keys and values are returned properly.
        $this->assertEqualsCanonicalizing(
            array_map(fn($c) => $c->name, archive_filename_variable::cases()),
            $keys,
            'The names of the enum cases must match the keys returned by keys() and the names returned by values().'
        );
        $this->assertEqualsCanonicalizing(
            array_map(fn($c) => $c->value, archive_filename_variable::cases()),
            $values,
            'The values of the enum cases must match the values returned by values().'
        );
    }
}
