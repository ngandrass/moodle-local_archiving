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

namespace local_archiving\exception;


/**
 * Tests for the yield_exception.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the yield_exception.
 */
final class yield_exception_test extends \advanced_testcase {
    /**
     * Tests creation of a default yield_exception.
     *
     * @covers \local_archiving\exception\yield_exception
     *
     * @return void
     */
    public function test_yield_exception(): void {
        $exception = new yield_exception();
        $this->assertSame('yield', $exception->errorcode, 'Yield exception must have error code "yield" by default.');
        $this->assertSame('local_archiving', $exception->module, 'Yield exception must have module "local_archiving" by default.');
    }
}
