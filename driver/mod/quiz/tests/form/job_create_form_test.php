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

namespace archivingmod_quiz\form;


use archivingmod_quiz\type\attempt_report_section;

/**
 * Tests for the job_create_form class
 *
 * @package   archivingmod_quiz
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

        // Ensure that the manual archiving trigger mock thinks it is enabled.
        set_config('enabled', true, 'archivingtrigger_manual');

        // Mock default storage driver.
        set_config('job_preset_storage_driver', 'localdir', 'local_archiving');
    }

    /**
     * Returns the data generator for the archivingmod_quiz plugin
     *
     * @return \archivingmod_quiz_generator The data generator for the archivingmod_quiz plugin
     */
    // phpcs:ignore
    public static function getDataGenerator(): \archivingmod_quiz_generator {
        return parent::getDataGenerator()->get_plugin_generator('archivingmod_quiz');
    }

    /**
     * Tests instantiating the form with valid parameters and checks that the definition works as expected.
     *
     * @covers \archivingmod_quiz\form\job_create_form
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_valid_definition(): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
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
     * @covers \archivingmod_quiz\form\job_create_form
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_export_defaults(): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Create the form and check that the definition works as expected.
        $form = new job_create_form('quiz', $cminfo);

        $defaults = $form->export_raw_data();
        $this->assertNotEmpty($defaults);
    }

    /**
     * Tests that form data is validated properly.
     *
     * @covers \archivingmod_quiz\form\job_create_form
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
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
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
                    'archive_filename_pattern' => 'archive-${cmid}', // For parent validator.
                    'attempt_foldername_pattern' => 'folder-${attemptid}',
                    'attempt_filename_pattern' => 'attempt-${attemptid}',
                ],
                true,
            ],
            'Invalid attempt_foldername_pattern' => [
                [
                    'archive_filename_pattern' => 'archive-${cmid}', // For parent validator.
                    'attempt_foldername_pattern' => 'folder-${invalidplaceholder}',
                    'attempt_filename_pattern' => 'attempt-${attemptid}',
                ],
                false,
            ],
            'Invalid attempt_filename_pattern' => [
                [
                    'archive_filename_pattern' => 'archive-${cmid}', // For parent validator.
                    'attempt_filename_pattern' => 'attempt-${invalidplaceholder}',
                    'attempt_foldername_pattern' => 'folder-${attemptid}',
                ],
                false,
            ],
        ];
    }

    /**
     * Tests that locked data fields can not be overridden via malicious POST data.
     *
     * @covers \archivingmod_quiz\form\job_create_form
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
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        set_config("job_preset_{$optionkey}", $lockedvalue, 'archivingmod_quiz');
        set_config("job_preset_{$optionkey}_locked", 1, 'archivingmod_quiz');

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
        $testcases = [];
        foreach (attempt_report_section::values() as $sectionname) {
            $testcases["Locked: report_section_{$sectionname}"] = [
                "report_section_{$sectionname}",
                1,
                0,
            ];
        }

        return array_merge(
            $testcases,
            [
                'Locked: export_attempts' => ['export_attempts', 1, 0],
                'Locked: paper_format' => ['paper_format', 'A3', 'letter'],
                'Locked: keep_html_files' => ['keep_html_files', 1, 0],
                'Locked: image_optimize' => ['image_optimize', 1, 0],
                'Locked: image_optimize_width' => ['image_optimize_width', 1024, 100],
                'Locked: image_optimize_height' => ['image_optimize_height', 768, 100],
                'Locked: image_optimize_quality' => ['image_optimize_quality', 85, 50],
                'Locked: attempt_foldername_pattern' => ['attempt_foldername_pattern', 'folder-${attemptid}', 'folder'],
                'Locked: attempt_filename_pattern' => ['attempt_filename_pattern', 'attempt-${attemptid}', 'attempt'],
            ]
        );
    }
}
