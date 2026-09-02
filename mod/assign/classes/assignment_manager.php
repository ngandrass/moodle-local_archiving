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
 * This file defines the assignment manager class
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;

use archivingmod_assign\external\generate_submission_report;
use archivingmod_assign\local\type\attachment_type;
use context_module;

// @codeCoverageIgnoreStart
// phpcs:ignore
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/feedback/file/locallib.php');
require_once($CFG->dirroot . '/mod/assign/submission/file/locallib.php');
// @codeCoverageIgnoreEnd


/**
 * Assignment managemer
 *
 * This class provides a high-level management interface for working with
 * assignments during the archiving process.
 */
class assignment_manager {
    /** @var \stdClass Course object this instance is associated with */
    protected \stdClass $course;

    /** @var \cm_info Course module info object this instance is associated with */
    protected \cm_info $cm;

    /** @var \assign Assignment object this instance is associated with */
    protected \assign $assignment;

    /**
     * Creates a new assignment manager
     *
     * @param int $courseid ID of the course the assignment lives in
     * @param int $cmid ID of the course module the assignment lives in
     * @throws \dml_exception If the course or cm cannot be found
     * @throws \moodle_exception If the given arguments are invalid
     */
    public function __construct(
        /** @var int ID of the course the assignment lives in */
        protected int $courseid,
        /** @var int ID of the course module the assignment lives in */
        protected int $cmid
    ) {
        // Validate arguments.
        $ctx = context_module::instance($cmid);
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
        if ($course->id != $courseid) {
            throw new \moodle_exception('invalidcourseid', 'local_archiving');
        }

        // Get assignment.
        $assignment = new \assign($ctx, $cm, $course);
        if ($assignment->get_course_module()->id != $this->cmid) {
            // We should never get here but let's be sure.
            throw new \moodle_exception('assignmentnotfound', 'local_archiving'); // @codeCoverageIgnore
        }

        $this->course = $course;
        $this->cm = $cm;
        $this->assignment = $assignment;
    }

    /**
     * Create a new assignment manager based on the given module context
     *
     * @param \context_module $ctx Module context to create the assignment manager for
     * @return self New assignment manager instance
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function from_context(\context_module $ctx): self {
        return new self($ctx->get_course_context()->instanceid, $ctx->instanceid);
    }

    /**
     * Creats a new submission report handler for this assignment.
     *
     * @return submission_report Submission report generator for this assignment.
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function submission_report(): submission_report {
        return new submission_report(
            course: $this->course,
            cm: $this->cm,
            assignment: $this->assignment,
        );
    }

    /**
     * Returns the assignment object this instance is associated with
     *
     * @return \assign Assignment object this instance is associated with
     */
    public function get_assignment(): \assign {
        return $this->assignment;
    }

    /**
     * Returns the course module info object this instance is associated with
     *
     * @return \cm_info Course module info object this instance is associated with
     */
    public function get_cm(): \cm_info {
        return $this->cm;
    }

    /**
     * Returns the course object this instance is associated with
     *
     * @return \stdClass Course object this instance is associated with
     */
    public function get_course(): \stdClass {
        return $this->course;
    }

    /**
     * Get all submissions for all users inside this assignment
     *
     * @return array Array of all submission IDs together with the userid that were
     * made inside this assignment. Indexed by submissionid.
     *
     * @throws \dml_exception
     */
    public function get_submissions(): array {
        global $DB;

        return $DB->get_records(
            table: 'assign_submission',
            conditions: [
                'assignment' => $this->assignment->get_instance()->id,
                'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            ],
            sort: 'id ASC',
            fields: 'id AS submissionid, userid'
        );
    }

    /**
     * Gets the metadata of all submissions made inside this assignment
     *
     * @param array|null $filtersubmissionids If given, only submissions with
     * the given IDs will be returned.
     *
     * @return array
     * @throws \dml_exception
     */
    public function get_submissions_metadata(?array $filtersubmissionids = null): array {
        global $DB;

        // Handle submission ID filter.
        if ($filtersubmissionids) {
            $submissionidssql = implode(', ', array_map(fn($v): string => intval($v), $filtersubmissionids));
            $filterwhereclause = "AND s.id IN ({$submissionidssql})";
        }

        // Get all requested submissions.
        return $DB->get_records_sql(
            "SELECT s.id AS submissionid, s.userid, s.attemptnumber, s.status,
                    s.timecreated, s.timemodified, s.timestarted,
                    u.username, u.firstname, u.lastname, u.idnumber
             FROM {assign_submission} s LEFT JOIN {user} u ON s.userid = u.id
             WHERE status = :status AND s.assignment = :assignmentid " . ($filterwhereclause ?? ''),
            [
                'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
                'assignmentid' => $this->assignment->get_instance()->id,
            ]
        );
    }

    /**
     * Checks if a submission with the given ID exists inside this assignment
     *
     * @param int $submissionid ID of the submission to check for existence
     * @return bool True if a submission with the given ID exists inside this
     * assignment, false otherwise
     * @throws \dml_exception
     */
    public function submission_exists(int $submissionid): bool {
        global $DB;

        return $DB->record_exists('assign_submission', [
            'id' => $submissionid,
            'assignment' => $this->assignment->get_instance()->id,
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
        ]);
    }

    /**
     * Returns a list of metadata for all files that were attached to the given
     * submission to be used within the webservice API
     *
     * @param int $submissionid ID of the submission to get the files from
     * @return array containing the metadata of all files that are attached to
     * the given submission.
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_submission_attachments_metadata(int $submissionid): array {
        global $DB;

        $res = [];

        // Get all STUDENT_FILE_SUBMISSION attachments.
        $asfile = new \assign_submission_file($this->assignment, 'file');
        /** @var \stored_file[] $files */
        $files = $asfile->get_files(
            submission: (object) [
                'id' => $submissionid,
                'exportfullpath' => true,
            ],
            user: (object) [] // User object is unused as per PHPDoc.
        );
        $res = array_merge($res, generate_submission_report::prepare_attachment_list_for_response(
            attachment_type::STUDENT_FILE_SUBMISSION,
            $files
        ));

        // Get all PROVIDED_ASSIGNMENT_FILE attachments.
        $files = get_file_storage()->get_area_files(
            contextid: $this->assignment->get_context()->id,
            component: 'mod_assign',
            filearea: ASSIGN_INTROATTACHMENT_FILEAREA,
            includedirs: false
        );
        $res = array_merge($res, generate_submission_report::prepare_attachment_list_for_response(
            attachment_type::PROVIDED_ASSIGNMENT_FILE,
            $files
        ));

        // Retrieve grade records from DB for submission-specific attachments.
        $grades = $DB->get_records_sql("
            SELECT g.id
            FROM {assign_grades} g
                JOIN {assign_submission} s
                    ON g.assignment = s.assignment
                   AND g.userid = s.userid
                   AND g.attemptnumber = s.attemptnumber
            WHERE s.id = :submissionid
        ", ['submissionid' => $submissionid]);

        foreach ($grades as $grade) {
            // Get all GRADER_FEEDBACK_FILE attachments.
            $files = get_file_storage()->get_area_files(
                contextid: $this->assignment->get_context()->id,
                component: 'assignfeedback_file',
                filearea: ASSIGNFEEDBACK_FILE_FILEAREA,
                itemid: $grade->id,
                includedirs: false
            );
            $res = array_merge($res, generate_submission_report::prepare_attachment_list_for_response(
                attachment_type::GRADER_FEEDBACK_FILE,
                $files
            ));

            // Get all GRADER_ANNOTATED_FILE attachments.
            $files = get_file_storage()->get_area_files(
                contextid: $this->assignment->get_context()->id,
                component: 'assignfeedback_editpdf',
                filearea: \assignfeedback_editpdf\document_services::FINAL_PDF_FILEAREA,
                itemid: $grade->id,
                includedirs: false
            );
            $res = array_merge($res, generate_submission_report::prepare_attachment_list_for_response(
                attachment_type::GRADER_ANNOTATED_FILE,
                $files
            ));
        }

        return $res;
    }
}
