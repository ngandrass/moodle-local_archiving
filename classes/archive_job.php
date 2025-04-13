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
 * An archive job
 *
 * @package     local_archiving
 * @category    driver
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use local_archiving\driver\mod\archivingmod;
use local_archiving\driver\mod\task;
use local_archiving\exception\yield_exception;
use local_archiving\type\archive_job_status;
use local_archiving\type\db_table;
use local_archiving\util\mod_util;
use local_archiving\util\plugin_util;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * An asynchronous archive job. Holds everything related to a single job that
 * consists of multiple stages and tasks.
 */
class archive_job {

    /** @var int ID of the archive job this task is associated with */
    protected int $id;

    /** @var \context_module Moodle context this job is run in */
    protected \context_module $context;

    /** @var int ID of the course this job is run in */
    protected int $courseid;

    /** @var int ID of the course module this job is run for */
    protected int $cmid;

    /** @var int ID of the user that owns this job */
    protected int $userid;

    /** @var int Unix timestamp of creation time */
    protected int $timecreated;

    /** @var \stdClass|null Job settings object (lazy-loaded) */
    protected ?\stdClass $settings;

    /**
     * Constructs an archive job instance. This does not create the job in the
     * database. To create a new or retrieve an existing job use the respective
     * static functions.
     *
     * @param int $jobid ID of this archive job
     * @param \context_module $context Moodle context this archive job is run in
     * @param int $userid ID of the user that owns this job
     * @param int $timecreated Unix timestamp of creation time
     */
    protected function __construct(int $jobid, \context_module $context, int $userid, int $timecreated) {
        $this->id = $jobid;
        $this->context = $context;
        $this->courseid = $context->get_course_context()->instanceid;
        $this->cmid = $context->instanceid;
        $this->userid = $userid;
        $this->timecreated = $timecreated;
        $this->settings = null;
    }

    /**
     * Creates a new archive job in the database and returns the respective
     * instance
     *
     * @param \context $context Context this job is run in
     * @param int $userid ID of the user that owns this job
     * @return archive_job Created archive job instance
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create(
        \context $context,
        int $userid,
        \stdClass $settings
    ): archive_job {
        global $DB;

        // Check context.
        if (!($context instanceof \context_module)) {
            throw new \moodle_exception('invalid_context', 'local_archiving');
        }

        // Create object.
        $now = time();
        $id = $DB->insert_record(db_table::JOB, [
            'contextid' => $context->id,
            'userid' => $userid,
            'status' => archive_job_status::STATUS_UNINITIALIZED,
            'settings' => json_encode($settings),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return new self($id, $context, $userid, $now);
    }

    /**
     * Retrieves an archive job by its ID
     *
     * @param int $jobid The ID of the job to retrieve
     * @return archive_job Archive job instance
     *
     * @throws \dml_exception
     */
    public static function get_by_id(int $jobid): archive_job {
        global $DB;

        $job = $DB->get_record(db_table::JOB, ['id' => $jobid], '*', MUST_EXIST);
        $context = \context::instance_by_id($job->contextid);

        if (!($context instanceof \context_module)) {
            throw new \moodle_exception('invalid_context', 'local_archiving');
        }

        return new self($job->id, $context, $job->userid, $job->timecreated);
    }

    /**
     * Retrieves the unique lock resource identifier for this job
     *
     * @return string Resource identifier for this job
     */
    protected function get_lock_resource(): string {
        return "jobid_{$this->id}";
    }

    /**
     * Tries to acquire a lock for this archive job. Raises an exception if lock
     * could not be acquired after $timeoutsec by default.
     *
     * ATTENTION: Caller takes ownership of lock and is responsible for unlocking!
     *
     * @param bool $timeouterror If true, a failure to acquire a lock will throw an exception
     * @param int $timeoutsec Number of seconds to wait for the resource to become available
     * @return bool|\core\lock\lock Lock object if acquired successfully, false otherwise
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function lock(bool $timeouterror = true, int $timeoutsec = 15) {
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_archiving_archive_job');
        $jobtimeoutmin = get_config('local_archiving', 'job_timeout_min');

        if (!$lock = $lockfactory->get_lock(
            $this->get_lock_resource(),
            $timeoutsec,
            ($jobtimeoutmin ?: 6 * 60) * 60
        )) {
            mtrace("Failed to acquire lock for '{$this->get_lock_resource()}' after {$timeoutsec} seconds.");
            if ($timeouterror) {
                throw new \moodle_exception('locktimeout');
            }
        }

        return $lock;
    }

    /**
     * Tries to acquire a lock for this archive job. Will return immediately
     * without an error if lock could not be acquired.
     *
     * ATTENTION: Caller takes ownership of lock and is responsible for releasing!
     *
     * @return bool|\core\lock\lock Lock object if acquired successfully, false otherwise
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function try_lock() {
        return $this->lock(false, 0);
    }

    /**
     * Prepares this job and pushes it to the task queue, thereby scheduling it
     * for execution
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function enqueue(): void {
        if ($this->is_completed()) {
            throw new \moodle_exception('completed_job_cant_be_started_again', 'local_archiving');
        }

        $task = \local_archiving\task\process_archive_job::create($this);
        \core\task\manager::queue_adhoc_task($task);
        $this->set_status(archive_job_status::STATUS_QUEUED);
    }

    /**
     * Main processing loop. Once the scheduler picks this job from the queue,
     * this function is called. It will be called periodically until this job
     * reached a final state, as indicated by is_completed().
     *
     * Do not perform active waiting here, but instead yield by returning in
     * order to free up resources. This method will be automatically called
     * again in the future.
     *
     * @param bool $failonlocktimeout If true, an exception will be thrown if
     * the lock could not be acquired after a given timeout.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function execute(bool $failonlocktimeout = false): void {
        // Acquire lock for job execution.
        $lock = $this->lock($failonlocktimeout);
        if (!$lock) {
            return;
        }

        $status = $this->get_status();

        /*
         * We are sequentially processing through all job stages here. We work
         * on a local copy $status that is persisted once the try-catch block is
         * either completed or one part of the code threw a yield_exception,
         * hereby persisting the current status, releasing the lock and giving
         * back control to the task scheduler.
         */
        try {
            // Do not process uninitialized jobs.
            if ($status == archive_job_status::STATUS_UNINITIALIZED) {
                throw new \moodle_exception('invalid_archive_job_state', 'local_archiving');
            }

            // Timeout if required.
            if ($this->is_overdue()) {
                // TODO: Call cleanup logic.
                $status = archive_job_status::STATUS_TIMEOUT;
                throw new \moodle_exception('archive_job_timed_out', 'local_archiving');
            }

            // Queued -> Processing.
            if ($status == archive_job_status::STATUS_QUEUED) {
                // TODO: Create activity archiving task.
                $status = archive_job_status::STATUS_PROCESSING;
            }

            // Processing -> Activity archiving.
            if ($status == archive_job_status::STATUS_PROCESSING) {
                $this->create_activity_archiving_task();

                // Update job status early because task execution can take some time ...
                $status = archive_job_status::STATUS_ACTIVITY_ARCHIVING;
                $this->set_status($status);
            }

            // Activity archiving -> Post processing.
            if ($status == archive_job_status::STATUS_ACTIVITY_ARCHIVING) {
                $driver = $this->activity_archiving_driver();
                $driver->execute_all_tasks_for_job($this->get_id());

                if ($driver->is_all_tasks_for_job_completed($this->get_id())) {
                    $status = archive_job_status::STATUS_POST_PROCESSING;
                } else {
                    throw new yield_exception();
                }
            }

            // Post processing -> Store.
            if ($status == archive_job_status::STATUS_POST_PROCESSING) {
                // TODO: Do we need post processing?
                $status = archive_job_status::STATUS_STORE;

                // Yield for asynchronous archive store task to complete.
                throw new yield_exception();
            }

            // Store -> Cleanup.
            if ($status == archive_job_status::STATUS_STORE) {
                $status = archive_job_status::STATUS_CLEANUP;
            }

            // Cleanup -> Completed.
            if ($status == archive_job_status::STATUS_CLEANUP) {
                $status = archive_job_status::STATUS_COMPLETED;
            }
        } catch (\Exception $e) {
            // Catch the yield silently and let everything else bubble up.
            if (!$e instanceof yield_exception) {
                $status = archive_job_status::STATUS_FAILURE;
                throw $e;
            }
        } finally {
            $this->set_status($status);
            $lock->release();
        }
    }

    /**
     * Returns a new instance of the activity archiving driver for this job
     *
     * @return archivingmod Activity archiving driver instance
     * @throws \moodle_exception
     */
    protected function activity_archiving_driver(): archivingmod {
        $cminfo = mod_util::get_cm_info($this->context);
        $driverclass = plugin_util::get_archiving_driver_for_cm($cminfo->modname);

        if (!$driverclass) {
            throw new \moodle_exception('no_supported_activity_archiving_driver_found', 'local_archiving');
        }

        return new $driverclass($this->context);
    }

    /**
     * Creates a new activity archiving task using the respective activity
     * archiving driver
     *
     * @return task|null Activity archiving task or null if no task could be created
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function create_activity_archiving_task(): ?task {
        $driver = $this->activity_archiving_driver();
        if (!$driver->can_be_archived()) {
            // Handle this as a hard fail for now but maybe we want to try again here?
            throw new \moodle_exception('activity_not_ready_for_archiving', 'local_archiving');
        }

        return $driver->create_task($this, $this->get_settings());
    }

    /**
     * Deletes an archive job and everything that is associated with it from the
     * database
     *
     * @throws \dml_exception
     */
    public function delete(): void {
        global $DB;

        // TODO: Stop and cleanup potentially scheduled / running tasks.

        // TODO: Free files and potentially other stuff.

        $DB->delete_records(db_table::METADATA, ['jobid' => $this->id]);
        $DB->delete_records(db_table::FILE, ['jobid' => $this->id]);
        $DB->delete_records(db_table::JOB, ['id' => $this->id]);
    }

    /**
     * Retrieves the internal ID of this job
     *
     * @return int ID of this job
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Retrieves the Moodle context this job is run in
     *
     * @return \context_module Moodle context this job is run in
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * Retrieves the ID of the user that owns this job
     *
     * @return int ID of the user that owns this job
     */
    public function get_userid(): int {
        return $this->userid;
    }

    /**
     * Retrieves the current job status
     *
     * @return int Current job status
     */
    public function get_status(): int {
        global $DB;

        try {
            return $DB->get_field(db_table::JOB, 'status', ['id' => $this->id], MUST_EXIST);
        } catch (\dml_exception $e) {
            return archive_job_status::STATUS_UNKNOWN;
        }
    }

    /**
     * Changes the current job status to the given value
     *
     * @param int $status New status
     * @return void
     * @throws \dml_exception
     */
    public function set_status(int $status): void {
        global $DB;

        $DB->update_record(db_table::JOB, [
            'id' => $this->id,
            'status' => $status,
            'timemodified' => time(),
        ]);
        mtrace("Job status updated: ".archive_job_status::get_status_name($status)." ({$status})");
    }

    /**
     * Determines if this job can be further processed by calling its execute()
     * function or if it has reached a final state
     *
     * @return bool True if this job reached a final state
     */
    public function is_completed(): bool {
        switch ($this->get_status()) {
            case archive_job_status::STATUS_COMPLETED:
            case archive_job_status::STATUS_TIMEOUT:
            case archive_job_status::STATUS_FAILURE:
                return true;
            default:
                return false;
        }
    }

    /**
     * Determines if this job is overdue and should be timed out if not already done
     *
     * @return bool True if this jobs lifetime surpassed its timeout
     * @throws \dml_exception
     */
    public function is_overdue(): bool {
        $jobtimeoutsec = get_config('local_archiving', 'job_timeout_min') * 60;
        if (time() > $this->timecreated + $jobtimeoutsec) {
            return true;
        }

        return false;
    }

    /**
     * Calculates an progress approximation for this job. Values range from 0
     * to 100 percent. The progress value is no indicator for job status!
     *
     * @return ?int Job progress approximation in percent (0 to 100)) or null if
     * no progress can be calculated
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_progress(): ?int {
        switch ($this->get_status()) {
            case archive_job_status::STATUS_QUEUED:
            case archive_job_status::STATUS_PROCESSING:
                return 0;
            case archive_job_status::STATUS_ACTIVITY_ARCHIVING:
                $tasks = task::get_by_jobid($this->id);
                $total = array_reduce($tasks, fn ($carry, $task) => $carry + $task->get_progress(), 0);
                return 0.6 * ($total / count($tasks));
            case archive_job_status::STATUS_POST_PROCESSING:
                return 60;
            case archive_job_status::STATUS_STORE:
                return 60 + 20;  // TODO: Implement.
            case archive_job_status::STATUS_CLEANUP:
                return 99;
            case archive_job_status::STATUS_COMPLETED:
                return 100;
        }

        return null;
    }

    /**
     * Retrieves the job settings object. Settings are are immutable and available
     * only while a job is active (not completed yet).
     *
     * @return \stdClass Job settings object
     * @throws \dml_exception
     */
    public function get_settings(): \stdClass {
        if ($this->settings === null) {
            global $DB;

            $settingsjson = $DB->get_field(db_table::JOB, 'settings', ['id' => $this->id], MUST_EXIST);
            if (!$settingsjson) {
                // If no task specific settings are present, create empty class to prevent future DB queries.
                $this->settings = new \stdClass();
            } else {
                $this->settings = json_decode($settingsjson);
            }
        }

        return $this->settings;
    }

}
