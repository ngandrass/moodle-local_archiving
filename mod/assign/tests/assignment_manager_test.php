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
 * Tests for the assignment_manager class
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;


use archivingmod_assign\local\type\attachment_type;

/**
 * Tests for the assignment_manager class
 */
final class assignment_manager_test extends \advanced_testcase {
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
     * Tests creating a new assignment manager instance from an existing Moodle context.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_create_assignment_manager(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $assignman = assignment_manager::from_context(\context_module::instance($testdata->cm->id));
        $this->assertSame($testdata->course->id, $assignman->get_course()->id, 'Course ID does not match');
        $this->assertSame($testdata->cm->id, $assignman->get_cm()->id, 'Course module ID does not match');
        $this->assertSame(
            $testdata->assignment->id,
            $assignman->get_assignment()->get_instance()->id,
            'Assignment ID does not match'
        );
    }

    /**
     * Tests that the constructor throws a moodle_exception when the given courseid does not match
     * the course the course module belongs to.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_assignment_manager_mismatched_courseid(): void {
        $this->resetAfterTest();
        $testdata1 = $this::getDataGenerator()->create_assignment();
        $testdata2 = $this::getDataGenerator()->create_assignment();

        $this->expectException(\moodle_exception::class);
        new assignment_manager($testdata1->course->id, $testdata2->cm->id);
    }

    /**
     * Tests that from_context() creates a manager from a Moodle module context.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_from_context(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $ctx = \context_module::instance($testdata->cm->id);
        $assignman = assignment_manager::from_context($ctx);

        $this->assertSame($testdata->course->id, $assignman->get_course()->id);
        $this->assertSame($testdata->cm->id, $assignman->get_cm()->id);
        $this->assertSame($testdata->assignment->id, $assignman->get_assignment()->get_instance()->id);
    }

    /**
     * Tests that submission_report() returns a submission_report instance for this assignment.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_submission_report(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertInstanceOf(submission_report::class, $assignman->submission_report());
    }

    /**
     * Tests that get_assignment() returns the correct assign object.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_assignment(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $assign = $assignman->get_assignment();

        $this->assertSame($testdata->assignment->id, $assign->get_instance()->id);
    }

    /**
     * Tests that get_cm() returns the correct course module info object.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_cm(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $cm = $assignman->get_cm();

        $this->assertSame($testdata->cm->id, $cm->id);
    }

    /**
     * Tests that get_course() returns the correct course object.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_course(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $course = $assignman->get_course();

        $this->assertSame($testdata->course->id, $course->id);
    }

    /**
     * Tests that get_submissions() returns an empty array when no submissions exist.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submissions_empty(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertSame([], $assignman->get_submissions());
    }

    /**
     * Tests that get_submissions() returns the correct submission data when submissions exist.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submissions_with_submission(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $submissions = $assignman->get_submissions();

        $this->assertCount(1, $submissions);
        $submission = reset($submissions);
        $this->assertEquals($testdata->submission->id, $submission->submissionid);
        $this->assertEquals($testdata->student->id, $submission->userid);
    }

    /**
     * Tests that get_submissions() only returns the submissions targeted by the given refids.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submissions_by_refids(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        // Add a submission of a second student.
        $student2 = $this::getDataGenerator()->create_and_enrol($testdata->course, 'student');
        /** @var \mod_assign_generator $assigngen */
        $assigngen = $this::getDataGenerator()->get_plugin_generator('mod_assign');
        $assigngen->create_submission([
            'cmid' => $testdata->cm->id,
            'userid' => $student2->id,
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'onlinetext' => 'Second submission text.',
        ]);

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertCount(2, $assignman->get_submissions(), 'Expected two submissions without refids');

        // Target a single submission as well as a submission ID that does not exist.
        $submissions = $assignman->get_submissions([$testdata->submission->id, -1]);
        $this->assertCount(1, $submissions, 'Only the targeted submission should be returned');
        $this->assertEquals($testdata->submission->id, reset($submissions)->submissionid);

        // Refids that resolve to no submission yield an empty result.
        $this->assertSame([], $assignman->get_submissions([-1, -2]), 'Invalid refids should not resolve to any submission');

        // An empty list of refids is invalid.
        $this->expectException(\coding_exception::class);
        $assignman->get_submissions([]);
    }

    /**
     * Tests that submission_exists() returns true for a submission that exists inside this assignment.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_submission_exists_returns_true(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertTrue($assignman->submission_exists($testdata->submission->id));
    }

    /**
     * Tests that submission_exists() returns false for a submission ID that does not exist.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_submission_exists_returns_false_for_nonexistent_id(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertFalse($assignman->submission_exists(PHP_INT_MAX));
    }

    /**
     * Tests that submission_exists() returns false for a submission that belongs to a different assignment.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_submission_exists_returns_false_for_other_assignment(): void {
        $this->resetAfterTest();
        $testdata1 = $this::getDataGenerator()->create_assignment_with_text_submission();
        $testdata2 = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata2->course->id, $testdata2->cm->id);
        $this->assertFalse($assignman->submission_exists($testdata1->submission->id));
    }

    /**
     * Tests that get_submissions_metadata() returns an empty array when no submissions exist.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submissions_metadata_empty(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertSame([], $assignman->get_submissions_metadata());
    }

    /**
     * Tests that get_submissions_metadata() returns the correct metadata fields for a submission.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submissions_metadata_returns_correct_fields(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $metadata = $assignman->get_submissions_metadata();

        $this->assertCount(1, $metadata);
        $record = reset($metadata);

        $this->assertEquals($testdata->submission->id, $record->submissionid);
        $this->assertEquals($testdata->student->id, $record->userid);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_SUBMITTED, $record->status);
        $this->assertEquals($testdata->student->username, $record->username);
        $this->assertEquals($testdata->student->firstname, $record->firstname);
        $this->assertEquals($testdata->student->lastname, $record->lastname);
        $this->assertEquals($testdata->student->email, $record->email);
        $this->assertEquals($testdata->student->idnumber, $record->idnumber);
    }

    /**
     * Tests that get_submissions_metadata() with a submission ID filter returns only the matching submission.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submissions_metadata_filter_returns_matching_submission(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $metadata = $assignman->get_submissions_metadata([$testdata->submission->id]);

        $this->assertCount(1, $metadata);
        $record = reset($metadata);
        $this->assertEquals($testdata->submission->id, $record->submissionid);
    }

    /**
     * Tests that get_submissions_metadata() with a non-matching submission ID filter returns an empty array.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submissions_metadata_filter_excludes_non_matching_ids(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertEmpty($assignman->get_submissions_metadata([PHP_INT_MAX]));
    }

    /**
     * Tests that get_submissions_metadata() does not return submissions from other assignments.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submissions_metadata_excludes_other_assignment_submissions(): void {
        $this->resetAfterTest();
        $testdata1 = $this::getDataGenerator()->create_assignment_with_text_submission();
        $testdata2 = $this::getDataGenerator()->create_assignment();

        $assignman = new assignment_manager($testdata2->course->id, $testdata2->cm->id);
        $this->assertEmpty($assignman->get_submissions_metadata());
    }

    /**
     * Tests that get_submission_attachments_metadata() returns an empty array for a text-only submission.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_attachments_metadata_no_file_attachments(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertEmpty($assignman->get_submission_attachments_metadata($testdata->submission->id));
    }

    /**
     * Tests that get_submission_attachments_metadata() returns STUDENT_FILE_SUBMISSION entries for
     * file submissions.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_attachments_metadata_student_file_submission(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_submission(
            submissiondata: ['file' => $this::getDataGenerator()::get_fixture_file_path('submissionsample01.txt')],
            onlinetextenabled: false,
            filesubmissionenabled: true,
        );

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $attachments = $assignman->get_submission_attachments_metadata($testdata->submission->id);

        $this->assertCount(1, $attachments);
        $this->assertEquals(attachment_type::STUDENT_FILE_SUBMISSION->value, $attachments[0]['type']);
        $this->assertEquals('submissionsample01.txt', $attachments[0]['filename']);
        $this->assertArrayHasKey('filesize', $attachments[0]);
        $this->assertArrayHasKey('mimetype', $attachments[0]);
        $this->assertArrayHasKey('contenthash', $attachments[0]);
        $this->assertArrayHasKey('downloadurl', $attachments[0]);
    }

    /**
     * Tests that get_submission_attachments_metadata() returns a PROVIDED_ASSIGNMENT_FILE entry
     * when an intro attachment is present.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_attachments_metadata_provided_assignment_file(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $this::getDataGenerator()->add_intro_attachment($assign, 'intro_attachment.pdf');

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $attachments = $assignman->get_submission_attachments_metadata($testdata->submission->id);

        $providedfiles = array_values(array_filter(
            $attachments,
            fn($a) => $a['type'] === attachment_type::PROVIDED_ASSIGNMENT_FILE->value
        ));
        $this->assertCount(1, $providedfiles);
        $this->assertEquals('intro_attachment.pdf', $providedfiles[0]['filename']);
    }

    /**
     * Tests that get_submission_attachments_metadata() returns a GRADER_FEEDBACK_FILE entry
     * when a grader feedback file is present.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_attachments_metadata_grader_feedback_file(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $this::getDataGenerator()->add_grader_feedback_file($assign, $testdata->student->id, 'feedback.pdf');

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $attachments = $assignman->get_submission_attachments_metadata($testdata->submission->id);

        $feedbackfiles = array_values(array_filter(
            $attachments,
            fn($a) => $a['type'] === attachment_type::GRADER_FEEDBACK_FILE->value
        ));
        $this->assertCount(1, $feedbackfiles);
        $this->assertEquals('feedback.pdf', $feedbackfiles[0]['filename']);
    }

    /**
     * Tests that get_submission_attachments_metadata() returns a GRADER_ANNOTATED_FILE entry
     * when a grader annotated PDF is present.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_attachments_metadata_grader_annotated_file(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $this::getDataGenerator()->add_grader_annotated_file($assign, $testdata->student->id, 'annotated.pdf');

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $attachments = $assignman->get_submission_attachments_metadata($testdata->submission->id);

        $annotatedfiles = array_values(array_filter(
            $attachments,
            fn($a) => $a['type'] === attachment_type::GRADER_ANNOTATED_FILE->value
        ));
        $this->assertCount(1, $annotatedfiles);
        $this->assertEquals('annotated.pdf', $annotatedfiles[0]['filename']);
    }

    /**
     * Tests that get_submission_attachments_metadata() only returns grader feedback and
     * annotated files that belong to the requested submission, and never leaks another
     * student's grader files into the report.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_attachments_metadata_does_not_leak_other_students_grader_files(): void {
        $this->resetAfterTest();

        // Student A with a graded submission and grader feedback/annotated files.
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();
        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);
        $this::getDataGenerator()->add_grader_feedback_file($assign, $testdata->student->id, 'feedback_a.pdf');
        $this::getDataGenerator()->add_grader_annotated_file($assign, $testdata->student->id, 'annotated_a.pdf');

        // Student B, enrolled in the same course, with their own submission and
        // their own grader feedback/annotated files.
        $studentb = $this::getDataGenerator()->create_user();
        $this::getDataGenerator()->enrol_user($studentb->id, $testdata->course->id, 'student');

        /** @var \mod_assign_generator $assigngen */
        $assigngen = $this::getDataGenerator()->get_plugin_generator('mod_assign');
        $assigngen->create_submission([
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'onlinetext' => 'Test submission text from student B.',
            'cmid' => $testdata->assignment->cmid,
            'userid' => $studentb->id,
        ]);

        $this::getDataGenerator()->add_grader_feedback_file($assign, $studentb->id, 'feedback_b.pdf');
        $this::getDataGenerator()->add_grader_annotated_file($assign, $studentb->id, 'annotated_b.pdf');

        // Fetch attachments for student A's submission only.
        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $attachments = $assignman->get_submission_attachments_metadata($testdata->submission->id);

        $feedbackfiles = array_values(array_filter(
            $attachments,
            fn($a) => $a['type'] === attachment_type::GRADER_FEEDBACK_FILE->value
        ));
        $annotatedfiles = array_values(array_filter(
            $attachments,
            fn($a) => $a['type'] === attachment_type::GRADER_ANNOTATED_FILE->value
        ));

        // Only student A's own files must be present.
        $this->assertCount(1, $feedbackfiles);
        $this->assertEquals('feedback_a.pdf', $feedbackfiles[0]['filename']);
        $this->assertCount(1, $annotatedfiles);
        $this->assertEquals('annotated_a.pdf', $annotatedfiles[0]['filename']);

        // Student B's files must never leak into student A's report.
        $filenames = array_column($attachments, 'filename');
        $this->assertNotContains('feedback_b.pdf', $filenames);
        $this->assertNotContains('annotated_b.pdf', $filenames);
    }

    /**
     * Tests that get_submission_attachments_metadata() returns entries for all attachment types
     * when a fully-featured submission with all plugins enabled is present.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_attachments_metadata_all_attachment_types(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_fully_featured_assignment_with_submission();

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $attachments = $assignman->get_submission_attachments_metadata($testdata->submission->id);

        $bytype = [];
        foreach ($attachments as $a) {
            $bytype[$a['type']][] = $a;
        }

        $this->assertArrayHasKey(attachment_type::STUDENT_FILE_SUBMISSION->value, $bytype);
        $this->assertArrayHasKey(attachment_type::PROVIDED_ASSIGNMENT_FILE->value, $bytype);
        $this->assertArrayHasKey(attachment_type::GRADER_FEEDBACK_FILE->value, $bytype);
        $this->assertArrayHasKey(attachment_type::GRADER_ANNOTATED_FILE->value, $bytype);

        $this->assertEquals(
            $testdata->introfile->get_filename(),
            $bytype[attachment_type::PROVIDED_ASSIGNMENT_FILE->value][0]['filename']
        );
        $this->assertEquals(
            $testdata->feedbackfile->get_filename(),
            $bytype[attachment_type::GRADER_FEEDBACK_FILE->value][0]['filename']
        );
        $this->assertEquals(
            $testdata->annotatedfile->get_filename(),
            $bytype[attachment_type::GRADER_ANNOTATED_FILE->value][0]['filename']
        );
    }

    /**
     * Tests that get_submission_id_for_user() resolves the submission of a user.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_id_for_user(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();
        $otheruser = $this::getDataGenerator()->create_and_enrol($testdata->course, 'student');
        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);

        $this->assertSame((int) $testdata->submission->id, $assignman->get_submission_id_for_user($testdata->student->id));
        $this->assertNull($assignman->get_submission_id_for_user($otheruser->id), 'User without submission');
        $this->assertNull($assignman->get_submission_id_for_user(0), 'Invalid user ID must not fall back to current user');
    }

    /**
     * Tests that get_submission_id_for_user() respects the requested attempt number.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_id_for_user_with_attemptnumber(): void {
        global $DB;
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();

        // Add a second attempt for the student.
        $DB->set_field('assign_submission', 'latest', 0, ['id' => $testdata->submission->id]);
        $secondid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $testdata->assignment->id,
            'userid' => $testdata->student->id,
            'groupid' => 0,
            'attemptnumber' => $testdata->submission->attemptnumber + 1,
            'latest' => 1,
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertSame(
            (int) $secondid,
            $assignman->get_submission_id_for_user($testdata->student->id),
            'Default latest attempt'
        );
        $this->assertSame(
            (int) $testdata->submission->id,
            $assignman->get_submission_id_for_user($testdata->student->id, (int) $testdata->submission->attemptnumber),
            'Explicit first attempt'
        );
        $this->assertNull(
            $assignman->get_submission_id_for_user($testdata->student->id, 42),
            'Non-existing attempt'
        );
    }

    /**
     * Tests that get_submission_id_for_user() resolves the group submission if team submissions are enabled.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_id_for_user_team_submission(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this::getDataGenerator();

        // Create team assignment with a group of two students.
        $course = $generator->create_course();
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student1->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student2->id]);

        /** @var \mod_assign_generator $assigngen */
        $assigngen = $generator->get_plugin_generator('mod_assign');
        $assignment = $assigngen->create_instance([
            'course' => $course->id,
            'submissiondrafts' => 0,
            'teamsubmission' => 1,
            'requireallteammemberssubmit' => 0,
            'preventsubmissionnotingroup' => 0,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $assigngen->create_submission([
            'cmid' => $assignment->cmid,
            'userid' => $student1->id,
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'onlinetext' => 'Team submission text.',
        ]);
        $groupsubmission = $DB->get_record(
            'assign_submission',
            ['assignment' => $assignment->id, 'groupid' => $group->id, 'userid' => 0],
            '*',
            MUST_EXIST
        );

        // Both team members resolve to the group submission.
        $assignman = new assignment_manager($course->id, $assignment->cmid);
        $this->assertSame((int) $groupsubmission->id, $assignman->get_submission_id_for_user($student1->id));
        $this->assertSame((int) $groupsubmission->id, $assignman->get_submission_id_for_user($student2->id));
    }

    /**
     * Tests that get_submission_id_for_grade() resolves the graded submission.
     *
     * @covers \archivingmod_assign\assignment_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_submission_id_for_grade(): void {
        $this->resetAfterTest();
        $testdata = $this::getDataGenerator()->create_assignment_with_text_submission();
        $grade = $this::getDataGenerator()->grade_submission($testdata);
        $othertestdata = $this::getDataGenerator()->create_assignment_with_text_submission();
        $othergrade = $this::getDataGenerator()->grade_submission($othertestdata);

        $assignman = new assignment_manager($testdata->course->id, $testdata->cm->id);
        $this->assertSame((int) $testdata->submission->id, $assignman->get_submission_id_for_grade($grade->id));
        $this->assertNull($assignman->get_submission_id_for_grade($othergrade->id), 'Grade of another assignment');
        $this->assertNull($assignman->get_submission_id_for_grade(-1), 'Non-existing grade');
    }
}
