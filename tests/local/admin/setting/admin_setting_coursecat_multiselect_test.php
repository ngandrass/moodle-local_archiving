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

namespace local_archiving\local\admin\setting;

/**
 * Tests for the admin_setting_coursecat_multiselect class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the admin_setting_coursecat_multiselect class.
 */
final class admin_setting_coursecat_multiselect_test extends \advanced_testcase {
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
     * Tests that all course categories are present in the admin setting choices.
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_coursecat_multiselect
     *
     * @return void
     */
    public function test_definition(): void {
        // Prepare some course categories.
        $this->resetAfterTest();
        $cat1 = $this->generator()->create_category(['name' => 'Category 1']);
        $cat2 = $this->generator()->create_category(['name' => 'Category 2']);
        $cat3 = $this->generator()->create_category(['name' => 'Category 3']);
        $cat11 = $this->generator()->create_category(['name' => 'Category 1.1', 'parent' => $cat1->id]);
        $cat12 = $this->generator()->create_category(['name' => 'Category 1.2', 'parent' => $cat1->id]);
        $cat21 = $this->generator()->create_category(['name' => 'Category 2.1', 'parent' => $cat2->id]);
        $cat211 = $this->generator()->create_category(['name' => 'Category 2.1.1', 'parent' => $cat21->id]);

        // Get all categories and verify that the setting lists all.
        $cats = array_merge([\core_course_category::top()], \core_course_category::get_all());

        $setting = new admin_setting_coursecat_multiselect(
            'local_archiving/testsetting',
            'Test setting',
            'A test setting for course categories',
            false
        );

        $this->assertSameSize($cats, $setting->choices, 'The setting should list all course categories plus the top category.');
    }

    /**
     * Tests that an empty selection is detected correctly and valid values can be stored.
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_coursecat_multiselect
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_detecting_emtpy_selection(): void {
        // Prepare a course category to test.
        $this->resetAfterTest();
        $cat = $this->generator()->create_category(['name' => 'My Category']);

        // Prepare a setting instance.
        $setting = new admin_setting_coursecat_multiselect(
            'local_archiving/testsetting',
            'Test setting',
            'A test setting for course categories',
            allowempty: false
        );

        // Test writing an empty selection.
        $this->assertSame(
            get_string('error_at_least_one_coursecat_required', 'local_archiving'),
            $setting->write_setting([]),
            'An error should be returned when trying to save an empty selection if allowempty is false.'
        );
        $this->assertSame(
            get_string('error_at_least_one_coursecat_required', 'local_archiving'),
            $setting->write_setting(['xxxxx' => 1]),
            'An error should be returned when trying to save a placeholder selection if allowempty is false.'
        );

        // Test writing a non-empty selection.
        $this->assertSame(
            '',
            $setting->write_setting([$cat->id => $cat->id]),
            'No error should be returned when trying to save a non-empty selection'
        );
        $this->assertSame(
            $cat->id,
            get_config('local_archiving', 'testsetting'),
            'The setting value should be saved correctly when a non-empty selection is provided.'
        );
    }
}
