<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the submission_report class
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;

use archivingmod_assign\local\type\submission_report_section;

/**
 * Tests for the submission_report class
 */
final class submission_report_test extends \advanced_testcase {
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
     * PHPUnit test setup hook.
     *
     * @return void
     * @throws \coding_exception
     */
    #[\Override]
    protected function setUp(): void {
        parent::setUp();

        // The mod_assign internals (e.g. assign::get_return_action()) access $PAGE->url and trigger a
        // debugging() call if it was never set. In production this is set by the calling webservice
        // (see generate_submission_report::execute()); tests need to set it themselves.
        global $PAGE;
        $PAGE->set_url(new \moodle_url('/'));
    }

    /**
     * Tests that a submission_report instance can be constructed with valid arguments.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_submission_report(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);

        $report = new submission_report($testdata->course, $testdata->cm, $assign);
        $this->assertInstanceOf(submission_report::class, $report);
    }

    /**
     * Tests that the constructor throws a moodle_exception when the course module
     * does not belong to the given course.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_submission_report_throws_for_mismatched_course(): void {
        $this->resetAfterTest();
        $testdata1 = $this::getDataGenerator()->create_assignment();
        $testdata2 = $this::getDataGenerator()->create_assignment();

        $ctx = \context_module::instance($testdata2->cm->id);
        $assign = new \assign($ctx, $testdata2->cm, $testdata2->course);

        $this->expectException(\moodle_exception::class);
        new submission_report($testdata1->course, $testdata2->cm, $assign);
    }

    /**
     * Tests that the constructor throws a moodle_exception when the assignment
     * instance does not match the course module instance.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_submission_report_throws_for_mismatched_assignment(): void {
        $this->resetAfterTest();
        $testdata1 = $this::getDataGenerator()->create_assignment();
        $testdata2 = $this::getDataGenerator()->create_assignment();

        $ctx1 = \context_module::instance($testdata1->cm->id);
        $ctx2 = \context_module::instance($testdata2->cm->id);
        $assign2 = new \assign($ctx2, $testdata2->cm, $testdata2->course);

        $this->expectException(\moodle_exception::class);
        new submission_report($testdata1->course, $testdata1->cm, $assign2);
    }

    /**
     * Tests that generate() includes key submission metadata in the rendered HTML.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_contains_expected_content(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);

        $report = new submission_report($testdata->course, $testdata->cm, $assign);
        $html = $report->generate($testdata->submission->id, submission_report_section::cases());

        $this->assertStringContainsString(
            $testdata->assignment->name,
            $html,
            'Assignment title not found in rendered HTML'
        );
        $this->assertStringContainsString(
            $testdata->course->fullname,
            $html,
            'Course name not found in rendered HTML'
        );
        $this->assertStringContainsString(
            (string) $testdata->submission->id,
            $html,
            'Submission ID not found in rendered HTML'
        );
        $this->assertStringContainsString(
            (string) $testdata->student->id,
            $html,
            'Student user ID not found in rendered HTML'
        );
    }

    /**
     * Tests that generate() throws a moodle_exception when the submission belongs
     * to a different assignment.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_throws_for_wrong_assignment(): void {
        $this->resetAfterTest();
        $testdata1 = $this::getDataGenerator()->create_assignment_with_text_submission();
        $testdata2 = $this::getDataGenerator()->create_assignment();

        $ctx = \context_module::instance($testdata2->cm->id);
        $assign = new \assign($ctx, $testdata2->cm, $testdata2->course);

        $report = new submission_report($testdata2->course, $testdata2->cm, $assign);

        $this->expectException(\moodle_exception::class);
        $report->generate($testdata1->submission->id, submission_report_section::cases());
    }

    /**
     * Tests that generate_full_page() returns non-empty HTML for a valid submission.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \DOMException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_full_page_stub(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);

        $report = new submission_report($testdata->course, $testdata->cm, $assign);
        $html = $report->generate_full_page(
            $testdata->submission->id,
            submission_report_section::cases(),
            false, // We need to disable this since $OUTPUT->header() is not working during tests.
            false, // We need to disable this since $OUTPUT->header() is not working during tests.
            true
        );

        $this->assertNotEmpty($html, 'Generated full page report is empty');
    }

    /**
     * Tests that generate_submission_filename() correctly substitutes known variables.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_generate_submission_filename_substitutes_variables(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $filename = $report->generate_submission_filename(
            $testdata->submission->id,
            'submission-${submissionid}-${username}-${assignmentid}',
            false
        );

        $this->assertSame(
            "submission-{$testdata->submission->id}-{$testdata->student->username}-{$testdata->assignment->id}",
            $filename
        );
    }

    /**
     * Tests that generate_submission_filename() preserves path separators when
     * generating a folder name.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_generate_submission_filename_as_foldername_preserves_path_separators(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $foldername = $report->generate_submission_filename(
            $testdata->submission->id,
            '${username}/${submissionid}',
            true
        );

        $this->assertSame(
            "{$testdata->student->username}/{$testdata->submission->id}",
            $foldername
        );
    }

    /**
     * Tests that generate_submission_filename() throws an invalid_parameter_exception
     * for a folder name pattern containing forbidden characters.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_submission_filename_throws_for_invalid_foldername_pattern(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $this->expectException(\invalid_parameter_exception::class);
        $report->generate_submission_filename($testdata->submission->id, '${username}*', true);
    }

    /**
     * Tests that generate_submission_filename() throws an invalid_parameter_exception
     * for a filename pattern containing forbidden characters (e.g. a path separator).
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_submission_filename_throws_for_invalid_filename_pattern(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $this->expectException(\invalid_parameter_exception::class);
        $report->generate_submission_filename($testdata->submission->id, '${username}/${submissionid}', false);
    }

    /**
     * Tests that generate_submission_filename() throws an invalid_parameter_exception
     * for a pattern referencing an unknown variable.
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_submission_filename_throws_for_unknown_variable(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $this->expectException(\invalid_parameter_exception::class);
        $report->generate_submission_filename($testdata->submission->id, '${notavariable}', false);
    }

    /**
     * Tests generation of a report without the assignment header section
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_header(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::ASSIGNMENT_HEADER,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            get_string('openingdate', 'archivingmod_assign'),
            $html,
            'Assignment header found when it should be absent'
        );
        $this->assertStringContainsString(
            get_string('submissionstatusheading', 'assign'),
            $html,
            'Submission status heading not found'
        );
    }

    /**
     * Tests generation of a report without the assignment instructions section
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_instructions(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::ASSIGNMENT_INSTRUCTIONS,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            get_string('activityeditor', 'mod_assign'),
            $html,
            'Assignment instructions found when it should be absent'
        );
        $this->assertStringContainsString(
            get_string('openingdate', 'archivingmod_assign'),
            $html,
            'Assignment header not found'
        );
    }

    /**
     * Tests generation of a report without the submission section
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_submission(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::SUBMISSION,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            get_string('submissionstatusheading', 'assign'),
            $html,
            'Submission status heading found when it should be absent'
        );
        $this->assertStringNotContainsString(
            'Test submission text.',
            $html,
            'Submission content found when it should be absent'
        );
        $this->assertStringNotContainsString(
            get_string('pluginname', 'assignsubmission_comments'),
            $html,
            'Submission comments found when it should be absent'
        );
    }

    /**
     * Tests generation of a report with the submission but without its status
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_submission_status(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::SUBMISSION_STATUS,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            get_string('submissionstatusheading', 'assign'),
            $html,
            'Submission status heading found when it should be absent'
        );
        $this->assertStringContainsString(
            'Test submission text.',
            $html,
            'Submission content not found'
        );
    }

    /**
     * Tests generation of a report with the submission but without its comments
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_submission_comments(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::SUBMISSION_COMMENTS,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            get_string('pluginname', 'assignsubmission_comments'),
            $html,
            'Submission comments found when it should be absent'
        );
        $this->assertStringContainsString(
            'Test submission text.',
            $html,
            'Submission content not found'
        );
        $this->assertStringContainsString(
            get_string('submissionstatusheading', 'assign'),
            $html,
            'Submission status heading not found'
        );
    }

    /**
     * Tests generation of a report without the feedback section
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_feedback(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();
        $this::getDataGenerator()->grade_submission($testdata);

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::FEEDBACK,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            get_string('feedback', 'assign'),
            $html,
            'Feedback section found when it should be absent'
        );
        $this->assertStringNotContainsString(
            '>' . get_string('gradenoun') . '<',
            $html,
            'Grade found when it should be absent'
        );
        $this->assertStringNotContainsString(
            get_string('gradedby', 'assign'),
            $html,
            'Grading details found when they should be absent'
        );
    }

    /**
     * Tests generation of a report with feedback but without feedback comments
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_feedback_comments(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();
        $this::getDataGenerator()->grade_submission($testdata);

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::FEEDBACK_COMMENTS,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            get_string('pluginname', 'assignfeedback_comments'),
            $html,
            'Feedback comments found when they should be absent'
        );
        $this->assertStringContainsString(
            get_string('feedback', 'assign'),
            $html,
            'Feedback section not found'
        );
        $this->assertStringContainsString(
            '>' . get_string('gradenoun') . '<',
            $html,
            'Grade not found'
        );
    }

    /**
     * Tests generation of a report with feedback but without the grade
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_grade(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();
        $this::getDataGenerator()->grade_submission($testdata);

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::GRADE,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            '>' . get_string('gradenoun') . '<',
            $html,
            'Grade found when it should be absent'
        );
        $this->assertStringContainsString(
            get_string('gradedby', 'assign'),
            $html,
            'Grading details not found'
        );
    }

    /**
     * Tests generation of a report with the grade but without grading details
     *
     * @covers \archivingmod_assign\submission_report
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_grading_details(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();
        $this::getDataGenerator()->grade_submission($testdata);

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $report = new submission_report($testdata->course, $testdata->cm, $assign);

        $sections = array_filter(submission_report_section::cases(), fn ($s) => !in_array($s, [
            submission_report_section::GRADING_DETAILS,
        ]));
        $html = $report->generate($testdata->submission->id, $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        $this->assertStringNotContainsString(
            get_string('gradedby', 'assign'),
            $html,
            'Grading details found when they should be absent'
        );
        $this->assertStringContainsString(
            '>' . get_string('gradenoun') . '<',
            $html,
            'Grade not found'
        );
    }
}
