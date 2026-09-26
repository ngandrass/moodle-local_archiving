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
 * Assignment activity archiving driver
 *
 * @package     archivingmod_assign
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;

use local_archiving\activity_archiving_task;
use local_archiving\local\exception\yield_exception;
use local_archiving\local\type\activity_archiving_task_status;
use local_archiving\local\type\cm_state_fingerprint;
use local_archiving\local\type\task_content_metadata;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

// @codeCoverageIgnoreStart
require_once($CFG->dirroot . '/mod/assign/locallib.php');
// @codeCoverageIgnoreEnd


/**
 * Assignment activity archiving driver
 */
class archivingmod extends \local_archiving\local\driver\archivingmod {
    /** @var \stdClass Course the assignment lives in */
    protected \stdClass $course;

    /** @var \cm_info Info object of the associated course module */
    protected \cm_info $cm;

    /** @var int ID of the targeted assignment */
    protected int $assignmentid;

    /** @var string Short-name of the web service the external worker uses for communication with Moodle */
    public const WEB_SERVICE_SHORTNAME = 'archivingmod_assign_ws';

    /**
     * Creates a new activity archiving driver instance.
     *
     * @param \context_module $context
     * @throws \moodle_exception
     */
    public function __construct(\context_module $context) {
        parent::__construct($context);

        // Try to get course, cm info, and assignment.
        [$this->course, $this->cm] = get_course_and_cm_from_cmid($this->cmid, 'assign');
        if (empty($this->cm)) {
            throw new \moodle_exception('invalid_cmid', 'archivingmod_assign'); // @codeCoverageIgnore
        }
        if ($this->course->id != $this->courseid) {
            throw new \moodle_exception('invalid_courseid', 'archivingmod_assign'); // @codeCoverageIgnore
        }
        $this->assignmentid = $this->cm->instance;
    }

    #[\Override]
    public static function is_ready(): bool {
        $config = get_config('archivingmod_assign');

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
        return ['assign'];
    }

    #[\Override]
    public function get_job_create_form(string $handler, \cm_info $cminfo): \local_archiving\form\job_create_form {
        return new form\job_create_form($handler, $cminfo);
    }

    #[\Override]
    public function can_be_archived(): bool {
        global $DB;

        // Check if assignment has submitted submissions.
        if (
            !$DB->record_exists('assign_submission', [
                'assignment' => $this->assignmentid,
                'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            ])
        ) {
            return false;
        }

        return true;
    }

    #[\Override]
    public function execute_task(activity_archiving_task $task): void {
        if ($task->get_status(usecached: true) == activity_archiving_task_status::UNINITIALIZED) {
            $task->set_status(activity_archiving_task_status::CREATED);
        }

        if ($task->get_status(usecached: true) == activity_archiving_task_status::CREATED) {
            // Prepare access to assignment and webservice.
            $assignmanager = assignment_manager::from_context($task->get_context());
            $submissions = $assignmanager->get_submissions();

            $wstoken = $task->create_webservice_token(
                webserviceid: self::get_webserviceid(),
                userid: get_admin()->id,
                lifetimesec: get_config('local_archiving', 'job_timeout_min') * MINSECS
            );

            // Persist metadata.
            $job = $task->get_job();
            $job->set_metadata_entry('num_attempts', count($submissions));

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
                submissionids: array_keys($submissions)
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
        $assignmentmanager = assignment_manager::from_context($task->get_context());

        $res = [];
        foreach ($assignmentmanager->get_submissions() as $submission) {
            $res[] = new task_content_metadata(
                taskid: $task->get_id(),
                userid: $submission->userid,
                reftable: 'assign_submission',
                refid: $submission->submissionid,
                summary: null
            );
        }

        return $res;
    }

    /**
     * Creates a new fingerprint for the current state of the referenced course
     * module.
     *
     * Those fingerprints are used to determine if the course module has changed
     * since the last archive job. For information on cm_state_fingerprints and
     * their creation, see the cm_state_fingerprint class documentation.
     *
     * @return cm_state_fingerprint Fingerprint for the current state of the
     * referenced course module
     * @throws \JsonException
     * @throws \coding_exception
     * @throws \dml_exception
     */
    #[\Override]
    public function fingerprint(): cm_state_fingerprint {
        global $DB;

        // Get the latest modification time of any submission indide this assignment as well as the assignment itself.
        $assignmenttimemodified = $DB->get_field('assign', 'timemodified', ['id' => $this->assignmentid], MUST_EXIST);

        $submissiontimemodified = $DB->get_field_sql(
            "SELECT MAX(timemodified) FROM {assign_submission} WHERE assignment = :assignmentid",
            ['assignmentid' => $this->assignmentid],
            MUST_EXIST
        );

        // Calculate the fingerprint.
        return cm_state_fingerprint::generate([
            'assignmenttimemodified' => $assignmenttimemodified,
            'submissiontimemodified' => $submissiontimemodified,
        ]);
    }

    /**
     * Retrieves the ID of the self::WEB_SERVICE_SHORTNAME web service
     *
     * @return int ID of the web service
     * @throws \dml_exception If the web service could not be found (should never happen,
     * but who knows all the weird states a Moodle instance can be in ...)
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
