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

use local_archiving\activity_archiving_task;
use local_archiving\archive_job;
use local_archiving\local\type\cm_state_fingerprint;
use local_archiving\local\type\db_table;

// @codeCoverageIgnoreStart
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/feedback/file/locallib.php');
require_once($CFG->dirroot . '/mod/assign/feedback/editpdf/locallib.php');
// @codeCoverageIgnoreEnd


/**
 * Tests generator for the archivingmod_assign plugin
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archivingmod_assign_generator extends \testing_data_generator {
    /**
     * Retrieves the full path to a fixture file based on the given fixture
     * filename
     *
     * @param string $filename Filename of the fixture to get, relative to the
     * tests/fixtures directory
     * @param bool $fullpath Whether to return the full path or just the relative path
     * @return string Full path to the fixture file
     */
    public static function get_fixture_file_path(string $filename, bool $fullpath = false): string {
        global $CFG;
        return ($fullpath ? rtrim($CFG->dirroot, '/') . '/' : '') .
               'local/archiving/mod/assign/tests/fixtures/' .
               ltrim($filename, '/');
    }

    /**
     * Creates a mock assignment archiving task, optionally with a web service token.
     *
     * @param bool $populateassignment If true, a fully featured assignment with submissions will be generated.
     * If false, an empty assignment without submissions will be generated.
     * @param string|null $wstoken Web service token to assign to the task
     * @return \stdClass Object with properties: course, cm, assignment, teacher, student, context, job, task
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_mock_task(bool $populateassignment = false, ?string $wstoken = null): \stdClass {
        global $DB;

        // Create assignment and derive context.
        $mocks = $populateassignment ? $this->create_fully_featured_assignment_with_submission() : $this->create_assignment();
        $context = \context_module::instance($mocks->cm->id);

        // Create archive job and task.
        $job = archive_job::create($context, get_admin()->id, 'manual', settings: (object) [
            // Report sections (one key per submission_report_section case).
            'report_section_header' => 1,
            'report_section_instructions' => 1,
            'report_section_submission' => 1,
            'report_section_submissionstatus' => 1,
            'report_section_submissioncomments' => 1,
            'report_section_feedback' => 1,
            'report_section_feedbackcomments' => 1,
            'report_section_grade' => 1,
            'report_section_gradedetails' => 1,

            // Attachments (one key per attachment_type case).
            'attachment_assignment' => 1,
            'attachment_submission' => 1,
            'attachment_feedback' => 1,
            'attachment_annotation' => 1,

            // Report / rendering settings.
            'paper_format' => 'A4',
            'keep_html_files' => 0,
            'image_optimize' => 1,
            'image_optimize_width' => 1280,
            'image_optimize_height' => 1280,
            'image_optimize_quality' => 85,
            'archive_filename_pattern' => 'archive-${courseshortname}-${courseid}-${cmtype}-${cmname}-${cmid}_${date}-${time}',
            'submission_foldername_pattern' => '${username}/${submissionid}-${date}_${time}',
            'submission_filename_pattern' => 'submission-${username}-${submissionid}-${date}_${time}',
            'archive_flatten' => 0,
        ]);
        $task = activity_archiving_task::create(
            $job->get_id(),
            $context,
            cm_state_fingerprint::from_raw_value(str_repeat('0', 64)),
            get_admin()->id,
            'assign'
        );

        // Assign wstoken if given.
        if ($wstoken !== null) {
            $DB->set_field(db_table::ACTIVITY_TASK->value, 'wstoken', $wstoken, ['id' => $task->get_id()]);
        }

        $mocks->context = $context;
        $mocks->job = $job;
        $mocks->task = $task;

        return $mocks;
    }

    /**
     * Creates a test course with an assignment activity, a teacher, and a student.
     *
     * Both users are enrolled in the course. Submission drafts are disabled.
     * Which submission and feedback plugins are enabled is controlled by the
     * parameters.
     *
     * @param bool $onlinetextenabled Enable the online text submission plugin
     * @param bool $filesubmissionenabled Enable the file submission plugin
     * @param bool $feedbackfileenabled Enable the feedback file plugin
     * @param bool $feedbackeditpdfenabled Enable the feedback editpdf plugin
     * @return \stdClass Object with properties: course, cm, assignment, teacher, student
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_assignment(
        bool $onlinetextenabled = true,
        bool $filesubmissionenabled = false,
        bool $feedbackfileenabled = false,
        bool $feedbackeditpdfenabled = false,
    ): \stdClass {
        // Create entities.
        $course = $this->create_course();
        $teacher = $this->create_user();
        $student = $this->create_user();

        $this->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->enrol_user($student->id, $course->id, 'student');

        // Create assignment.
        /** @var \mod_assign_generator $assigngen */
        $assigngen = $this->get_plugin_generator('mod_assign');
        $assignment = $assigngen->create_instance([
            'course'                              => $course->id,
            'submissiondrafts'                    => 0,
            'assignsubmission_onlinetext_enabled' => (int) $onlinetextenabled,
            'assignsubmission_file_enabled'       => (int) $filesubmissionenabled,
            'assignsubmission_file_maxfiles'      => 1,
            'assignsubmission_file_maxsizebytes'  => 0,
            'assignfeedback_file_enabled'         => (int) $feedbackfileenabled,
            'assignfeedback_editpdf_enabled'      => (int) $feedbackeditpdfenabled,
        ]);

        // Build response.
        [$course, $cm] = get_course_and_cm_from_cmid($assignment->cmid, 'assign');

        return (object) [
            'course'     => $course,
            'cm'         => $cm,
            'assignment' => $assignment,
            'teacher'    => $teacher,
            'student'    => $student,
        ];
    }

    /**
     * Creates a test course with an assignment and a single submitted submission.
     *
     * The $submissiondata array is passed directly to the mod_assign generator's
     * create_submission(), which dispatches to sub-plugin generators based on the
     * keys present (e.g. 'onlinetext', 'file'). The matching plugin(s) must be
     * enabled via the corresponding flag parameters.
     *
     * @param array $submissiondata Data for the submission (plugin-specific keys + optional 'status')
     * @param bool $onlinetextenabled Enable the online text submission plugin
     * @param bool $filesubmissionenabled Enable the file submission plugin
     * @param bool $feedbackfileenabled Enable the feedback file plugin
     * @param bool $feedbackeditpdfenabled Enable the feedback editpdf plugin
     * @return \stdClass Object with properties: course, cm, assignment, teacher, student, submission
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_assignment_with_submission(
        array $submissiondata,
        bool $onlinetextenabled = true,
        bool $filesubmissionenabled = false,
        bool $feedbackfileenabled = false,
        bool $feedbackeditpdfenabled = false,
    ): \stdClass {
        global $DB;

        // Create empty assignment.
        $testdata = $this->create_assignment(
            $onlinetextenabled,
            $filesubmissionenabled,
            $feedbackfileenabled,
            $feedbackeditpdfenabled,
        );

        // Add submission.
        /** @var \mod_assign_generator $assigngen */
        $assigngen = $this->get_plugin_generator('mod_assign');
        $assigngen->create_submission(array_merge(
            ['status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED],
            $submissiondata,
            ['cmid' => $testdata->assignment->cmid, 'userid' => $testdata->student->id],
        ));

        // Build response.
        $submission = $DB->get_record(
            'assign_submission',
            ['assignment' => $testdata->assignment->id, 'userid' => $testdata->student->id],
            '*',
            MUST_EXIST
        );

        return (object) [
            'course'     => $testdata->course,
            'cm'         => $testdata->cm,
            'assignment' => $testdata->assignment,
            'teacher'    => $testdata->teacher,
            'student'    => $testdata->student,
            'submission' => $submission,
        ];
    }

    /**
     * Creates a test course with an assignment activity and one submitted online
     * text submission from the student.
     *
     * @param string|null $submissiontext Optional text to use for the submission. If null, a default text will be used.
     * @return \stdClass Object with properties: course, cm, assignment, teacher, student, submission
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_assignment_with_text_submission(?string $submissiontext = null): \stdClass {
        return $this->create_assignment_with_submission([
            'onlinetext' => $submissiontext ?? 'Test submission text.',
        ]);
    }

    /**
     * Creates a fully-featured assignment with a submission that uses all submission
     * and feedback plugins, plus all three file attachment types.
     *
     * Useful as a one-call setup for tests that need every attachment type present.
     *
     * @return \stdClass Object with properties: course, cm, assignment, teacher, student,
     *                   submission, introfile, feedbackfile, annotatedfile
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_fully_featured_assignment_with_submission(): \stdClass {
        $testdata = $this->create_assignment_with_submission(
            submissiondata: [
                'onlinetext' => 'Test submission text.',
                'file'       => $this::get_fixture_file_path('submissionsample01.txt'),
            ],
            onlinetextenabled: true,
            filesubmissionenabled: true,
            feedbackfileenabled: true,
            feedbackeditpdfenabled: true,
        );

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);

        $introfile = $this->add_intro_attachment($assign);
        $feedbackfile = $this->add_grader_feedback_file($assign, $testdata->student->id);
        $annotatedfile = $this->add_grader_annotated_file($assign, $testdata->student->id);

        return (object) array_merge((array) $testdata, [
            'introfile'     => $introfile,
            'feedbackfile'  => $feedbackfile,
            'annotatedfile' => $annotatedfile,
        ]);
    }

    /**
     * Plants a file in the assignment intro attachment filearea (PROVIDED_ASSIGNMENT_FILE).
     *
     * @param \assign $assign Assignment instance
     * @param string $filename Name for the planted file
     * @param string $filecontent Synthetic file content
     * @return \stored_file
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function add_intro_attachment(
        \assign $assign,
        string $filename = 'intro_attachment.pdf',
        string $filecontent = 'intro file content',
    ): \stored_file {
        $contextid = $assign->get_context()->id;

        return get_file_storage()->create_file_from_string(
            [
                'contextid' => $contextid,
                'component' => 'mod_assign',
                'filearea'  => ASSIGN_INTROATTACHMENT_FILEAREA,
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $filename,
            ],
            $filecontent
        );
    }

    /**
     * Plants a file in the grader feedback file area (GRADER_FEEDBACK_FILE).
     *
     * Creates a grade record for the student if one does not already exist.
     *
     * @param \assign $assign Assignment instance
     * @param int $studentid ID of the student whose grade record to use
     * @param string $filename Name for the planted file
     * @param string $filecontent Synthetic file content
     * @return \stored_file
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function add_grader_feedback_file(
        \assign $assign,
        int $studentid,
        string $filename = 'feedback.pdf',
        string $filecontent = 'feedback file content',
    ): \stored_file {
        $grade = $assign->get_user_grade($studentid, true);

        return get_file_storage()->create_file_from_string(
            [
                'contextid' => $assign->get_context()->id,
                'component' => 'assignfeedback_file',
                'filearea'  => ASSIGNFEEDBACK_FILE_FILEAREA,
                'itemid'    => $grade->id,
                'filepath'  => '/',
                'filename'  => $filename,
            ],
            $filecontent
        );
    }

    /**
     * Plants a file in the grader annotated PDF file area (GRADER_ANNOTATED_FILE).
     *
     * Creates a grade record for the student if one does not already exist.
     *
     * @param \assign $assign Assignment instance
     * @param int $studentid ID of the student whose grade record to use
     * @param string $filename Name for the planted file
     * @param string $filecontent Synthetic file content
     * @return \stored_file
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function add_grader_annotated_file(
        \assign $assign,
        int $studentid,
        string $filename = 'annotated.pdf',
        string $filecontent = 'annotated file content',
    ): \stored_file {
        $grade = $assign->get_user_grade($studentid, true);

        return get_file_storage()->create_file_from_string(
            [
                'contextid' => $assign->get_context()->id,
                'component' => 'assignfeedback_editpdf',
                'filearea'  => \assignfeedback_editpdf\document_services::FINAL_PDF_FILEAREA,
                'itemid'    => $grade->id,
                'filepath'  => '/',
                'filename'  => $filename,
            ],
            $filecontent
        );
    }

    /**
     * Grades the given submission and adds a feedback comment.
     *
     * @param \stdClass $testdata Test data as returned by create_assignment_with_text_submission()
     * @param float $grade Grade to assign
     * @param string $feedbackcomment Feedback comment text
     * @return \stdClass The updated assign_grades record
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function grade_submission(
        \stdClass $testdata,
        float $grade = 75.0,
        string $feedbackcomment = 'Well done.'
    ): \stdClass {
        global $DB;

        $ctx = \context_module::instance($testdata->cm->id);
        $assign = new \assign($ctx, $testdata->cm, $testdata->course);

        $gradeobj = $assign->get_user_grade($testdata->student->id, true);
        $gradeobj->grade = $grade;
        $gradeobj->grader = $testdata->teacher->id;
        $assign->update_grade($gradeobj);

        $DB->insert_record('assignfeedback_comments', (object) [
            'commenttext' => $feedbackcomment,
            'commentformat' => FORMAT_HTML,
            'grade' => $gradeobj->id,
            'assignment' => $testdata->assignment->id,
        ]);

        return $gradeobj;
    }
}
