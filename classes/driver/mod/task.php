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
 * @category    driver
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver\mod;

use local_archiving\exception\yield_exception;
use local_archiving\type\db_table;
use local_archiving\util\plugin_util;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * An asynchronous activity archiving task. Holds all information and state for a single
 * activity archiving task.
 */
final class task {

    /** @var int ID of this activity archiving task */
    protected int $taskid;

    /** @var int ID of the archive job this task is associated with */
    protected int $jobid;

    /** @var \context_module Moodle context this task is run in */
    protected \context_module $context;

    /** @var int ID of the user that owns this task */
    protected int $userid;

    /** @var string Name of the activity archiving driver that handles this task */
    protected string $archivingmodname;

    /** @var archivingmod|null Instance of the associated activity archiving driver (lazy-loaded) */
    protected ?archivingmod $archivingmod;

    /** @var ?\stdClass Optional task specific settings (lazy-loaded) */
    protected ?\stdClass $settings;

    /**
     * Builds a new activity archiving task object. This does not create entries
     * in the database. For creating or loading tasks use the respective static
     * methods.
     *
     * @param int $taskid
     * @param int $jobid
     * @param \context_module $context
     * @param int $userid
     * @param string $archivingmodname
     */
    protected function __construct(
        int $taskid,
        int $jobid,
        \context_module $context,
        int $userid,
        string $archivingmodname
    ) {
        $this->taskid = $taskid;
        $this->jobid = $jobid;
        $this->context = $context;
        $this->userid = $userid;
        $this->archivingmodname = $archivingmodname;
        $this->archivingmod = null;
        $this->settings = null;
    }

    /**
     * Creates a new activity archiving task object and inserts it into the
     * database. This method does not execute the task.
     *
     * @param int $jobid
     * @param \context_module $context
     * @param int $userid
     * @param string $archivingmodname
     * @param ?\stdClass $settings
     * @param int $status
     * @return task
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create(
        int $jobid,
        \context_module $context,
        int $userid,
        string $archivingmodname,
        ?\stdClass $settings = null,
        int $status = task_status::STATUS_UNINITIALIZED
    ): task {
        global $DB;

        // Validate input.
        if (!plugin_util::get_subplugin_by_name('archivingmod', $archivingmodname)) {
            throw new \moodle_exception('invalid_archivingmod', 'local_archiving');
        }

        // Create instance in DB.
        $now = time();
        $taskid = $DB->insert_record(db_table::ACTIVITY_TASK, [
            'jobid' => $jobid,
            'archivingmod' => $archivingmodname,
            'contextid' => $context->id,
            'userid' => $userid,
            'status' => $status,
            'progress' => 0,
            'settings' => $settings ? json_encode($settings) : null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return new self($taskid, $jobid, $context, $userid, $archivingmodname);
    }

    /**
     * Loads an existing activity archiving task object from the database
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_by_id(int $taskid): task {
        global $DB;

        $task = $DB->get_record(db_table::ACTIVITY_TASK, ['id' => $taskid], '*', MUST_EXIST);
        $context = \context::instance_by_id($task->contextid);

        if (!$context instanceof \context_module) {
            throw new \moodle_exception('invalidcontext', 'local_archiving');
        }

        return new self($task->id, $task->jobid, $context, $task->userid, $task->archivingmod);
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

        $tasks = $DB->get_records(db_table::ACTIVITY_TASK, ['jobid' => $jobid]);

        $result = [];
        foreach ($tasks as $task) {
            $context = \context::instance_by_id($task->contextid);

            if (!$context instanceof \context_module) {
                throw new \moodle_exception('invalidcontext', 'local_archiving');
            }

            $result[] = new self($task->id, $task->jobid, $context, $task->userid, $task->archivingmod);
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

        $driverclass = plugin_util::get_subplugin_by_name('archivingmod', $this->archivingmodname);
        if (!$driverclass) {
            throw new \moodle_exception('invalid_archivingmod', 'local_archiving');
        }

        $this->archivingmod = new $driverclass($this->context);

        return $this->archivingmod;
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
        $this->archivingmod()->execute_task($this);
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
        $this->archivingmod()->cancel_task($this);
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
        $this->archivingmod()->delete_task($this);
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

        // TODO: Free temporary files, stop other async stuff, and cleanup potentially other stuff.
        foreach ($this->get_linked_artifacts() as $artifact) {
            $this->unlink_artifact($artifact, true);
        }

        $DB->delete_records(db_table::ACTIVITY_TASK, ['id' => $this->taskid]);
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

            $settingsjson = $DB->get_field(db_table::ACTIVITY_TASK, 'settings', ['id' => $this->taskid], MUST_EXIST);

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

        $DB->update_record(db_table::ACTIVITY_TASK, [
            'id' => $this->taskid,
            'settings' => null,
        ]);
        $this->settings = new \stdClass();
    }

    /**
     * Retrieves the current status of this task
     *
     * @return int Job status value
     */
    public function get_status(): int {
        global $DB;

        try {
            return $DB->get_field(db_table::ACTIVITY_TASK, 'status', ['id' => $this->taskid], MUST_EXIST);
        } catch (\dml_exception $e) {
            return task_status::STATUS_UNKNOWN;
        }
    }

    /**
     * Changes the current task status to the given value
     *
     * @param int $status New status value
     * @return void
     * @throws \dml_exception
     */
    public function set_status(int $status): void {
        global $DB;

        $DB->update_record(db_table::ACTIVITY_TASK, [
            'id' => $this->taskid,
            'status' => $status,
        ]);
    }

    /**
     * Determines if this task can further be processed of if it has reached a
     * final state.
     *
     * @return bool True, is this task has reached a final state
     */
    public function is_completed(): bool {
        switch ($this->get_status()) {
            case task_status::STATUS_FINISHED:
            case task_status::STATUS_CANCELED:
            case task_status::STATUS_FAILED:
            case task_status::STATUS_TIMEOUT:
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
        return $DB->get_field(db_table::ACTIVITY_TASK, 'progress', ['id' => $this->taskid], MUST_EXIST);
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

        $DB->update_record(db_table::ACTIVITY_TASK, [
            'id' => $this->taskid,
            'progress' => $progress,
        ]);
        mtrace("Activity archiving task {$this->taskid} progress updated: {$progress}%");
    }

    /**
     * Returns a list of all artifacts that are linked to this task
     *
     * @return \stored_file[] List of linked artifacts
     * @throws \dml_exception
     */
    public function get_linked_artifacts(): array {
        global $DB;

        $artifacts = $DB->get_records(db_table::FILE, [
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
     * @param \stored_file $artifactfile
     * @return void
     * @throws \dml_exception
     */
    public function link_artifact(\stored_file $artifactfile): void {
        global $DB;

        $DB->insert_record(db_table::FILE, [
            'jobid' => $this->jobid,
            'taskid' => $this->taskid,
            'fileid' => $artifactfile->get_id(),
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

        $DB->delete_records(db_table::FILE, [
            'jobid' => $this->jobid,
            'taskid' => $this->taskid,
            'fileid' => $artifactfile->get_id(),
        ]);

        if ($delete) {
            $referencestoartifact = $DB->count_records(db_table::FILE, ['fileid' => $artifactfile->get_id()]);

            if ($referencestoartifact > 0) {
                throw new \moodle_exception('artifactfile_still_linked', 'local_archiving');
            }

            $artifactfile->delete();
        }
    }

}
