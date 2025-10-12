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

namespace local_archiving\form;


use local_archiving\util\course_util;

/**
 * Tests for the job_create_form class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for the job_create_form class
 */
final class job_create_form_test extends \advanced_testcase {
    /**
     * This method is called before each test.
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $PAGE->set_url('/');
    }

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
     * Tests instantiating the form with valid parameters and checks that the definition works as expected.
     *
     * @covers \local_archiving\form\job_create_form
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_valid_definition(): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);

        // Create the form and check that the definition works as expected.
        $form = new job_create_form('quiz', $cminfo);

        $html = $form->render();
        $this->assertStringContainsString(
            get_string('pluginname', 'mod_quiz'),
            $html,
            'The form must contain the module name title.'
        );
        $this->assertStringContainsString('type="submit"', $html, 'The form must contain a submit button.');
    }

    /**
     * Tests that the form allows exporting all default values directly after
     * definition without any errors.
     *
     * @covers \local_archiving\form\job_create_form
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_export_defaults(): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Create the form and check that the definition works as expected.
        $form = new job_create_form('quiz', $cminfo);

        $defaults = $form->export_raw_data();
        $this->assertNotEmpty($defaults);
    }

    /**
     * Tests that warnings are displayed when archiving is disabled for a course and the submit button is hidden.
     *
     * @covers \local_archiving\form\job_create_form
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_course_archiving_disabled_detection(): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Create a new course category and limit archiving to this category.
        $category = self::getDataGenerator()->create_category();
        set_config('coursecat_whitelist', $category->id, 'local_archiving');
        $this->assertFalse(course_util::archiving_enabled_for_course($course->id), 'Archiving must be disabled for the course.');

        // Create the form and check that only a warning is displayed.
        $form = new job_create_form('quiz', $cminfo);
        $html = $form->render();
        $this->assertStringContainsString(
            get_string('archiving_disabled_for_this_course_by_category', 'local_archiving'),
            $html,
            'The form must contain a warning that archiving is disabled.'
        );
        $this->assertStringNotContainsString(
            'type="submit"',
            $html,
            'The form must remove the submit button if archiving is disabled for course.'
        );

        // Make user privileged, allowing archiving but displaying a warning message.
        $this->setAdminUser();
        $form = new job_create_form('quiz', $cminfo);
        $html = $form->render();
        $this->assertStringContainsString(
            get_string('archiving_force_allowed_for_course', 'local_archiving'),
            $html,
            'The form must contain a warning that archiving is disabled but can be forced.'
        );
        $this->assertStringContainsString(
            'type="submit"',
            $html,
            'The form must contain a submit button if user is allowed to bypass archiving whitelist.'
        );
    }

    /**
     * Tests that the archive job creation form is blocked when the manual archiving trigger is disabled.
     *
     * @covers \local_archiving\form\job_create_form
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_manual_archiving_disabled_detection(): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Disable manual archiving.
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('archivingtrigger_manual');
        $plugininfo::enable_plugin('manual', 0);
        $this->assertFalse($plugininfo->is_enabled(), 'The manual archiving trigger must be disabled now.');

        // Create the form and check that a warning is displayed and the submit button is removed.
        $form = new job_create_form('quiz', $cminfo);
        $html = $form->render();

        $this->assertStringContainsString(
            get_string('can_not_create_archive_manual_archiving_disabled', 'local_archiving'),
            $html,
            'The form must contain a warning that manual archiving is disabled.'
        );
        $this->assertStringNotContainsString(
            'type="submit"',
            $html,
            'The form must remove the submit button if manual archiving is disabled.'
        );

        // Re-enable manual archiving and confirm that the form is displayed as intended.
        $plugininfo::enable_plugin('manual', 1);
        $this->assertTrue($plugininfo->is_enabled(), 'The manual archiving trigger must be enabled now.');

        $form = new job_create_form('quiz', $cminfo);
        $html = $form->render();

        $this->assertStringNotContainsString(
            get_string('can_not_create_archive_manual_archiving_disabled', 'local_archiving'),
            $html,
            'The form must not contain a warning that manual archiving is disabled.'
        );
        $this->assertStringContainsString(
            'type="submit"',
            $html,
            'The form must contain a submit button if manual archiving is enabled.'
        );
    }

    /**
     * Tests that form data is validated properly.
     *
     * @covers \local_archiving\form\job_create_form
     * @dataProvider form_data_validation_data_provider
     *
     * @param array $formdata
     * @param bool $isvalid
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_form_data_validation(array $formdata, bool $isvalid): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Create the form and submit the data.
        $form = new job_create_form('quiz', $cminfo);
        $res = $form->validation($formdata, []);

        if ($isvalid) {
            $this->assertEmpty($res, 'The form data must be considered valid.');
        } else {
            $this->assertNotEmpty($res, 'The form data for must be considered invalid.');
        }
    }

    /**
     * Data provider for test_form_data_validation
     *
     * @return array[] Test data
     */
    public static function form_data_validation_data_provider(): array {
        return [
            'Valid data' => [
                [
                    'archive_filename_pattern' => 'archive-${courseshortname}',
                ],
                true,
            ],
            'Invalid archive_filename_pattern' => [
                [
                    'archive_filename_pattern' => 'archive-${invalidplaceholder}',
                ],
                false,
            ],
        ];
    }

    /**
     * Tests that locked data fields can not be overridden via malicious POST data.
     *
     * @covers \local_archiving\form\job_create_form
     * @dataProvider locked_data_is_immutable_data_provider
     *
     * @param string $optionkey Key of the locked config option
     * @param mixed $lockedvalue Value to which the option is locked
     * @param mixed $postedvalue Malicious value that is attempted to be set via form POST data
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_locked_data_is_immutable(string $optionkey, mixed $lockedvalue, mixed $postedvalue): void {
        // Prepare a course module and lock the respective config option.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        set_config("job_preset_{$optionkey}", $lockedvalue, 'local_archiving');
        set_config("job_preset_{$optionkey}_locked", 1, 'local_archiving');

        // Prepare malicious POST data and create the form.
        $validpostdata = json_decode(
            file_get_contents(__DIR__ . '/../fixtures/job_create_form_request_valid.json'),
            true
        );
        foreach ($validpostdata as $key => $value) {
            $_POST[$key] = $value;
        }
        $_POST[$optionkey] = $postedvalue;
        $_POST['sesskey'] = sesskey();

        $form = new job_create_form('quiz', $cminfo);

        // Verify that the form data contains the locked value, not the malicious one.
        $formdata = $form->get_data();
        $this->assertNotFalse($formdata, 'Form data must be returned.');
        $this->assertEquals($lockedvalue, $formdata->{$optionkey}, "The option {$optionkey} must contain the locked value.");
    }

    /**
     * Data provider for test_locked_data_is_immutable
     *
     * @return array Test data
     */
    public static function locked_data_is_immutable_data_provider(): array {
        return [
            'Locked: export_cm_backup' => ['export_cm_backup', 1, 0],
            'Locked: export_course_backup' => ['export_course_backup', 0, 1],
            'Locked: storage_driver' => ['storage_driver', 'local', 'ftp'],
            'Locked: archive_filename_pattern' => ['archive_filename_pattern', 'archive-${courseid}', 'archive-changedvalue'],
            'Locked: archive_autodelete' => ['archive_autodelete', 1, 0],
            'Locked: archive_retention_time' => ['archive_retention_time', 3600, 1],
        ];
    }
}
