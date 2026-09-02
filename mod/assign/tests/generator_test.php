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
 * Tests for the archivingmod_assign test data generator
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;


/**
 * Tests for the archivingmod_assign_generator class
 */
final class generator_test extends \advanced_testcase {
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
     * Retrieves whether the given assign submission or feedback plugin is enabled for the given assignment.
     *
     * @param int $assignmentid ID of the assignment instance
     * @param string $subtype Plugin subtype, e.g. 'assignsubmission' or 'assignfeedback'
     * @param string $plugin Plugin name, e.g. 'onlinetext', 'file', or 'editpdf'
     * @return bool Whether the plugin is enabled
     * @throws \dml_exception
     */
    private function is_assign_plugin_enabled(int $assignmentid, string $subtype, string $plugin): bool {
        global $DB;

        $value = $DB->get_field('assign_plugin_config', 'value', [
            'assignment' => $assignmentid,
            'subtype' => $subtype,
            'plugin' => $plugin,
            'name' => 'enabled',
        ]);

        return (bool) $value;
    }

    /**
     * Tests generating the path to a fixture file
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     */
    public function test_get_fixture_file_path(): void {
        global $CFG;

        $generator = self::getDataGenerator();

        // Relative path (default).
        $relativepath = $generator->get_fixture_file_path('submissionsample01.txt');
        $this->assertSame(
            'local/archiving/mod/assign/tests/fixtures/submissionsample01.txt',
            $relativepath,
            'The relative fixture file path is incorrect'
        );

        // Full path.
        $fullpath = $generator->get_fixture_file_path('submissionsample01.txt', true);
        $this->assertSame(
            rtrim($CFG->dirroot, '/') . '/local/archiving/mod/assign/tests/fixtures/submissionsample01.txt',
            $fullpath,
            'The full fixture file path is incorrect'
        );

        // Leading slash on filename should be stripped.
        $this->assertSame(
            $relativepath,
            $generator->get_fixture_file_path('/submissionsample01.txt'),
            'A leading slash on the filename should be stripped'
        );
    }

    /**
     * Tests creating a plain test assignment with default parameters
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_assignment(): void {
        global $DB;

        // Create assignment with default parameters.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_assignment();

        // Verify course, cm, and assignment were created.
        $this->assertNotEmpty($DB->get_record('course', ['id' => $mocks->course->id]), 'The course was not created');
        $this->assertNotEmpty($DB->get_record('assign', ['id' => $mocks->assignment->id]), 'The assignment was not created');
        $this->assertSame(
            (int) $mocks->assignment->id,
            (int) $mocks->cm->instance,
            'The course module does not point to the created assignment'
        );

        // Verify submission drafts are disabled.
        $this->assertEquals(0, $mocks->assignment->submissiondrafts, 'Submission drafts should be disabled');

        // Verify teacher and student enrolment and roles.
        $context = \context_course::instance($mocks->course->id);
        $this->assertTrue(
            is_enrolled($context, $mocks->teacher->id),
            'The teacher should be enrolled in the course'
        );
        $this->assertTrue(
            is_enrolled($context, $mocks->student->id),
            'The student should be enrolled in the course'
        );
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->assertTrue(
            user_has_role_assignment($mocks->teacher->id, $teacherroleid, $context->id),
            'The teacher should have the editingteacher role'
        );
        $this->assertTrue(
            user_has_role_assignment($mocks->student->id, $studentroleid, $context->id),
            'The student should have the student role'
        );

        // Verify default plugin enable flags.
        $this->assertTrue(
            $this->is_assign_plugin_enabled($mocks->assignment->id, 'assignsubmission', 'onlinetext'),
            'The onlinetext submission plugin should be enabled by default'
        );
        $this->assertFalse(
            $this->is_assign_plugin_enabled($mocks->assignment->id, 'assignsubmission', 'file'),
            'The file submission plugin should be disabled by default'
        );
        $this->assertFalse(
            $this->is_assign_plugin_enabled($mocks->assignment->id, 'assignfeedback', 'file'),
            'The file feedback plugin should be disabled by default'
        );
        $this->assertFalse(
            $this->is_assign_plugin_enabled($mocks->assignment->id, 'assignfeedback', 'editpdf'),
            'The editpdf feedback plugin should be disabled by default'
        );
    }

    /**
     * Tests that the plugin enable flags passed to create_assignment() are respected
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_assignment_plugin_flags(): void {
        // Create assignment with all plugins enabled.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_assignment(
            onlinetextenabled: true,
            filesubmissionenabled: true,
            feedbackfileenabled: true,
            feedbackeditpdfenabled: true,
        );

        // Verify all plugins are enabled.
        $this->assertTrue(
            $this->is_assign_plugin_enabled($mocks->assignment->id, 'assignsubmission', 'onlinetext'),
            'The onlinetext submission plugin should be enabled'
        );
        $this->assertTrue(
            $this->is_assign_plugin_enabled($mocks->assignment->id, 'assignsubmission', 'file'),
            'The file submission plugin should be enabled'
        );
        $this->assertTrue(
            $this->is_assign_plugin_enabled($mocks->assignment->id, 'assignfeedback', 'file'),
            'The file feedback plugin should be enabled'
        );
        $this->assertTrue(
            $this->is_assign_plugin_enabled($mocks->assignment->id, 'assignfeedback', 'editpdf'),
            'The editpdf feedback plugin should be enabled'
        );
    }

    /**
     * Tests creating an assignment with a submission
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_assignment_with_submission(): void {
        // Create assignment with a submitted submission.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_assignment_with_submission(['onlinetext' => 'Lorem ipsum']);

        // Verify submission linkage and default status.
        $this->assertNotEmpty($mocks->submission, 'The submission was not created');
        $this->assertSame(
            (int) $mocks->assignment->id,
            (int) $mocks->submission->assignment,
            'The submission is not linked to the correct assignment'
        );
        $this->assertSame(
            (int) $mocks->student->id,
            (int) $mocks->submission->userid,
            'The submission is not linked to the correct student'
        );
        $this->assertEquals(
            ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            $mocks->submission->status,
            'The submission should be submitted by default'
        );
    }

    /**
     * Tests creating an assignment with an online text submission
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_assignment_with_text_submission(): void {
        global $DB;

        // Create assignment with default text.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_assignment_with_text_submission();

        $onlinetext = $DB->get_record('assignsubmission_onlinetext', ['submission' => $mocks->submission->id], '*', MUST_EXIST);
        $this->assertStringContainsString(
            'Test submission text.',
            $onlinetext->onlinetext,
            'The default submission text is incorrect'
        );

        // Create assignment with custom text.
        $mocks = self::getDataGenerator()->create_assignment_with_text_submission('Custom submission text.');
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', ['submission' => $mocks->submission->id], '*', MUST_EXIST);
        $this->assertStringContainsString(
            'Custom submission text.',
            $onlinetext->onlinetext,
            'The custom submission text is incorrect'
        );
    }

    /**
     * Tests creating a fully-featured assignment with a submission and all attachment types
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_fully_featured_assignment_with_submission(): void {
        global $DB;

        // Create fully-featured assignment.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_fully_featured_assignment_with_submission();

        // Verify all plugins are enabled.
        foreach (
            [
                ['assignsubmission', 'onlinetext'],
                ['assignsubmission', 'file'],
                ['assignfeedback', 'file'],
                ['assignfeedback', 'editpdf'],
            ] as [$subtype, $plugin]
        ) {
            $this->assertTrue(
                $this->is_assign_plugin_enabled($mocks->assignment->id, $subtype, $plugin),
                "The {$subtype}_{$plugin} plugin should be enabled"
            );
        }

        // Verify submission has both onlinetext and file content.
        $this->assertNotEmpty(
            $DB->get_record('assignsubmission_onlinetext', ['submission' => $mocks->submission->id]),
            'The submission should have online text content'
        );
        $this->assertNotEmpty(
            $DB->get_record('assignsubmission_file', ['submission' => $mocks->submission->id]),
            'The submission should have file content'
        );

        // Verify all three attachment files were created.
        $this->assertInstanceOf(\stored_file::class, $mocks->introfile, 'The intro attachment file was not created');
        $this->assertInstanceOf(\stored_file::class, $mocks->feedbackfile, 'The feedback file was not created');
        $this->assertInstanceOf(\stored_file::class, $mocks->annotatedfile, 'The annotated file was not created');
    }

    /**
     * Tests planting an intro attachment file
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_add_intro_attachment(): void {
        // Create assignment and plant intro attachment.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_assignment();
        $ctx = \context_module::instance($mocks->cm->id);
        $assign = new \assign($ctx, $mocks->cm, $mocks->course);

        $file = self::getDataGenerator()->add_intro_attachment($assign, 'custom_intro.pdf', 'custom intro content');

        // Verify file metadata.
        $this->assertSame('custom_intro.pdf', $file->get_filename(), 'The intro attachment has the wrong filename');
        $this->assertSame('mod_assign', $file->get_component(), 'The intro attachment has the wrong component');
        $this->assertSame(
            ASSIGN_INTROATTACHMENT_FILEAREA,
            $file->get_filearea(),
            'The intro attachment is in the wrong filearea'
        );
        $this->assertSame(0, $file->get_itemid(), 'The intro attachment has the wrong item ID');
        $this->assertSame($ctx->id, $file->get_contextid(), 'The intro attachment is in the wrong context');
        $this->assertSame('custom intro content', $file->get_content(), 'The intro attachment has the wrong content');
    }

    /**
     * Tests planting a grader feedback file
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_add_grader_feedback_file(): void {
        global $DB;

        // Create assignment and plant a grader feedback file.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_assignment_with_submission(['onlinetext' => 'Lorem ipsum']);
        $ctx = \context_module::instance($mocks->cm->id);
        $assign = new \assign($ctx, $mocks->cm, $mocks->course);

        $file = self::getDataGenerator()->add_grader_feedback_file(
            $assign,
            $mocks->student->id,
            'custom_feedback.pdf',
            'custom feedback content'
        );

        // Verify a grade record was created for the student.
        $grade = $DB->get_record('assign_grades', ['assignment' => $mocks->assignment->id, 'userid' => $mocks->student->id]);
        $this->assertNotEmpty($grade, 'A grade record should have been created for the student');

        // Verify file metadata.
        $this->assertSame('custom_feedback.pdf', $file->get_filename(), 'The feedback file has the wrong filename');
        $this->assertSame('assignfeedback_file', $file->get_component(), 'The feedback file has the wrong component');
        $this->assertSame(
            ASSIGNFEEDBACK_FILE_FILEAREA,
            $file->get_filearea(),
            'The feedback file is in the wrong filearea'
        );
        $this->assertSame((int) $grade->id, $file->get_itemid(), 'The feedback file has the wrong item ID');
        $this->assertSame('custom feedback content', $file->get_content(), 'The feedback file has the wrong content');
    }

    /**
     * Tests planting a grader annotated PDF file
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_add_grader_annotated_file(): void {
        global $DB;

        // Create assignment and plant a grader annotated file.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_assignment_with_submission(['onlinetext' => 'Lorem ipsum']);
        $ctx = \context_module::instance($mocks->cm->id);
        $assign = new \assign($ctx, $mocks->cm, $mocks->course);

        $file = self::getDataGenerator()->add_grader_annotated_file(
            $assign,
            $mocks->student->id,
            'custom_annotated.pdf',
            'custom annotated content'
        );

        // Verify a grade record was created for the student.
        $grade = $DB->get_record('assign_grades', ['assignment' => $mocks->assignment->id, 'userid' => $mocks->student->id]);
        $this->assertNotEmpty($grade, 'A grade record should have been created for the student');

        // Verify file metadata.
        $this->assertSame('custom_annotated.pdf', $file->get_filename(), 'The annotated file has the wrong filename');
        $this->assertSame('assignfeedback_editpdf', $file->get_component(), 'The annotated file has the wrong component');
        $this->assertSame(
            \assignfeedback_editpdf\document_services::FINAL_PDF_FILEAREA,
            $file->get_filearea(),
            'The annotated file is in the wrong filearea'
        );
        $this->assertSame((int) $grade->id, $file->get_itemid(), 'The annotated file has the wrong item ID');
        $this->assertSame('custom annotated content', $file->get_content(), 'The annotated file has the wrong content');
    }

    /**
     * Tests grading a submission and adding a feedback comment
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_grade_submission(): void {
        global $DB;

        // Create assignment with submission and grade it.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_assignment_with_text_submission();
        $gradeobj = self::getDataGenerator()->grade_submission($mocks, 88.5, 'Great work!');

        // Verify the returned grade object.
        $this->assertEquals(88.5, $gradeobj->grade, 'The grade was not set correctly');
        $this->assertSame((int) $mocks->teacher->id, (int) $gradeobj->grader, 'The grader was not set correctly');

        // Verify the feedback comment was inserted.
        $comment = $DB->get_record('assignfeedback_comments', [
            'grade' => $gradeobj->id,
            'assignment' => $mocks->assignment->id,
        ], '*', MUST_EXIST);
        $this->assertSame('Great work!', $comment->commenttext, 'The feedback comment text is incorrect');
    }

    /**
     * Tests creating a mock archiving task without a populated assignment or wstoken
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_mock_task(): void {
        // Create mock task.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_mock_task();

        // Verify job and task linkage.
        $this->assertNotEmpty($mocks->job, 'The job was not created');
        $this->assertNotEmpty($mocks->task, 'The task was not created');
        $this->assertSame($mocks->job->get_id(), $mocks->task->get_jobid(), 'The task is not linked to the correct job');
        $this->assertSame('assign', $mocks->task->get_archivingmodname(), 'The task type is incorrect');

        // Verify job context.
        $this->assertSame($mocks->context->id, $mocks->job->get_context()->id, 'The job context is incorrect');
        $this->assertSame($mocks->context->id, $mocks->task->get_context()->id, 'The task context is incorrect');

        // Verify job settings are populated with the expected keys.
        $settings = $mocks->job->get_settings();
        foreach (
            [
                'report_section_header',
                'report_section_instructions',
                'report_section_submission',
                'report_section_submissionstatus',
                'report_section_submissioncomments',
                'report_section_feedback',
                'report_section_feedbackcomments',
                'report_section_grade',
                'report_section_gradedetails',
                'attachment_assignment',
                'attachment_submission',
                'attachment_feedback',
                'attachment_annotation',
            ] as $key
        ) {
            $this->assertObjectHasProperty($key, $settings, "The job settings should have a '{$key}' property");
        }

        // Verify no wstoken was set.
        $this->assertNull($mocks->task->get_webservice_token(), 'No wstoken should be set by default');

        // Verify the assignment was not populated with a submission.
        $this->assertObjectNotHasProperty('submission', $mocks, 'No submission should exist when populateassignment is false');
    }

    /**
     * Tests creating a mock archiving task with a web service token
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_mock_task_with_wstoken(): void {
        // Create mock task with a wstoken.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_mock_task(wstoken: 'TEST-WSTOKEN');

        // Verify the wstoken was set.
        $this->assertSame('TEST-WSTOKEN', $mocks->task->get_webservice_token(), 'The task wstoken is incorrect');
    }

    /**
     * Tests creating a mock archiving task with a fully populated assignment
     *
     * @covers \archivingmod_assign_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_mock_task_populated(): void {
        // Create mock task with a populated assignment.
        $this->resetAfterTest();
        $mocks = self::getDataGenerator()->create_mock_task(populateassignment: true);

        // Verify the submission and all attachment files are present.
        $this->assertNotEmpty($mocks->submission, 'The submission was not created');
        $this->assertInstanceOf(\stored_file::class, $mocks->introfile, 'The intro attachment file was not created');
        $this->assertInstanceOf(\stored_file::class, $mocks->feedbackfile, 'The feedback file was not created');
        $this->assertInstanceOf(\stored_file::class, $mocks->annotatedfile, 'The annotated file was not created');
    }
}
