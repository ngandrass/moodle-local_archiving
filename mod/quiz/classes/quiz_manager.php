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
 * This file defines the quiz class
 *
 * @package   archivingmod_quiz
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

// Required for legacy mod_quiz functions ...
require_once("$CFG->dirroot/mod/quiz/locallib.php");  // @codeCoverageIgnore


/**
 * Quiz management class
 *
 * This class provides a high-level management interface for working with
 * quizzes during the archiving process.
 */
class quiz_manager {
    /** @var \stdClass Course object this instance is associated with */
    protected \stdClass $course;

    /** @var \cm_info Course module info object this instance is associated with */
    protected \cm_info $cm;

    /** @var \stdClass Quiz object this instance is associated with */
    protected \stdClass $quiz;

    /**
     * Creates a new quiz manager
     *
     * @param int $courseid ID of the course the quiz lives in
     * @param int $cmid ID of the course module the quiz lives in
     * @throws \dml_exception If the course or cm cannot be found
     * @throws \moodle_exception If the given arguments are invalid
     */
    public function __construct(
        /** @var int ID of the course the quiz lives in */
        protected int $courseid,
        /** @var int ID of the course module the quiz lives in */
        protected int $cmid
    ) {
        global $DB;

        // Validate arguments.
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        if ($course->id != $courseid) {
            throw new \moodle_exception('invalidcourseid', 'local_archiving');
        }

        $this->course = $course;
        $this->cm = $cm;
        $this->quiz = $quiz;
    }

    /**
     * Create a new quiz manager based on the given module context
     *
     * @param \context_module $ctx Module context to create the quiz manager for
     * @return self New quiz manager instance
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function from_context(\context_module $ctx): self {
        return new self($ctx->get_course_context()->instanceid, $ctx->instanceid);
    }

    /**
     * Creates a new attempt_report instance that is associated with this quiz
     *
     * @return attempt_report New attempt_report instance
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function attempt_report(): attempt_report {
        return new attempt_report($this->course, $this->cm, $this->quiz);
    }

    /**
     * Returns the quiz object this instance is associated with
     *
     * @return \stdClass Quiz object this instance is associated with
     */
    public function get_quiz(): \stdClass {
        return $this->quiz;
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
     * Get all attempts for all users inside this quiz, excluding previews
     *
     * @return array Array of all attempt IDs together with the userid that were
     * made inside this quiz. Indexed by attemptid.
     *
     * @throws \dml_exception
     */
    public function get_attempts(): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT id AS attemptid, userid " .
            "FROM {quiz_attempts} " .
            "WHERE preview = 0 AND quiz = :quizid",
            [
                "quizid" => $this->quiz->id,
            ]
        );
    }

    /**
     * Gets the metadata of all attempts made inside this quiz, excluding previews.
     *
     * @param array|null $filterattemptids If given, only attempts with the given
     * IDs will be returned.
     *
     * @return array
     * @throws \dml_exception
     */
    public function get_attempts_metadata(?array $filterattemptids = null): array {
        global $DB;

        // Handle attempt ID filter.
        if ($filterattemptids) {
            $filterwhereclause = "AND qa.id IN (" . implode(', ', array_map(fn($v): string => intval($v), $filterattemptids)) . ")";
        }

        // Get all requested attempts.
        return $DB->get_records_sql(
            "SELECT qa.id AS attemptid, qa.userid, qa.attempt, qa.state, qa.timestart, qa.timefinish, " .
            "       u.username, u.firstname, u.lastname, u.idnumber " .
            "FROM {quiz_attempts} qa LEFT JOIN {user} u ON qa.userid = u.id " .
            "WHERE qa.preview = 0 AND qa.quiz = :quizid " . ($filterwhereclause ?? ''),
            [
                "quizid" => $this->quiz->id,
            ]
        );
    }

    /**
     * Checks if an attempt with the given ID exists inside this quiz and it's
     * not a preview
     *
     * @param int $attemptid ID of the attempt to check for existence
     * @return bool True if an attempt with the given ID exists inside this quiz
     * @throws \dml_exception
     */
    public function attempt_exists(int $attemptid): bool {
        global $DB;

        return $DB->record_exists('quiz_attempts', [
            'id' => $attemptid,
            'quiz' => $this->quiz->id,
            'preview' => 0,
        ]);
    }

    /**
     * Returns a list of all files that were attached to questions inside the
     * given attempt
     *
     * @param int $attemptid ID of the attempt to get the files from
     * @return array List of all files that are attached to the questions
     *               inside the given attempt
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_attempt_attachments(int $attemptid): array {
        $files = [];
        $attemptobj = quiz_create_attempt_handling_errors($attemptid);
        $ctx = \context_module::instance($attemptobj->get_cmid());

        // Get all files from all questions inside this attempt.
        foreach ($attemptobj->get_slots() as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $qafiles = $qa->get_last_qt_files('attachments', $ctx->id);

            foreach ($qafiles as $qafile) {
                $files[] = [
                    'usageid' => $qa->get_usage_id(),
                    'slot' => $slot,
                    'file' => $qafile,
                ];
            }
        }

        return $files;
    }

    /**
     * Returns a list of metadata for all files that were attached to questions
     * inside the given attempt to be used within the webservice API
     *
     * @param int $attemptid ID of the attempt to get the files from
     * @return array containing the metadata of all files that are attached to
     * the questions inside the given attempt.
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_attempt_attachments_metadata(int $attemptid): array {
        $res = [];

        foreach ($this->get_attempt_attachments($attemptid) as $attachment) {
            $downloadurl = strval(\moodle_url::make_webservice_pluginfile_url(
                $attachment['file']->get_contextid(),
                $attachment['file']->get_component(),
                $attachment['file']->get_filearea(),
                "{$attachment['usageid']}/{$attachment['slot']}/{$attachment['file']->get_itemid()}",
                /* ^-- YES, this is the abomination of a non-numeric itemid that question_attempt::get_response_file_url()
                   creates while eating innocent programmers for breakfast ... */
                $attachment['file']->get_filepath(),
                $attachment['file']->get_filename()
            ));

            $res[] = (object) [
                'slot' => $attachment['slot'],
                'filename' => $attachment['file']->get_filename(),
                'filesize' => $attachment['file']->get_filesize(),
                'mimetype' => $attachment['file']->get_mimetype(),
                'contenthash' => $attachment['file']->get_contenthash(),
                'downloadurl' => $downloadurl,
            ];
        }

        return $res;
    }
}
