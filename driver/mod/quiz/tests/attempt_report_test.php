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
 * Tests for the attempt_report class
 *
 * @package   archivingmod_quiz
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;

// phpcs:ignore
global $CFG;

use archivingmod_quiz\type\attempt_filename_variable;
use archivingmod_quiz\type\attempt_report_section;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Tests for the attempt_report class
 */
final class attempt_report_test extends \advanced_testcase {
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
     * Test generation of a full attempt report with all sections
     *
     * @covers \archivingmod_quiz\attempt_report::__construct
     * @covers \archivingmod_quiz\attempt_report::generate
     *
     * @return void
     * @throws \DOMException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_full_report(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();

        // Generate full report with all sections.
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $html = $report->generate($rc->attemptids[0], attempt_report_section::cases());
        $this->assertNotEmpty($html, 'Generated report is empty');

        // Verify quiz header.
        $this->assertMatchesRegularExpression(
            '/<table[^<>]*quizreviewsummary[^<>]*>/',
            $html,
            'Quiz header table not found'
        );
        $this->assertMatchesRegularExpression(
            '/<td[^<>]*>' . preg_quote($rc->course->fullname, '/') . '[^<>]+<\/td>/',
            $html,
            'Course name not found'
        );
        $this->assertMatchesRegularExpression(
            '/<td[^<>]*>' . preg_quote($rc->quiz->name, '/') . '[^<>]+<\/td>/',
            $html,
            'Quiz name not found'
        );

        // Verify overall quiz feedback.
        // TODO (MDL-0): Add proper overall feedback to reference quiz and check its contents.
        $this->assertMatchesRegularExpression(
            '/<th[^<>]*>\s*' . preg_quote(get_string('feedback', 'quiz'), '/') . '\s*<\/th>/',
            $html,
            'Overall feedback header not found'
        );

        // Verify questions.
        foreach ($this->getDataGenerator()::QUESTION_TYPES_IN_REFERENCE_QUIZ as $qtype) {
            $this->assertMatchesRegularExpression(
                '/<[^<>]*class="[^"<>]*que[^"<>]*' . preg_quote($qtype, '/') . '[^"<>]*"[^<>]*>/',
                $html,
                'Question of type ' . $qtype . ' not found'
            );
        }

        // Verify individual question feedback.
        $this->assertMatchesRegularExpression(
            '/<div [^<>]*class="[^<>"]*specificfeedback[^<>"]*"[^<>]*>/',
            $html,
            'Individual question feedback not found'
        );

        // Verify general question feedback.
        $this->assertMatchesRegularExpression(
            '/<div [^<>]*class="[^<>"]*generalfeedback[^<>"]*"[^<>]*>/',
            $html,
            'General question feedback not found'
        );

        // Verify correct answers.
        $this->assertMatchesRegularExpression(
            '/<div [^<>]*class="[^<>"]*rightanswer[^<>"]*"[^<>]*>/',
            $html,
            'Correct question answers not found'
        );

        // Verify answer history.
        $this->assertMatchesRegularExpression(
            '/<[^<>]*class="responsehistoryheader[^<>"]*"[^<>]*>/',
            $html,
            'Answer history not found'
        );
    }

    /**
     * Tests generation of a full page report with all sections
     *
     * @covers \archivingmod_quiz\attempt_report::generate_full_page
     * @covers \archivingmod_quiz\attempt_report::convert_image_to_base64
     * @covers \archivingmod_quiz\attempt_report::ensure_absolute_url
     *
     * @return void
     * @throws \DOMException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_generate_full_page_stub(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $html = $report->generate_full_page(
            $rc->attemptids[0],
            attempt_report_section::cases(),
            false, // We need to disable this since $OUTPUT->header() is not working during tests.
            false, // We need to disable this since $OUTPUT->header() is not working during tests.
            true
        );
        $this->assertNotEmpty($html, 'Generated report is empty');
    }

    /**
     * Tests generation of a report with no header
     *
     * @covers \archivingmod_quiz\attempt_report::generate
     *
     * @throws \restore_controller_exception
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_generate_report_no_header(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();

        // Generate report without a header.
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $sections = array_filter(attempt_report_section::cases(), fn ($s) => !in_array($s, [
            attempt_report_section::HEADER,
        ]));
        $html = $report->generate($rc->attemptids[0], $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        // Verify that quiz header is absent.
        $this->assertDoesNotMatchRegularExpression(
            '/<table[^<>]*quizreviewsummary[^<>]*>/',
            $html,
            'Quiz header table found when it should be absent'
        );

        // If the quiz header is disabled, the quiz feedback should also be absent.
        $this->assertDoesNotMatchRegularExpression(
            '/<th[^<>]*>\s*' . preg_quote(get_string('feedback', 'quiz'), '/') . '\s*<\/th>/',
            $html,
            'Overall feedback header found when it should be absent'
        );
    }

    /**
     * Tests generation of a report with no quiz feedback
     *
     * @covers \archivingmod_quiz\attempt_report::generate
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_generate_report_no_quiz_feedback(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();

        // Generate report without quiz feedback.
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $sections = array_filter(attempt_report_section::cases(), fn ($s) => !in_array($s, [
            attempt_report_section::GENERAL_FEEDBACK,
            attempt_report_section::QUESTION,
        ]));
        $html = $report->generate($rc->attemptids[0], $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        // Verify that quiz feedback is absent.
        $this->assertMatchesRegularExpression(
            '/<table[^<>]*quizreviewsummary[^<>]*>/',
            $html,
            'Quiz header table not found'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<th[^<>]*>\s*' . preg_quote(get_string('feedback', 'quiz'), '/') . '\s*<\/th>/',
            $html,
            'Overall feedback header found when it should be absent'
        );
    }

    /**
     * Tests generation of a report with no questions
     *
     * @covers \archivingmod_quiz\attempt_report::generate
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_generate_report_no_questions(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();

        // Generate report without questions.
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $sections = array_filter(attempt_report_section::cases(), fn ($s) => !in_array($s, [
            attempt_report_section::QUESTION,
        ]));
        $html = $report->generate($rc->attemptids[0], $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        // Verify that no questions are present.
        $this->assertDoesNotMatchRegularExpression(
            '/<[^<>]*class="[^\"<>]*que[^<>]*>/',
            $html,
            'Question found when it should be absent'
        );

        // If questions are disabled, question_feedback, general_feedback, rightanswer and history should be absent.
        $this->assertDoesNotMatchRegularExpression(
            '/<div [^<>]*class="[^<>"]*specificfeedback[^<>"]*"[^<>]*>/',
            $html,
            'Individual question feedback found when it should be absent'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div [^<>]*class="[^<>"]*generalfeedback[^<>"]*"[^<>]*>/',
            $html,
            'General question feedback found when it should be absent'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div [^<>]*class="[^<>"]*rightanswer[^<>"]*"[^<>]*>/',
            $html,
            'Correct question answers found when they should be absent'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<[^<>]*class="responsehistoryheader[^<>"]*"[^<>]*>/',
            $html,
            'Answer history found when it should be absent'
        );
    }

    /**
     * Tests generation of a report with no individual question feedback
     *
     * @covers \archivingmod_quiz\attempt_report::generate
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_generate_report_no_question_feedback(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();

        // Generate report without question feedback.
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $sections = array_filter(attempt_report_section::cases(), fn ($s) => !in_array($s, [
            attempt_report_section::QUESTION_FEEDBACK,
        ]));
        $html = $report->generate($rc->attemptids[0], $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        // Verify that question feedback is absent.
        $this->assertDoesNotMatchRegularExpression(
            '/<div [^<>]*class="[^<>"]*specificfeedback[^<>"]*"[^<>]*>/',
            $html,
            'Individual question feedback found when it should be absent'
        );
    }

    /**
     * Tests generation of a report with no general question feedback
     *
     * @covers \archivingmod_quiz\attempt_report::generate
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_generate_report_no_general_feedback(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();

        // Generate report without general feedback.
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $sections = array_filter(attempt_report_section::cases(), fn ($s) => !in_array($s, [
            attempt_report_section::GENERAL_FEEDBACK,
        ]));
        $html = $report->generate($rc->attemptids[0], $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        // Verify that general feedback is absent.
        $this->assertDoesNotMatchRegularExpression(
            '/<div [^<>]*class="[^<>"]*generalfeedback[^<>"]*"[^<>]*>/',
            $html,
            'General question feedback found when it should be absent'
        );
    }

    /**
     * Tests generation of a report without showing correct answers for questions
     *
     * @covers \archivingmod_quiz\attempt_report::generate
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_generate_report_no_rightanswers(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();

        // Generate report without right answers.
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $sections = array_filter(attempt_report_section::cases(), fn ($s) => !in_array($s, [
            attempt_report_section::CORRECT_ANSWER,
        ]));
        $html = $report->generate($rc->attemptids[0], $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        // Verify that right answers are absent.
        $this->assertDoesNotMatchRegularExpression(
            '/<div [^<>]*class="[^<>"]*rightanswer[^<>"]*"[^<>]*>/',
            $html,
            'Correct question answers found when they should be absent'
        );
    }

    /**
     * Tests generation of a report without showing answer histories
     *
     * @covers \archivingmod_quiz\attempt_report::generate
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_generate_report_no_history(): void {
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();

        // Generate report without answer history.
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);
        $sections = array_filter(attempt_report_section::cases(), fn ($s) => !in_array($s, [
            attempt_report_section::ANSWER_HISTORY,
        ]));
        $html = $report->generate($rc->attemptids[0], $sections);
        $this->assertNotEmpty($html, 'Generated report is empty');

        // Verify that answer history is absent.
        $this->assertDoesNotMatchRegularExpression(
            '/<[^<>]*class="responsehistoryheader[^<>"]*"[^<>]*>/',
            $html,
            'Answer history found when it should be absent'
        );
    }

    /**
     * Test generation of valid attempt folder names
     *
     * @covers \archivingmod_quiz\attempt_report::generate_attempt_filename
     *
     * @return void
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_generate_attempt_foldername(): void {
        // Generate data.
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);

        // Full pattern.
        $fullpattern = 'attempt';
        foreach (attempt_filename_variable::values() as $var) {
            $fullpattern .= '-${' . $var . '}';
        }
        $foldername = $report->generate_attempt_filename(
            attemptid: $rc->attemptids[0],
            pattern: $fullpattern,
            isfoldername: true
        );
        $this->assertStringContainsString($rc->course->id, $foldername, 'Course ID was not found in folder name');
        $this->assertStringContainsString($rc->cm->id, $foldername, 'Course module ID was not found in folder name');
        $this->assertStringContainsString($rc->quiz->id, $foldername, 'Quiz ID was not found in folder name');
        $this->assertStringContainsString($rc->course->fullname, $foldername, 'Course name was not found in folder name');
        $this->assertStringContainsString($rc->course->shortname, $foldername, 'Course shortname was not found in folder name');
        $this->assertStringContainsString($rc->quiz->name, $foldername, 'Quiz name was not found in folder name');
        // TODO: (MDL-0) Update reference course to cover groups and check for these.
        $this->assertStringContainsString('nogroup', $foldername, 'Group name placeholder was not found in folder name');
        $this->assertStringContainsString($rc->attemptids[0], $foldername, 'Attempt ID was not found in folder name');

        // Check that no unsubstituted variables are left.
        foreach (attempt_filename_variable::values() as $var) {
            $this->assertStringNotContainsString(
                '${' . $var . '}',
                $foldername,
                "Unsubstituted variable '{$var}' found in folder name"
            );
        }
    }

    /**
     * Test generation of attempt folder names without variables
     *
     * @covers \archivingmod_quiz\attempt_report::generate_attempt_filename
     *
     * @return void
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_generate_attempt_foldername_without_variables(): void {
        // Generate data.
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);

        $foldername = $report->generate_attempt_filename(
            attemptid: $rc->attemptids[0],
            pattern: 'attempt',
            isfoldername: true
        );
        $this->assertSame('attempt', $foldername, 'Folder name was not generated correctly');
    }

    /**
     * Test generation of attempt folder names with invalid patterns
     *
     * @covers \archivingmod_quiz\attempt_report::generate_attempt_filename
     *
     * @return void
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_generate_attempt_foldername_invalid_pattern(): void {
        // Generate data.
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);

        // Test filename generation.
        $this->expectException(\invalid_parameter_exception::class);
        $report->generate_attempt_filename(
            attemptid: $rc->attemptids[0],
            pattern: '.',
            isfoldername: true
        );
    }

    /**
     * Test generation of attempt folder names with invalid variables
     *
     * @covers \archivingmod_quiz\attempt_report::generate_attempt_filename
     *
     * @return void
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_generate_attempt_foldername_invalid_variables(): void {
        // Generate data.
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);

        // Test filename generation.
        $this->expectException(\invalid_parameter_exception::class);
        $report->generate_attempt_filename(
            attemptid: $rc->attemptids[0],
            pattern: 'attempt-${foo}${bar}${baz}${courseid}',
            isfoldername: true
        );
    }

    /**
     * Test generation of valid attempt filenames
     *
     * @covers \archivingmod_quiz\attempt_report::generate_attempt_filename
     *
     * @return void
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_generate_attempt_filename(): void {
        // Generate data.
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);

        // Full pattern.
        $fullpattern = 'attempt';
        foreach (attempt_filename_variable::values() as $var) {
            $fullpattern .= '-${' . $var . '}';
        }
        $filename = $report->generate_attempt_filename(
            attemptid: $rc->attemptids[0],
            pattern: $fullpattern,
            isfoldername: false
        );
        $this->assertStringContainsString($rc->course->id, $filename, 'Course ID was not found in filename');
        $this->assertStringContainsString($rc->cm->id, $filename, 'Course module ID was not found in filename');
        $this->assertStringContainsString($rc->quiz->id, $filename, 'Quiz ID was not found in filename');
        $this->assertStringContainsString($rc->course->fullname, $filename, 'Course name was not found in filename');
        $this->assertStringContainsString($rc->course->shortname, $filename, 'Course shortname was not found in filename');
        $this->assertStringContainsString($rc->quiz->name, $filename, 'Quiz name was not found in filename');
        // TODO: (MDL-0) Update reference course to cover groups and check for these.
        $this->assertStringContainsString('nogroup', $filename, 'Group name placeholder was not found in filename');
        $this->assertStringContainsString($rc->attemptids[0], $filename, 'Attempt ID was not found in filename');

        // Check that no unsubstituted variables are left.
        foreach (attempt_filename_variable::values() as $var) {
            $this->assertStringNotContainsString(
                '${' . $var . '}',
                $filename,
                "Unsubstituted variable '{$var}' found in filename"
            );
        }
    }

    /**
     * Test generation of attempt filenames without variables
     *
     * @covers \archivingmod_quiz\attempt_report::generate_attempt_filename
     *
     * @return void
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_generate_attempt_filename_without_variables(): void {
        // Generate data.
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);

        $filename = $report->generate_attempt_filename(
            attemptid: $rc->attemptids[0],
            pattern: 'attempt',
            isfoldername: false
        );
        $this->assertSame('attempt', $filename, 'Filename was not generated correctly');
    }

    /**
     * Test generation of attempt filenames with invalid patterns
     *
     * @covers \archivingmod_quiz\attempt_report::generate_attempt_filename
     *
     * @return void
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_generate_attempt_filename_invalid_pattern(): void {
        // Generate data.
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);

        // Test filename generation.
        $this->expectException(\invalid_parameter_exception::class);
        $report->generate_attempt_filename(
            attemptid: $rc->attemptids[0],
            pattern: '.',
            isfoldername: false
        );
    }

    /**
     * Test generation of attempt filenames with invalid variables
     *
     * @covers \archivingmod_quiz\attempt_report::generate_attempt_filename
     *
     * @return void
     * @throws \coding_exception
     * @throws \invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_generate_attempt_filename_invalid_variables(): void {
        // Generate data.
        $this->resetAfterTest();
        $rc = $this->getDataGenerator()->import_reference_course();
        $report = new attempt_report($rc->course, $rc->cm, $rc->quiz);

        // Test filename generation.
        $this->expectException(\invalid_parameter_exception::class);
        $report->generate_attempt_filename(
            attemptid: $rc->attemptids[0],
            pattern: 'attempt-${foo}${bar}${baz}${courseid}',
            isfoldername: false
        );
    }
}
