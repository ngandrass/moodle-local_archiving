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

/**
 * Quiz activity archiving driver
 *
 * @package     archivingmod_quiz
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;

use local_archiving\activity_archiving_task;
use local_archiving\exception\yield_exception;
use local_archiving\type\activity_archiving_task_status;
use local_archiving\type\cm_state_fingerprint;
use local_archiving\type\task_content_metadata;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Quiz activity archiving driver
 */
class archivingmod extends \local_archiving\driver\archivingmod {
    /** @var \stdClass Course the quiz lives in */
    protected \stdClass $course;

    /** @var \cm_info Info object of the associated course module */
    protected \cm_info $cm;

    /** @var int ID of the targeted quiz */
    protected int $quizid;

    /** @var string Short-name of the web service the external worker uses for communication with Moodle */
    public const WEB_SERVICE_SHORTNAME = 'archivingmod_quiz_ws';

    /**
     * Creates a new activity archiving driver instance.
     *
     * @param \context_module $context
     * @throws \moodle_exception
     */
    public function __construct(\context_module $context) {
        parent::__construct($context);

        // Try to get course, cm info, and quiz.
        [$this->course, $this->cm] = get_course_and_cm_from_cmid($this->cmid, 'quiz');
        if (empty($this->cm)) {
            throw new \moodle_exception('invalid_cmid', 'archivingmod_quiz'); // @codeCoverageIgnore
        }
        if ($this->course->id != $this->courseid) {
            throw new \moodle_exception('invalid_courseid', 'archivingmod_quiz'); // @codeCoverageIgnore
        }
        $this->quizid = $this->cm->instance;
    }

    #[\Override]
    public static function is_ready(): bool {
        $config = get_config('archivingmod_quiz');

        if (
            strlen($config->worker_url ?? '') > 1 &&
            self::get_webserviceid() > 0 &&
            self::is_webservices_enabled() &&
            self::is_webserviceproto_rest_enabled()
        ) {
            return true;
        }

        return false;
    }


    #[\Override]
    public static function get_supported_activities(): array {
        return ['quiz'];
    }

    #[\Override]
    public function can_be_archived(): bool {
        global $DB;

        // Check if quiz has questions.
        if (!$DB->record_exists('quiz_slots', ['quizid' => $this->quizid])) {
            return false;
        }

        // Check if quiz has attempts.
        if (!$DB->record_exists('quiz_attempts', ['quiz' => $this->quizid, 'preview' => 0])) {
            return false;
        }

        return true;
    }

    #[\Override]
    public function get_job_create_form(string $handler, \cm_info $cminfo): \local_archiving\form\job_create_form {
        return new form\job_create_form($handler, $cminfo); // @codeCoverageIgnore
    }

    #[\Override]
    public function execute_task(activity_archiving_task $task): void {
        if ($task->get_status(usecached: true) == activity_archiving_task_status::UNINITIALIZED) {
            $task->set_status(activity_archiving_task_status::CREATED);
        }

        if ($task->get_status(usecached: true) == activity_archiving_task_status::CREATED) {
            // Prepare access to quiz and webservice.
            $quizmanager = quiz_manager::from_context($task->get_context());
            $attempts = $quizmanager->get_attempts();

            $wstoken = $task->create_webservice_token(
                webserviceid: self::get_webserviceid(),
                userid: get_admin()->id,
                lifetimesec: get_config('local_archiving', 'job_timeout_min') * MINSECS
            );

            // Calculate and persist metadata.
            $numattachments = 0;
            $task->get_logger()->trace('Counting number of attachments in all attempts ...');
            foreach ($attempts as $attempt) {
                $numattachments += count($quizmanager::get_attempt_attachments($attempt->attemptid));
            }
            $task->get_logger()->trace("Found {$numattachments} attachments in all attempts.");

            $job = $task->get_job();
            $job->set_metadata_entry('num_attempts', count($attempts));
            $job->set_metadata_entry('num_attachments', $numattachments);

            // Do not actually call the remote archive worker during unit tests.
            if (defined('PHPUNIT_TEST') && PHPUNIT_TEST === true) {
                $task->set_status(activity_archiving_task_status::AWAITING_PROCESSING);
                throw new yield_exception();
            }

            // @codeCoverageIgnoreStart

            // Enqueue a new job at the worker. Non-recoverable errors are bubbled
            // up to the core archiving manager and handled there.
            $worker = remote_archive_worker::instance();
            $workerjob = $worker->enqueue_archive_job(
                wstoken: $wstoken,
                task: $task,
                attemptids: array_keys($attempts)
            );
            $task->get_logger()->info("Enqueued new worker job with UUID {$workerjob->uuid}");

            $task->set_status(activity_archiving_task_status::AWAITING_PROCESSING);
            throw new yield_exception();

            // @codeCoverageIgnoreEnd
        }

        // Starting with status AWAITING_PROCESSING control is given to the external worker service. It will interact with the web
        // service functions and update the task status accordingly. Timeout is handled on job level. Therefore we just yield if the
        // task is not jet finished or return cleanly if it reached a final state.
        if ($task->is_completed()) {
            // Task is completed.
            return;
        } else {
            throw new yield_exception();
        }
    }

    #[\Override]
    public function get_task_content_metadata(activity_archiving_task $task): array {
        $quizmanager = quiz_manager::from_context($task->get_context());

        $res = [];
        foreach ($quizmanager->get_attempts() as $attempt) {
            $res[] = new task_content_metadata(
                taskid: $task->get_id(),
                userid: $attempt->userid,
                reftable: 'quiz_attempts',
                refid: $attempt->attemptid,
                summary: null
            );
        }

        return $res;
    }

    /**
     * Returns a fingerprint for the current state of the referenced quiz.
     *
     * We use the latest modification time of the quiz itself and the latest
     * modification time of any attempt inside this quiz to calculate the
     * fingerprint.
     *
     * @return cm_state_fingerprint Fingerprint for the current state of the
     * referenced course module
     * @throws \JsonException On JSON encoding errors
     * @throws \coding_exception If the fingerprint calculation fails
     * @throws \dml_exception On database errors
     */
    #[\Override]
    public function fingerprint(): cm_state_fingerprint {
        global $DB;

        // Get the latest modification time of any attempt indide this quiz as well as the quiz itself.
        $quiztimemodified = $DB->get_field('quiz', 'timemodified', ['id' => $this->quizid], MUST_EXIST);

        $attempttimemodified = $DB->get_field_sql(
            "SELECT MAX(timemodified) FROM {quiz_attempts} WHERE quiz = :quizid",
            ['quizid' => $this->quizid],
            MUST_EXIST
        );

        // Calculate the fingerprint.
        return cm_state_fingerprint::generate([
            'quiztimemodified' => $quiztimemodified,
            'attempttimemodified' => $attempttimemodified,
        ]);
    }

    /**
     * Retrieves the ID of the self::WEB_SERVICE_SHORTNAME web service
     *
     * @return int ID of the web service
     * @throws \dml_exception If the web service could not be found (should never happen,
     * but who knows all the weird stats a Moodle instance can be in ...)
     */
    public static function get_webserviceid(): int {
        global $DB;

        return $DB->get_field(
            'external_services',
            'id',
            ['shortname' => self::WEB_SERVICE_SHORTNAME],
            MUST_EXIST
        );
    }

    /**
     * Determines if web services are enabled globally.
     *
     * @return bool True, if web services are enabled, false otherwise
     * @throws \dml_exception
     */
    public static function is_webservices_enabled(): bool {
        return get_config('core', 'enablewebservices') == true;
    }

    /**
     * Determines if the web service protocol "REST" is enabled globally.
     *
     * @return bool True, if REST protocol is enabled, false otherwise
     * @throws \dml_exception
     */
    public static function is_webserviceproto_rest_enabled(): bool {
        return stripos(get_config('core', 'webserviceprotocols'), 'rest') !== false;
    }
}
