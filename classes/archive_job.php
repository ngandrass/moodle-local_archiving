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
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use local_archiving\driver\archivingmod;
use local_archiving\exception\yield_exception;
use local_archiving\logging\job_logger;
use local_archiving\type\archive_filename_variable;
use local_archiving\type\archive_job_status;
use local_archiving\type\db_table;
use local_archiving\type\log_level;
use local_archiving\util\mod_util;
use local_archiving\util\plugin_util;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * An asynchronous archive job. Holds everything related to a single job that
 * consists of multiple stages and tasks.
 */
class archive_job {

    /** @var int ID of the course this job is run in */
    protected int $courseid;

    /** @var int ID of the course module this job is run for */
    protected int $cmid;

    /** @var \stdClass|null Job settings object (lazy-loaded) */
    protected ?\stdClass $settings;

    /** @var job_logger|null Logger instance (lazy-loaded) */
    protected ?job_logger $logger;

    /**
     * Constructs an archive job instance. This does not create the job in the
     * database. To create a new or retrieve an existing job use the respective
     * static functions.
     *
     * @param int $id ID of this archive job
     * @param \context_module $context Moodle context this archive job is run in
     * @param int $userid ID of the user that owns this job
     * @param int $timecreated Unix timestamp of creation
     * @param archive_job_status $status Current job status
     */
    protected function __construct(
        /** @var int ID of this archive job */
        protected readonly int $id,
        /** @var \context_module Moodle context this archive job is run in */
        protected readonly \context_module $context,
        /** @var int $userid ID of the user that owns this job */
        protected readonly int $userid,
        /** @var int $timecreated Unix timestamp of creation */
        protected readonly int $timecreated,
        /** @var archive_job_status $status Current job status */
        protected archive_job_status $status,
    ) {
        $this->courseid = $context->get_course_context()->instanceid;
        $this->cmid = $context->instanceid;
        $this->settings = null;
        $this->logger = null;
    }

    /**
     * Creates a logger instance that is tied to this job.
     *
     * All log entries created through this logger will automatically be linked
     * to this archive job.
     *
     * @return job_logger Logger instance
     * @throws \dml_exception
     */
    public function get_logger(): job_logger {
        if ($this->logger instanceof job_logger) {
            return $this->logger;
        }

        $this->logger = new job_logger($this->id);

        return $this->logger;
    }

    /**
     * Creates a new archive job in the database and returns the respective
     * instance
     *
     * @param \context $context Context this job is run in
     * @param int $userid ID of the user that owns this job
     * @param \stdClass $settings Job settings object
     * @param bool $cleansettings If true, the settings object will be cleared from any mform stuff
     * @return archive_job Created archive job instance
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create(
        \context $context,
        int $userid,
        \stdClass $settings,
        bool $cleansettings = true
    ): archive_job {
        global $DB;

        // Check context.
        if (!($context instanceof \context_module)) {
            throw new \moodle_exception('invalid_context', 'local_archiving');
        }

        // Clean settings object.
        if ($cleansettings) {
            $settings = (object) array_filter(
                (array) $settings,
                fn ($key) => !str_starts_with($key, 'mform_') && $key !== 'submitbutton',
                ARRAY_FILTER_USE_KEY
            );
        }

        // Create object.
        $now = time();
        $jobstatus = archive_job_status::UNINITIALIZED;
        $id = $DB->insert_record(db_table::JOB->value, [
            'contextid' => $context->id,
            'userid' => $userid,
            'status' => $jobstatus->value,
            'settings' => json_encode($settings),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return new self($id, $context, $userid, $now, $jobstatus);
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

        $job = $DB->get_record(db_table::JOB->value, ['id' => $jobid], '*', MUST_EXIST);
        $context = \context::instance_by_id($job->contextid);

        if (!($context instanceof \context_module)) {
            throw new \moodle_exception('invalid_context', 'local_archiving');
        }

        return new self($job->id, $context, $job->userid, $job->timecreated, archive_job_status::from($job->status));
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
    protected function lock(bool $timeouterror = true, int $timeoutsec = 15): bool|\core\lock\lock {
        // Create a static lock_factory to prevent double-locking issues with postgres driver (See MDL-81731).
        static $lockfactory;
        if (!$lockfactory instanceof \core\lock\lock_factory) {
            $lockfactory = \core\lock\lock_config::get_lock_factory('local_archiving_archive_job');
        }

        $jobtimeoutmin = get_config('local_archiving', 'job_timeout_min');

        if (!$lock = $lockfactory->get_lock(
            $this->get_lock_resource(),
            $timeoutsec,
            ($jobtimeoutmin ?: 6 * 60) * 60
        )) {
            $this->get_logger()->warn("Failed to acquire lock for '{$this->get_lock_resource()}' after {$timeoutsec} seconds.");
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
        $this->set_status(archive_job_status::QUEUED);
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

        /*
         * We are sequentially processing through all job stages here.
         * Stages are either completed or a yield_exception was thrown, hereby
         * releasing the lock and giving the control back to the task scheduler.
         */
        try {
            $starttime = time();
            $this->get_logger()->debug("v---------- Starting execution cycle {$starttime} ----------v");

            // Do not process uninitialized jobs.
            if ($this->get_status(usecached: true) == archive_job_status::UNINITIALIZED) {
                throw new \moodle_exception('invalid_archive_job_state', 'local_archiving');
            }

            // Timeout if required.
            if ($this->is_overdue()) {
                // Update task status.
                $this->set_status(archive_job_status::TIMEOUT);

                // Stop all running tasks.
                foreach (activity_archiving_task::get_by_jobid($this->id) as $task) {
                    $task->cancel();
                }

                // Perform cleanup and die.
                $this->cleanup();
                throw new \moodle_exception('archive_job_timed_out', 'local_archiving');
            }

            // Queued -> Pre-Processing.
            if ($this->get_status(usecached: true) == archive_job_status::QUEUED) {
                $this->get_logger()->trace(
                    "Initialized new archive job. Settings: \r\n".json_encode($this->get_settings(), JSON_PRETTY_PRINT)
                );

                $this->set_status(archive_job_status::PRE_PROCESSING);
            }

            // Pre-Processing -> Activity archiving.
            if ($this->get_status(usecached: true) == archive_job_status::PRE_PROCESSING) {
                // Create activity archiving task.
                $task = $this->create_activity_archiving_task();
                if (!$task) {
                    $this->get_logger()->fatal('Failed to create activity archiving task.');
                    throw new \moodle_exception('activity_archiving_task_creation_failed', 'local_archiving');
                }

                $drivername = $this->activity_archiving_driver()->get_plugin_name();
                $this->get_logger()->info('Created activity archiving task (driver: '.$drivername.')');
                $this->set_metadata_entry('activity_archiving_driver', $drivername);

                // Check if we already have a task with the same fingerprint.
                $this->get_logger()->trace('Activity fingerprint: '.$task->get_fingerprint()->get_raw_value());
                if (activity_archiving_task::fingerprint_exists($task->get_context(), $task->get_fingerprint())) {
                    $this->get_logger()->info(
                        'An archive for the current state of the activity already was created successfully. Continuing anyway ...'
                    );
                }

                // Create backup tasks if requested.
                if ($this->get_setting('export_course_backup')) {
                    $backup = backup_manager::initiate_course_backup($this->courseid, $this->userid);
                    $this->set_metadata_entry('course_backup_id', $backup->backupid);
                    $this->get_logger()->info(
                        "Requested a Moodle course backup (#{$backup->backupid}): {$backup->filename}"
                    );
                }
                if ($this->get_setting('export_cm_backup')) {
                    $backup = backup_manager::initiate_activity_backup($this->cmid, $this->userid);
                    $this->set_metadata_entry('cm_backup_id', $backup->backupid);
                    $this->get_logger()->info(
                        'Requested a Moodle activity backup (#'.$backup->backupid.'): '.$backup->filename
                    );
                }

                // Gather and save archive task contents metadata.
                $taskcontent = $this->activity_archiving_driver()->get_task_content_metadata($task);
                if (count($taskcontent) > 0) {
                    $task->store_task_content_metadata($taskcontent);
                    $this->get_logger()->info('Stored '.count($taskcontent).' task content metadata entries.');
                    if ($this->get_logger()->loglevel <= log_level::TRACE) {
                        $this->get_logger()->trace("Task content metadata:\r\n".json_encode($taskcontent, JSON_PRETTY_PRINT));
                    }
                } else {
                    $this->get_logger()->warn('No task content metadata provided by the archiving driver!');
                }

                $this->set_status(archive_job_status::ACTIVITY_ARCHIVING);
            }

            // Activity archiving -> Backup collection.
            if ($this->get_status(usecached: true) == archive_job_status::ACTIVITY_ARCHIVING) {
                $driver = $this->activity_archiving_driver();
                $driver->execute_all_tasks_for_job($this->get_id());

                if ($driver->is_all_tasks_for_job_completed($this->get_id())) {
                    $this->set_status(archive_job_status::BACKUP_COLLECTION);
                } else {
                    $this->get_logger()->info('Not all activity archiving tasks are finished yet. Waiting ...');
                    throw new yield_exception();
                }
            }

            // Backup collection -> Post processing.
            if ($this->get_status(usecached: true) == archive_job_status::BACKUP_COLLECTION) {
                // Check if we have any backups to collect.
                $backupids = [];
                if ($this->get_setting('export_course_backup')) {
                    $backupids[] = $this->get_metadata_entry('course_backup_id', strict: true);
                }
                if ($this->get_setting('export_cm_backup')) {
                    $backupids[] = $this->get_metadata_entry('cm_backup_id', strict: true);
                }

                // Check backup status.
                $allbackupsready = true;
                foreach ($backupids as $backupid) {
                    $bm = new backup_manager($backupid);

                    if ($bm->is_failed()) {
                        throw new \moodle_exception('backup_failed_id', 'local_archiving', a: $backupid);
                    }
                    if (!$bm->is_finished_successfully()) {
                        $allbackupsready = false;
                        $this->get_logger()->info("Backup #{$backupid} is not finished yet. Waiting ...");
                    }
                }

                if ($allbackupsready) {
                    $this->set_status(archive_job_status::POST_PROCESSING);
                } else {
                    // Not all backups ready yet, yield and wait for them to finish.
                    throw new yield_exception();
                }
            }

            // Post processing -> Store.
            if ($this->get_status(usecached: true) == archive_job_status::POST_PROCESSING) {
                // Check that we have artifacts artifacts.
                $artifacts = [];
                foreach (activity_archiving_task::get_by_jobid($this->id) as $task) {
                    $artifacts = array_merge($artifacts, $task->get_linked_artifacts());
                }

                if (empty($artifacts)) {
                    $this->set_status(archive_job_status::FAILURE);
                    throw new \moodle_exception('no_activity_artifacts_found', 'local_archiving');
                } else {
                    $this->get_logger()->info('Got '.count($artifacts).' artifact(s) from the activity.');
                }

                $this->set_status(archive_job_status::STORE);
            }

            // Store -> Sign.
            if ($this->get_status(usecached: true) == archive_job_status::STORE) {
                // Store artifacts.
                $tasks = activity_archiving_task::get_by_jobid($this->id);
                $storagepath = "job-{$this->id}";

                $driver = \local_archiving\driver\factory::storage_driver($this->get_setting('storage_driver') ?? 'null');
                $this->set_metadata_entry('storage_driver', $driver->get_plugin_name());

                if (!$driver->is_enabled()) {
                    $this->get_logger()->fatal("Artifact storage driver {$driver->get_plugin_name()} is not enabled.");
                    throw new \moodle_exception('artifact_storing_failed', 'local_archiving');
                }

                if (!$driver::is_ready()) {
                    $this->get_logger()->fatal("Artifact storage driver {$driver->get_plugin_name()} is not ready.");
                    throw new \moodle_exception('artifact_storing_failed', 'local_archiving');
                }

                // Activity archiving tasks.
                foreach ($tasks as $task) {
                    foreach ($task->get_linked_artifacts() as $artifact) {
                        $filehandle = $driver->store($this->id, $artifact, $storagepath);
                        $this->get_logger()->info('Stored activity artifact: '.
                            "{$filehandle->filename} (size: ".display_size($filehandle->filesize).") (id: {$filehandle->id})");
                        $task->unlink_artifact($artifact, true);
                    }
                }

                // Backups.
                $backupidkeys = ['course_backup_id', 'cm_backup_id'];
                foreach ($backupidkeys as $backupidkey) {
                    $backupid = $this->get_metadata_entry($backupidkey, strict: false);
                    if ($backupid) {
                        $bm = new backup_manager($backupid);
                        $backupfile = $bm->get_backupfile();

                        if (!$backupfile) {
                            throw new \moodle_exception(
                                'backup_artifact_file_not_found_filename',
                                'local_archiving',
                                a: $bm->get_filename()
                            );
                        }

                        $filehandle = $driver->store($this->id, $backupfile, $storagepath);
                        $this->get_logger()->info('Stored backup: '.
                            "{$filehandle->filename} (size: ".display_size($filehandle->filesize).") (id: {$filehandle->id})");
                        $bm->cleanup();
                    } else {
                        $this->get_logger()->debug("No {$backupidkey} found.");
                    }
                }

                $this->set_status(archive_job_status::SIGN);
            }

            // Sign -> Cleanup.
            if ($this->get_status(usecached: true) == archive_job_status::SIGN) {
                if (!tsp_manager::is_automatic_tsp_signing_enabled()) {
                    $this->get_logger()->info("Automatic TSP signing of job artifacts is disabled, skipping signing step.");
                } else {
                    // @codeCoverageIgnoreStart
                    // Sign all stored files with TSP.
                    $filehandles = file_handle::get_by_jobid($this->id);

                    foreach ($filehandles as $filehandle) {
                        $tspmanager = new tsp_manager($filehandle);
                        if ($tspmanager->wants_tsp_timestamp()) {
                            $tspmanager->timestamp();
                            $this->get_logger()->info(
                                "Created TSP signature for file: {$filehandle->filename} (id: {$filehandle->id})"
                            );
                        } else {
                            $this->get_logger()->warn(
                                "No TSP timestamp requested for file: {$filehandle->filename} (id: {$filehandle->id})"
                            );
                        }
                    }
                    // @codeCoverageIgnoreEnd
                }

                $this->set_status(archive_job_status::CLEANUP);
            }

            // Cleanup -> Completed.
            if ($this->get_status(usecached: true) == archive_job_status::CLEANUP) {
                $this->cleanup();
                $this->set_status(archive_job_status::COMPLETED);
            }
        } catch (\Throwable $e) {
            // Catch the yield silently and let everything else bubble up.
            if (!$e instanceof yield_exception) {
                $this->get_logger()->fatal($e->getMessage());
                $this->set_status(archive_job_status::FAILURE);
                throw $e;
            }
        } finally {
            $this->get_logger()->debug("^---------- Finished execution cycle {$starttime} ----------^");
            $lock->release();
        }
    }

    /**
     * Cleans up anything that can be deleted once a job reached a final state.
     *
     * This method should not be called arbitrarily, but only once we are sure
     * that the job is completed and will never require further processing.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function cleanup(): void {
        // Cleanup temporary settings objects from DB.
        foreach (activity_archiving_task::get_by_jobid($this->id) as $task) {
            $task->clear_settings(force: true);
        }

        $this->clear_settings(force: true);
    }

    /**
     * Returns a new instance of the activity archiving driver for this job
     *
     * @return archivingmod Activity archiving driver instance
     * @throws \moodle_exception
     */
    protected function activity_archiving_driver(): archivingmod {
        $cminfo = mod_util::get_cm_info($this->context);
        $drivername = plugin_util::get_archiving_driver_for_cm($cminfo->modname);

        if (!$drivername) {
            throw new \moodle_exception('no_supported_activity_archiving_driver_found', 'local_archiving');
        }

        return \local_archiving\driver\factory::activity_archiving_driver($drivername, $this->context);
    }

    /**
     * Creates a new activity archiving task using the respective activity
     * archiving driver
     *
     * @return activity_archiving_task|null Activity archiving task or null if no task could be created
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function create_activity_archiving_task(): ?activity_archiving_task {
        $driver = $this->activity_archiving_driver();

        if (!$driver->is_enabled()) {
            throw new \moodle_exception('activity_archiving_driver_not_enabled', 'local_archiving');
        }

        if (!$driver::is_ready()) {
            throw new \moodle_exception('component_not_ready', 'local_archiving');
        }

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
     * @throws \moodle_exception
     */
    public function delete(): void {
        global $DB;

        // Handle activity archiving tasks.
        $archivingtasks = activity_archiving_task::get_by_jobid($this->id);
        foreach ($archivingtasks as $task) {
            $task->cancel();
            $task->delete();
        }

        // Delete job artifacts.
        $files = file_handle::get_by_jobid($this->id);
        foreach ($files as $filehandle) {
            // Remove local cache copy if present.
            if ($cachedfile = $filehandle->get_local_file()) {
                $cachedfile->delete();
            }

            // Remove original file from the storage.
            $filehandle->destroy(removefile: true);
        }

        // Delete records from the database.
        $DB->delete_records(db_table::METADATA->value, ['jobid' => $this->id]);
        $DB->delete_records(db_table::TEMPFILE->value, ['jobid' => $this->id]);
        $DB->delete_records(db_table::LOG->value, ['jobid' => $this->id]);
        $DB->delete_records(db_table::JOB->value, ['id' => $this->id]);
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
     * Retrieves the unix timestamp this job was created
     *
     * @return int Unix timestamp of creation time
     */
    public function get_timecreated(): int {
        return $this->timecreated;
    }

    /**
     * Retrieves the unix timestamp this job was last modified
     *
     * @return int Unix timestamp of last modification time
     * @throws \dml_exception
     */
    public function get_timemodified(): int {
        global $DB;
        return $DB->get_field(db_table::JOB->value, 'timemodified', ['id' => $this->id], MUST_EXIST);
    }

    /**
     * Retrieves the current job status
     *
     * @param bool $usecached If true, the cached status will be used instead of querying the database
     *
     * @return archive_job_status Current job status
     */
    public function get_status(bool $usecached = false): archive_job_status {
        global $DB;

        // If we only want the cached status, return it immediately.
        if ($usecached) {
            return $this->status;
        }

        // Update local status value from database.
        try {
            $this->status = archive_job_status::from(
                $DB->get_field(db_table::JOB->value, 'status', ['id' => $this->id], MUST_EXIST)
            );
        } catch (\dml_exception $e) {
            $this->status = archive_job_status::UNKNOWN;
        }

        return $this->status;
    }

    /**
     * Changes the current job status to the given value
     *
     * @param archive_job_status $status New status
     * @return void
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public function set_status(archive_job_status $status): void {
        global $DB;

        // Update status value in the database.
        $DB->update_record(db_table::JOB->value, [
            'id' => $this->id,
            'status' => $status->value,
            'timemodified' => time(),
        ]);

        // Update local status copy and log.
        if ($this->status != $status) {
            $this->status = $status;
            $this->get_logger()->info(
                "Job status: ".$status->name()." ({$status->value})"
            );
        }
    }

    /**
     * Determines if this job can be further processed by calling its execute()
     * function or if it has reached a final state
     *
     * @return bool True if this job reached a final state
     */
    public function is_completed(): bool {
        switch ($this->get_status()) {
            case archive_job_status::COMPLETED:
            case archive_job_status::DELETED:
            case archive_job_status::TIMEOUT:
            case archive_job_status::FAILURE:
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
            case archive_job_status::UNINITIALIZED:
            case archive_job_status::QUEUED:
            case archive_job_status::PRE_PROCESSING:
                return 0;
            case archive_job_status::ACTIVITY_ARCHIVING:
                $tasks = activity_archiving_task::get_by_jobid($this->id);
                if (count($tasks) == 0) {
                    return 60;
                } else {
                    $total = array_reduce($tasks, fn ($carry, $task) => $carry + $task->get_progress(), 0);
                    return 0.6 * ($total / count($tasks));
                }
            case archive_job_status::POST_PROCESSING:
            case archive_job_status::BACKUP_COLLECTION:
                return 60;
            case archive_job_status::STORE:
                return 60 + 20;  // TODO (MDL-0): Implement proper status reporting for storing step.
            case archive_job_status::SIGN:
                return 90;
            case archive_job_status::CLEANUP:
                return 95;
            case archive_job_status::COMPLETED:
            case archive_job_status::DELETED:
                return 100;
            default:
                return null; // @codeCoverageIgnore
        }
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

            $settingsjson = $DB->get_field(db_table::JOB->value, 'settings', ['id' => $this->id], MUST_EXIST);
            if (!$settingsjson) {
                // If no task specific settings are present, create empty class to prevent future DB queries.
                $this->settings = new \stdClass();
            } else {
                $this->settings = json_decode($settingsjson);
            }
        }

        return $this->settings;
    }

    /**
     * Retrieves a job setting by its key
     *
     * @param string $key Key of the setting
     * @param bool $strict If true, an exception will be thrown if the setting does not exist
     * @return mixed Setting value or null if not found
     * @throws \coding_exception Invalid setting key
     * @throws \dml_exception
     */
    public function get_setting(string $key, bool $strict = false) {
        $settings = $this->get_settings();

        if (property_exists($settings, $key)) {
            return $settings->{$key};
        } else {
            if ($strict) {
                throw new \coding_exception('invalid_job_setting_requested', 'local_archiving');
            }
        }

        return null;
    }

    /**
     * Clears this jobs settings inside the database. This option is irreversible.
     *
     * @param bool $force If true, force clear job settings even if the job is not completed yet
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception If the job it not yet completed
     */
    public function clear_settings(bool $force = false): void {
        global $DB;

        if (!$this->is_completed() && !$force) {
            throw new \moodle_exception('job_settings_cant_be_cleared', 'local_archiving');
        }

        $DB->update_record(db_table::JOB->value, [
            'id' => $this->id,
            'settings' => null,
        ]);

        $this->settings = new \stdClass();
    }

    /**
     * Sets a metadata entry for this job. If the entry already exists, it will be updated.
     *
     * @param string $key Key of the metadata entry
     * @param mixed $value Value of the metadata entry. Will be JSON-encoded before storing.
     * @return void
     * @throws \dml_exception
     */
    public function set_metadata_entry(string $key, mixed $value): void {
        global $DB;

        // Check if metadata entry already exists.
        $recordid = $DB->get_field(db_table::METADATA->value, 'id', [
            'jobid' => $this->id,
            'datakey' => $key,
        ], IGNORE_MISSING);

        // Update or create record.
        if ($recordid) {
            // Update existing entry.
            $DB->update_record(db_table::METADATA->value, [
                'id' => $recordid,
                'datavalue' => json_encode($value),
            ]);
        } else {
            // Insert new entry.
            $DB->insert_record(db_table::METADATA->value, [
                'jobid' => $this->id,
                'datakey' => $key,
                'datavalue' => json_encode($value),
            ]);
        }
    }

    /**
     * Retrieves a metadata entry by its key. If the entry does not exist,
     * null will be returned. If $strict is true, an exception will be thrown
     * if the entry does not exist.
     *
     * @param string $key Key of the metadata entry to retrieve
     * @param bool $strict If true, an exception will be thrown if the entry does not exist
     * @return mixed Metadata value or null if not found
     * @throws \coding_exception No metadata entry with the given key exists
     * @throws \dml_exception
     */
    public function get_metadata_entry(string $key, bool $strict = false): mixed {
        global $DB;

        // Check if metadata entry exists.
        $record = $DB->get_record(db_table::METADATA->value, [
            'jobid' => $this->id,
            'datakey' => $key,
        ], 'datavalue', IGNORE_MISSING);

        // Return value.
        if ($record) {
            return json_decode($record->datavalue);
        } else {
            if ($strict) {
                throw new \coding_exception('invalid_job_metadata_requested', 'local_archiving');
            }
        }

        return null;
    }

    /**
     * Retrieves all metadata entries for this job
     *
     * @return array All metadata entries stored for this job as key-value pairs
     * @throws \dml_exception
     */
    public function get_metadata_entries(): array {
        global $DB;

        // Retrieve all metadata entries for this job.
        $entries = $DB->get_records(db_table::METADATA->value, ['jobid' => $this->id], '', 'datakey, datavalue');
        $result = [];
        foreach ($entries as $entry) {
            $result[$entry->datakey] = json_decode($entry->datavalue);
        }

        return $result;
    }

    /**
     * Generates the archive name prefix for this job.
     *
     * In the simple case of having a single artifact file from the activitty
     * archiving stage, the prefix can diretcly be used as a file name. If
     * multiple artifact files are created, please add an apropriate suffix to
     * the prefix to make it unique.
     *
     * Note: File extensions are excluded and must be added by the caller in
     * order to generate a final file name!
     *
     * @return string Archive name prefix for this job
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function generate_archive_name_prefix(): string {
        // Validate pattern.
        $pattern = $this->get_setting('archive_filename_pattern', strict: true);
        if (!storage::is_valid_filename_pattern(
            $pattern,
            archive_filename_variable::values(),
            storage::FILENAME_FORBIDDEN_CHARACTERS
        )) {
            throw new \invalid_parameter_exception(get_string('error_invalid_filename_pattern', 'local_archiving'));
        }

        // Prepare data.
        $course = get_course($this->courseid);
        $cm = mod_util::get_cm_info($this->get_context());
        $data = [
            'courseid' => $course->id ?: 0,
            'coursename' => $course->fullname ?: 'null',
            'courseshortname' => $course->shortname ?: 'null',
            'cmid' => $cm->id ?: 0,
            'cmtype' => $cm->modname ?: 'null',
            'cmname' => $cm->name ?: 'null',
            'date' => date('Y-m-d'),
            'time' => date('H-i-s'),
            'timestamp' => time(),
        ];

        // Substitute variables.
        $filename = $pattern;
        foreach ($data as $key => $value) {
            $filename = preg_replace(
                '/\$\{\s*'.$key.'\s*\}/m',
                substr($value, 0, storage::FILENAME_VARIABLE_MAX_LENGTH),
                $filename
            );
        }

        return storage::sanitize_filename($filename);
    }

}
