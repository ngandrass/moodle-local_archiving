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

namespace archivingtrigger_manual;


/**
 * Tests for the archivingtrigger_manual implementation.
 *
 * @package   archivingtrigger_manual
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingtrigger_manual implementation.
 */
final class archivingtrigger_test extends \advanced_testcase {
    /**
     * Just a plain test to ensure nothing breaks when instantiating this trigger.
     *
     * @covers \archivingtrigger_manual\archivingtrigger
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_instantiation(): void {
        $trigger = new archivingtrigger();
        $this->assertSame('archivingtrigger', $trigger->get_plugin_type());
        $this->assertSame('manual', $trigger->get_plugin_name());
    }
}
