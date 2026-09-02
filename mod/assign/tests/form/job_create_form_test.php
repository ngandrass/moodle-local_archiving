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

namespace archivingmod_assign\form;


use archivingmod_assign\local\type\submission_report_section;

/**
 * Tests for the job_create_form class
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
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
     * Returns the data generator for the archivingmod_assign plugin
     *
     * @return \archivingmod_assign_generator The data generator for the archivingmod_assign plugin
     */
    // phpcs:ignore
    public static function getDataGenerator(): \archivingmod_assign_generator {
        return parent::getDataGenerator()->get_plugin_generator('archivingmod_assign');
    }

    /**
     * Tests instantiating the form with valid parameters and checks that the definition works as expected.
     *
     * @covers \archivingmod_assign\form\job_create_form
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
        $cm = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);

        // Create the form and check that the definition works as expected.
        $form = new job_create_form('assign', $cminfo);

        $html = $form->render();
        $this->assertStringContainsString(
            get_string('pluginname', 'mod_assign'),
            $html,
            'The form must contain the module name title.'
        );
        $this->assertStringContainsString('type="submit"', $html, 'The form must contain a submit button.');
    }

    /**
     * Tests that the form allows exporting all default values directly after
     * definition without any errors.
     *
     * @covers \archivingmod_assign\form\job_create_form
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_export_defaults(): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Create the form and check that the definition works as expected.
        $form = new job_create_form('assign', $cminfo);

        $defaults = $form->export_raw_data();
        $this->assertNotEmpty($defaults);
    }

    /**
     * Tests that form data is validated properly.
     *
     * @covers \archivingmod_assign\form\job_create_form
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
        $cm = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Create the form and submit the data.
        $form = new job_create_form('assign', $cminfo);
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
                    'submission_foldername_pattern' => 'folder-${submissionid}',
                    'submission_filename_pattern' => 'submission-${submissionid}',
                ],
                true,
            ],
            'Invalid submission_foldername_pattern' => [
                [
                    'archive_filename_pattern' => 'archive-${cmid}', // For parent validator.
                    'submission_foldername_pattern' => 'folder-${invalidplaceholder}',
                    'submission_filename_pattern' => 'submission-${submissionid}',
                ],
                false,
            ],
            'Invalid submission_filename_pattern' => [
                [
                    'archive_filename_pattern' => 'archive-${cmid}', // For parent validator.
                    'submission_filename_pattern' => 'submission-${invalidplaceholder}',
                    'submission_foldername_pattern' => 'folder-${submissionid}',
                ],
                false,
            ],
        ];
    }

    /**
     * Tests that locked data fields can not be overridden via malicious POST data.
     *
     * @covers \archivingmod_assign\form\job_create_form
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
        $cm = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        set_config("job_preset_{$optionkey}", $lockedvalue, 'archivingmod_assign');
        set_config("job_preset_{$optionkey}_locked", 1, 'archivingmod_assign');

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

        $form = new job_create_form('assign', $cminfo);

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
        foreach (submission_report_section::values() as $sectionname) {
            $testcases["Locked: report_section_{$sectionname}"] = [
                "report_section_{$sectionname}",
                1,
                0,
            ];
        }

        return array_merge(
            $testcases,
            [
                'Locked: paper_format' => ['paper_format', 'A3', 'letter'],
                'Locked: keep_html_files' => ['keep_html_files', 1, 0],
                'Locked: image_optimize' => ['image_optimize', 1, 0],
                'Locked: image_optimize_width' => ['image_optimize_width', 1024, 100],
                'Locked: image_optimize_height' => ['image_optimize_height', 768, 100],
                'Locked: image_optimize_quality' => ['image_optimize_quality', 85, 50],
                'Locked: submission_foldername_pattern' => [
                    'submission_foldername_pattern',
                    'folder-${submissionid}',
                    'folder',
                ],
                'Locked: submission_filename_pattern' => [
                    'submission_filename_pattern',
                    'submission-${submissionid}',
                    'submission',
                ],
            ]
        );
    }

    /**
     * Tests that a report section is forced disabled if one of its dependencies
     * is not enabled, even if it was posted as enabled.
     *
     * @covers \archivingmod_assign\form\job_create_form::get_data
     * @dataProvider dependent_sections_are_disabled_data_provider
     *
     * @param string $sectionname Value of the section that has dependencies
     * @param string $dependencyname Value of one of that section's dependencies
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_dependent_sections_are_disabled_when_dependency_is_unchecked(
        string $sectionname,
        string $dependencyname
    ): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Prepare POST data: section checked, its dependency unchecked.
        $validpostdata = json_decode(
            file_get_contents(__DIR__ . '/../fixtures/job_create_form_request_valid.json'),
            true
        );
        foreach ($validpostdata as $key => $value) {
            $_POST[$key] = $value;
        }
        $_POST["report_section_{$sectionname}"] = 1;
        $_POST["report_section_{$dependencyname}"] = 0;
        $_POST['sesskey'] = sesskey();

        $form = new job_create_form('assign', $cminfo);

        // Verify that the dependent section was forced disabled.
        $formdata = $form->get_data();
        $this->assertNotFalse($formdata, 'Form data must be returned.');
        $this->assertEquals(
            0,
            $formdata->{"report_section_{$sectionname}"},
            "The section {$sectionname} must be disabled since its dependency {$dependencyname} is disabled."
        );
    }

    /**
     * Data provider for test_dependent_sections_are_disabled_when_dependency_is_unchecked
     *
     * @return array Test data
     */
    public static function dependent_sections_are_disabled_data_provider(): array {
        $testcases = [];
        foreach (submission_report_section::cases() as $section) {
            foreach ($section->dependencies() as $dependency) {
                $testcases["{$section->value} depends on {$dependency->value}"] = [
                    $section->value,
                    $dependency->value,
                ];
            }
        }
        return $testcases;
    }

    /**
     * Tests that all sections depending, directly or indirectly, on a common
     * disabled section are forced disabled together in a single get_data() call.
     *
     * GRADING_DETAILS depends on both FEEDBACK and GRADE, and GRADE itself
     * depends on FEEDBACK. This posts GRADING_DETAILS and GRADE as checked,
     * but FEEDBACK as unchecked, and expects both dependents to end up
     * disabled.
     *
     * @covers \archivingmod_assign\form\job_create_form::get_data
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_transitively_dependent_section_is_disabled(): void {
        // Prepare a course module.
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($cm->cmid);

        // Prepare POST data: GRADING_DETAILS and GRADE checked, but FEEDBACK unchecked.
        $validpostdata = json_decode(
            file_get_contents(__DIR__ . '/../fixtures/job_create_form_request_valid.json'),
            true
        );
        foreach ($validpostdata as $key => $value) {
            $_POST[$key] = $value;
        }
        $_POST['report_section_gradedetails'] = 1;
        $_POST['report_section_grade'] = 1;
        $_POST['report_section_feedback'] = 0;
        $_POST['sesskey'] = sesskey();

        $form = new job_create_form('assign', $cminfo);

        // Verify that both GRADING_DETAILS and GRADE were forced disabled.
        $formdata = $form->get_data();
        $this->assertNotFalse($formdata, 'Form data must be returned.');
        $this->assertEquals(
            0,
            $formdata->report_section_gradedetails,
            'GRADING_DETAILS must be disabled since its dependency FEEDBACK is disabled.'
        );
        $this->assertEquals(
            0,
            $formdata->report_section_grade,
            'GRADE must be disabled since its dependency FEEDBACK is disabled.'
        );
    }
}
