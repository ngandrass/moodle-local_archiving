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

namespace local\admin\setting;

use local_archiving\local\admin\setting\admin_setting_webservice_enabler;

/**
 * Tests for the admin_setting_webservice_enabler class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the admin_setting_webservice_enabler class.
 */
final class admin_setting_webservice_enabler_test extends \advanced_testcase {
    /**
     * Tests that the web service enabler component can be rendered
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_webservice_enabler
     *
     * @return void
     * @throws \moodle_exception
     * @throws \dml_exception
     */
    public function test_definition(): void {
        $setting = new admin_setting_webservice_enabler(
            'local_archiving/webserviceenablertest',
            'Test web service enabler',
            'A test instance of the web service enabler component',
        );

        $this->assertNotEmpty($setting->output_html(''));
    }
}
