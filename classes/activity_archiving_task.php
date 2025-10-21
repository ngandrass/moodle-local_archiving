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
 * An asynchronous activity archiving task
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use local_archiving\driver\archivingmod;
use local_archiving\exception\yield_exception;
use local_archiving\logging\task_logger;
use local_archiving\type\activity_archiving_task_status;
use local_archiving\type\cm_state_fingerprint;
use local_archiving\type\db_table;
use local_archiving\type\filearea;
use local_archiving\type\task_content_metadata;
use local_archiving\util\plugin_util;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * An asynchronous activity archiving task. Holds all information and state for a single
 * activity archiving task.
 */
final class activity_archiving_task {
    /** @var archive_job|null Instance of the associated archive_job (lazy-loaded) */
    protected ?archive_job $archivejob;

    /** @var archivingmod|null Instance of the associated activity archiving driver (lazy-loaded) */
    protected ?archivingmod $archivingmod;

    /** @var ?\stdClass Optional task specific settings (lazy-loaded) */
    protected ?\stdClass $settings;

    /** @var task_logger|null Logger instanze (lazy-loaded) */
    protected ?task_logger $logger;

    /**
     * Builds a new activity archiving task object. This does not create entries
     * in the database. For creating or loading tasks use the respective static
     * methods.
     *
     * @param int $taskid ID of this activity archiving task
     * @param int $jobid ID of the archive job this task is associated with
     * @param \context_module $context Moodle context this task is run in
     * @param cm_state_fingerprint $fingerprint Fingerprint of the course module at task creation
     * @param int $userid ID of the user that owns this task
     * @param string $archivingmodname Name of the activity archiving driver that handles this task
     * @param activity_archiving_task_status $status Status of this task
     */
    protected function __construct(
        /** @var int $taskid ID of this activity archiving task */
        protected readonly int $taskid,
        /** @var int $jobid ID of the archiving job this task is associated with */
        protected readonly int $jobid,
        /** @var \context_module Moodle context this task is run in */
        protected readonly \context_module $context,
        /** @var cm_state_fingerprint Fingerprint of the course module at task creation */
        protected readonly cm_state_fingerprint $fingerprint,
        /** @var int $userid ID of the user that owns this task */
        protected readonly int $userid,
        /** @var string $archivingmodname Name of the activity archiving driver that handles this task */
        protected readonly string $archivingmodname,
        /** @var activity_archiving_task_status Status of this task */
        protected activity_archiving_task_status $status
    ) {
        $this->archivejob = null;
        $this->archivingmod = null;
        $this->settings = null;
        $this->logger = null;
    }

    /**
     * Creates a logger instance that is tied to this activity archiving task.
     *
     * All log entries created through this logger will automatically be linked
     * to this task.
     *
     * @return task_logger Logger instance
     * @throws \dml_exception
     */
    public function get_logger(): task_logger {
        if ($this->logger instanceof task_logger) {
            return $this->logger;
        }

        $this->logger = new task_logger($this->jobid, $this->taskid);

        return $this->logger;
    }

    /**
     * Creates a new activity archiving task object and inserts it into the
     * database. This method does not execute the task.
     *
     * @param int $jobid ID of the archive job this task is associated with
     * @param \context_module $context Moodle context this task is run in
     * @param cm_state_fingerprint $cmfingerprint Fingerprint of the course module at task creation
     * @param int $userid ID of the user that owns this task
     * @param string $archivingmodname Name of the activity archiving driver that handles this task
     * @param ?\stdClass $settings Optional task specific settings
     * @param activity_archiving_task_status $status Status of the task
     * @return activity_archiving_task The created task object
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create(
        int $jobid,
        \context_module $context,
        cm_state_fingerprint $cmfingerprint,
        int $userid,
        string $archivingmodname,
        ?\stdClass $settings = null,
        activity_archiving_task_status $status = activity_archiving_task_status::UNINITIALIZED
    ): activity_archiving_task {
        global $DB;

        // Validate input.
        if (!plugin_util::is_subplugin_installed('archivingmod', $archivingmodname)) {
            throw new \moodle_exception('invalid_archivingmod', 'local_archiving');
        }

        // Create instance in DB.
        $now = time();
        $taskid = $DB->insert_record(db_table::ACTIVITY_TASK->value, [
            'jobid' => $jobid,
            'archivingmod' => $archivingmodname,
            'contextid' => $context->id,
            'fingerprint' => $cmfingerprint->get_raw_value(),
            'userid' => $userid,
            'status' => $status->value,
            'progress' => 0,
            'settings' => $settings ? json_encode($settings) : null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return new self($taskid, $jobid, $context, $cmfingerprint, $userid, $archivingmodname, $status);
    }

    /**
     * Loads an existing activity archiving task object from the database
     *
     * @param int $taskid ID of the task to load
     * @return self The loaded task object
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_by_id(int $taskid): activity_archiving_task {
        global $DB;

        $task = $DB->get_record(db_table::ACTIVITY_TASK->value, ['id' => $taskid], '*', MUST_EXIST);
        $context = \context::instance_by_id($task->contextid);

        if (!$context instanceof \context_module) {
            throw new \moodle_exception('invalidcontext', 'local_archiving');
        }

        return new self(
            $task->id,
            $task->jobid,
            $context,
            cm_state_fingerprint::from_raw_value($task->fingerprint),
            $task->userid,
            $task->archivingmod,
            activity_archiving_task_status::from($task->status)
        );
    }

    /**
     * Retrieves all jobs associated with the given jobid
     *
     * @param int $jobid ID of the job to retrieve tasks for
     * @return self[] List of tasks associated with the given jobid
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_by_jobid(int $jobid): array {
        global $DB;

        $tasks = $DB->get_records(db_table::ACTIVITY_TASK->value, ['jobid' => $jobid]);

        $result = [];
        foreach ($tasks as $task) {
            $context = \context::instance_by_id($task->contextid);

            if (!$context instanceof \context_module) {
                throw new \moodle_exception('invalidcontext', 'local_archiving');
            }

            $result[] = new self(
                $task->id,
                $task->jobid,
                $context,
                cm_state_fingerprint::from_raw_value($task->fingerprint),
                $task->userid,
                $task->archivingmod,
                activity_archiving_task_status::from($task->status)
            );
        }

        return $result;
    }

    /**
     * Returns an instance of the activity archiving driver that handles this task
     *
     * @return archivingmod Instance of the activity archiving driver
     * @throws \moodle_exception
     */
    protected function archivingmod(): archivingmod {
        if ($this->archivingmod instanceof archivingmod) {
            return $this->archivingmod;
        }

        $this->archivingmod = \local_archiving\driver\factory::activity_archiving_driver(
            $this->archivingmodname,
            $this->context
        );

        return $this->archivingmod;
    }

    /**
     * Returns the ID of this task
     *
     * @return int ID of this task
     */
    public function get_id(): int {
        return $this->taskid;
    }

    /**
     * Returns the ID of the archive job this task is associated with
     *
     * @return int ID of the archive job this task is associated with
     */
    public function get_jobid(): int {
        return $this->jobid;
    }

    /**
     * Returns the ID of the user that owns this task
     *
     * @return int ID of the user that owns this task
     */
    public function get_userid(): int {
        return $this->userid;
    }

    /**
     * Returns the name of the activity archiving driver that handles this task
     *
     * @return string Name of the activity archiving driver that handles this task
     */
    public function get_archivingmodname(): string {
        return $this->archivingmodname;
    }

    /**
     * Returns the Moodle context this task is run in
     *
     * @return \context_module Moodle context this task is run in
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * Returns the fingerprint of the course module at task creation
     *
     * @return cm_state_fingerprint Fingerprint of the course module at task creation
     */
    public function get_fingerprint(): cm_state_fingerprint {
        return $this->fingerprint;
    }

    /**
     * Returns the archive job this task is associated with
     *
     * @return archive_job The archive job this task is associated with
     * @throws \dml_exception
     */
    public function get_job(): archive_job {
        if (!$this->archivejob instanceof archive_job) {
            $this->archivejob = archive_job::get_by_id($this->jobid);
        }

        return $this->archivejob;
    }

    /**
     * Executes this task via the associated activity archiving driver
     *
     * This is just a convenience wrapper.
     *
     * @return void
     * @throws yield_exception If the task is waiting for an asynchronous
     * @throws \moodle_exception
     * operation to completed or event to occur.
     */
    public function execute(): void {
        $this->archivingmod()->execute_task($this); // @codeCoverageIgnore
    }

    /**
     * Cancels this task via the associated activity archiving driver
     *
     * This is just a convenience wrapper.
     *
     * @return void
     * @throws \moodle_exception
     */
    public function cancel(): void {
        $this->archivingmod()->cancel_task($this); // @codeCoverageIgnore
    }

    /**
     * Deletes this task via the associated activity archiving driver
     *
     * This is just a convenience wrapper.
     *
     * @return void
     * @throws \moodle_exception
     */
    public function delete(): void {
        $this->archivingmod()->delete_task($this); // @codeCoverageIgnore
    }

    /**
     * Deletes an activity archiving task and everything that is associated with
     * it from the database.
     *
     * This method is called from archivingmod::delete() and handles the generic
     * task deletetion. If an archivingmod needs to perform additional actions
     * it must override the archivingmod::delete_task() method.
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function delete_from_db(): void {
        global $DB;

        // Invalidate web service tokens if present.
        $this->delete_webservice_token();

        // Remove all linked artifact files from the filesystem.
        foreach ($this->get_linked_artifacts() as $artifact) {
            $this->unlink_artifact($artifact, true);
        }

        // Remove all task content metadata entries from the database.
        $DB->delete_records(db_table::CONTENT->value, ['taskid' => $this->taskid]);

        // Finally delete the task from the database.
        $DB->delete_records(db_table::ACTIVITY_TASK->value, ['id' => $this->taskid]);
    }

    /**
     * Retrieves the optional task settings object
     *
     * @return \stdClass Task settings object
     * @throws \dml_exception
     */
    public function get_settings(): \stdClass {
        if (is_null($this->settings)) {
            global $DB;

            $settingsjson = $DB->get_field(db_table::ACTIVITY_TASK->value, 'settings', ['id' => $this->taskid], MUST_EXIST);

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
     * Clears this tasks settings inside the database. This option is irreversible.
     *
     * @param bool $force If true, force clear task settings even if the task is not completed yet
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception If the task it not yet completed
     */
    public function clear_settings(bool $force = false): void {
        global $DB;

        if (!$this->is_completed() && !$force) {
            throw new \moodle_exception('task_settings_cant_be_cleared', 'local_archiving');
        }

        $DB->update_record(db_table::ACTIVITY_TASK->value, [
            'id' => $this->taskid,
            'settings' => null,
        ]);
        $this->settings = new \stdClass();
    }

    /**
     * Retrieves the current status of this task
     *
     * @param bool $usecached If true, the cached status will be used instead of querying the database
     *
     * @return activity_archiving_task_status Task status
     */
    public function get_status(bool $usecached = false): activity_archiving_task_status {
        global $DB;

        // If we only want the cached status, return it directly.
        if ($usecached) {
            return $this->status;
        }

        // Update local status value from database.
        try {
            $this->status = activity_archiving_task_status::from(
                $DB->get_field(db_table::ACTIVITY_TASK->value, 'status', ['id' => $this->taskid], MUST_EXIST)
            );
        } catch (\dml_exception $e) {
            $this->status = activity_archiving_task_status::UNKNOWN;
        }

        return $this->status;
    }

    /**
     * Changes the current task status to the given value
     *
     * @param activity_archiving_task_status $status New task status
     * @param bool $deletewstokenoncompletion If true, deletes the associated web
     * service token upon completion
     * @return void
     * @throws \dml_exception
     */
    public function set_status(activity_archiving_task_status $status, bool $deletewstokenoncompletion = true): void {
        global $DB;

        // Update status value in the database.
        $DB->update_record(db_table::ACTIVITY_TASK->value, [
            'id' => $this->taskid,
            'status' => $status->value,
        ]);

        // Update local status copy and log.
        if ($this->status != $status) {
            $this->status = $status;
            $this->get_logger()->info(
                "Activity archiving task status: " . $status->name . " ({$status->value})"
            );
        }

        // Delete web service token if desired.
        if ($deletewstokenoncompletion && $this->is_completed()) {
            $this->delete_webservice_token();
        }
    }

    /**
     * Stores the given list of task content metadata entries
     *
     * ATTENTION: Calling this method twice will result in duplicate entries!
     *
     * @param task_content_metadata[] $taskcontentmetadata List of task content metadata entries to store
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function store_task_content_metadata(array $taskcontentmetadata): void {
        global $DB;

        // Validate input.
        foreach ($taskcontentmetadata as $entry) {
            if (!($entry instanceof task_content_metadata)) {
                throw new \coding_exception('invalid_task_content_metadata_entry', 'local_archiving');
            }
        }

        // Store task content metadata in database.
        $DB->insert_records(db_table::CONTENT->value, $taskcontentmetadata);
    }

    /**
     * Retrieves all task content metadata entries that are associated with this task
     *
     * @return task_content_metadata[] List of task content metadata entries
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_task_content_metadata(): array {
        global $DB;

        // Fetch raw records from the database.
        $rawrecords = $DB->get_records(db_table::CONTENT->value, ['taskid' => $this->taskid]);

        // Convert raw records to task_content_metadata objects.
        return array_map(fn($row): task_content_metadata => new task_content_metadata(
            taskid: $row->taskid,
            userid: $row->userid,
            reftable: $row->reftable,
            refid: $row->refid,
            summary: $row->summary
        ), $rawrecords);
    }

    /**
     * Retrieves the webservice token that is associated with this task, if one
     * such exists
     *
     * @return string|null Webservice token or null if not set
     * @throws \dml_exception
     */
    public function get_webservice_token(): ?string {
        global $DB;

        return $DB->get_field(
            db_table::ACTIVITY_TASK->value,
            'wstoken',
            ['id' => $this->taskid],
            IGNORE_MISSING
        ) ?: null;
    }

    /**
     * Creates a new token for the given webservice, user, and lifetime
     *
     * @param int $webserviceid ID of the webservice to create the token for
     * @param int $userid ID of the user to associate to create the token for
     * @param int $lifetimesec Lifetime of the webservice token in secons from now
     * @return string Generated webservice token
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_webservice_token(int $webserviceid, int $userid, int $lifetimesec): string {
        global $DB;

        // Validate lifetime.
        if ($lifetimesec <= 0) {
            throw new \moodle_exception('invalid_wstoken_lifetime', 'local_archiving');
        }

        // Invalidate existing token if present.
        $this->delete_webservice_token();

        // Generate wstoken and store it in the database.
        $wstoken = \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            \core_external\util::get_service_by_id($webserviceid),
            $userid,
            \context_system::instance(),
            time() + $lifetimesec,
            0
        );
        $DB->update_record(db_table::ACTIVITY_TASK->value, [
            'id' => $this->taskid,
            'wstoken' => $wstoken,
        ]);

        // Log token creation.
        $this->get_logger()->debug('Created webservice token: ' . $wstoken);

        return $wstoken;
    }

    /**
     * Deletes the webservice token that is associated with this task
     *
     * @return bool True, if a wstoken was deleted. False if no wstoken existed.
     * @throws \dml_exception
     */
    public function delete_webservice_token(): bool {
        global $DB;

        // Check if we have an associated web service token.
        $wstoken = $this->get_webservice_token();
        if (!$wstoken) {
            return false;
        }

        // Invalidate token and remove link to this task.
        $DB->delete_records('external_tokens', ['token' => $wstoken, 'tokentype' => EXTERNAL_TOKEN_PERMANENT]);
        $DB->update_record(db_table::ACTIVITY_TASK->value, [
            'id' => $this->taskid,
            'wstoken' => null,
        ]);

        // Log token destruction.
        $this->get_logger()->debug('Destroyed web service token: ' . $wstoken);

        return true;
    }

    /**
     * Determines if this task can further be processed of if it has reached a
     * final state.
     *
     * @return bool True, is this task has reached a final state
     */
    public function is_completed(): bool {
        switch ($this->get_status()) {
            case activity_archiving_task_status::FINISHED:
            case activity_archiving_task_status::CANCELED:
            case activity_archiving_task_status::FAILED:
            case activity_archiving_task_status::TIMEOUT:
                return true;
            default:
                return false;
        }
    }

    /**
     * Retrieves the current progress of this task
     *
     * @return int Progress of this task in percent (0 to 100))
     * @throws \dml_exception
     */
    public function get_progress(): int {
        global $DB;
        return $DB->get_field(db_table::ACTIVITY_TASK->value, 'progress', ['id' => $this->taskid], MUST_EXIST);
    }

    /**
     * Updates the progress of this task to the given percentage (0 to 100).
     * Setting this to 100 does not automatically complete the task. Completion
     * is exclusively determined by task status. The progress value is solely
     * an additional feedback indicator.
     *
     * @param int $progress New task progress in percent (0 to 100)
     * @return void
     * @throws \moodle_exception If the given progress value is invalid
     */
    public function set_progress(int $progress): void {
        global $DB;

        if ($progress < 0 || $progress > 100) {
            throw new \moodle_exception('invalid_progress_value', 'local_archiving');
        }

        $DB->update_record(db_table::ACTIVITY_TASK->value, [
            'id' => $this->taskid,
            'progress' => $progress,
        ]);

        $this->get_logger()->info("Activity archiving task {$this->taskid} progress updated: {$progress}%");
    }

    /**
     * Returns a list of all artifacts that are linked to this task
     *
     * @return \stored_file[] List of linked artifacts
     * @throws \dml_exception
     */
    public function get_linked_artifacts(): array {
        global $DB;

        $artifacts = $DB->get_records(db_table::TEMPFILE->value, [
            'jobid' => $this->jobid,
            'taskid' => $this->taskid,
        ]);

        $fs = get_file_storage();
        $result = [];
        foreach ($artifacts as $artifact) {
            $result[$artifact->id] = $fs->get_file_by_id($artifact->fileid);
        }

        return $result;
    }

    /**
     * Links the given stored_file to this task.
     *
     * @param \stored_file $artifactfile The stored_file to link
     * @param string|null $sha256sum The sha256sum of the file. Will be calculated if not given
     * @param bool $takeownership If true, the file will be moved to the filearea of this task
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function link_artifact(\stored_file $artifactfile, ?string $sha256sum = null, bool $takeownership = false): void {
        global $DB;

        // Take ownership of the file if requested.
        if ($takeownership) {
            $targetfile = get_file_storage()->create_file_from_storedfile(
                filerecord: self::generate_artifact_fileinfo($artifactfile->get_filename()),
                fileorid: $artifactfile
            );
            $artifactfile->delete();
        } else {
            $targetfile = $artifactfile;
        }

        // Calculate sha256sum if not given.
        if (!$sha256sum || storage::is_valid_sha256sum($sha256sum)) {
            $sha256sum = storage::hash_file($targetfile);
        }

        // Create link in database.
        $DB->insert_record(db_table::TEMPFILE->value, [
            'jobid' => $this->jobid,
            'taskid' => $this->taskid,
            'fileid' => $targetfile->get_id(),
            'sha256sum' => $sha256sum,
        ]);
    }

    /**
     * Unlinks the given artifactfile from this task.
     *
     * @param \stored_file $artifactfile The stored_file to unlink
     * @param bool $delete If true, the file will also be deleted from the filesystem
     * @return void
     * @throws \dml_exception On DB errors
     * @throws \moodle_exception If trying to delete a file that is still linked to other tasks / jobs
     */
    public function unlink_artifact(\stored_file $artifactfile, bool $delete = false): void {
        global $DB;

        // Remove database link.
        $DB->delete_records(db_table::TEMPFILE->value, [
            'jobid' => $this->jobid,
            'taskid' => $this->taskid,
            'fileid' => $artifactfile->get_id(),
        ]);

        // Delete file if requested.
        if ($delete) {
            $referencestoartifact = $DB->count_records(db_table::TEMPFILE->value, ['fileid' => $artifactfile->get_id()]);

            if ($referencestoartifact > 0) {
                throw new \moodle_exception('artifactfile_still_linked', 'local_archiving');
            }

            $artifactfile->delete();
        }
    }

    /**
     * Generates a fileinfo object for an artifact file of this task
     *
     * @param string $filename Name of the artifact file
     * @return \stdClass Populated fileinfo object
     * @throws \moodle_exception
     */
    public function generate_artifact_fileinfo(string $filename): \stdClass {
        return (object) [
            'contextid' => $this->context->get_course_context()->id,
            'component' => 'archivingmod_' . $this->archivingmod()->get_plugin_name(),
            'filearea' => filearea::ARTIFACT->value,
            'itemid' => 0,
            'filepath' => "/job-{$this->jobid}/task-{$this->taskid}/",
            'filename' => $filename,
        ];
    }

    /**
     * Determines if an activity archiving task with the given fingerprint already
     * exists in the given context.
     *
     * @param \context_module $ctx Context of the module to check
     * @param cm_state_fingerprint $fingerprint Fingerprint to check for
     * @param bool $requiresuccess If true, only tasks with status FINISHED are considered
     * @return bool
     * @throws \dml_exception
     */
    public static function fingerprint_exists(
        \context_module $ctx,
        cm_state_fingerprint $fingerprint,
        bool $requiresuccess = true
    ): bool {
        global $DB;

        // Prepare query.
        $queryparams = [
            'contextid' => $ctx->id,
            'fingerprint' => $fingerprint->get_raw_value(),
        ];

        if ($requiresuccess) {
            $queryparams['status'] = activity_archiving_task_status::FINISHED->value;
        }

        // Execute query to determine if fingerprint exists.
        return $DB->record_exists(db_table::ACTIVITY_TASK->value, $queryparams);
    }
}
