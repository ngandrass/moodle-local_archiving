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

use local_archiving\type\db_table;
use local_archiving\util\plugin_util;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * An asynchronous activity archiving task. Holds all information and state for a single
 * activity archiving task.
 */
class task {

    /** @var int ID of this activity archiving task */
    protected int $taskid;

    /** @var int ID of the archive job this task is associated with */
    protected int $jobid;

    /** @var \context Moodle context this task is run in */
    protected \context $context;

    /** @var int ID of the user that owns this task */
    protected int $userid;

    /** @var string Name of the archivingmod driver that handles this task */
    protected string $archivingmod;

    /** @var ?\stdClass Optional task specific settings (lazy-loaded) */
    protected ?\stdClass $settings;

    /**
     * Builds a new activity archiving task object. This does not create entries
     * in the database. For creating or loading tasks use the respective static
     * methods.
     *
     * @param int $taskid
     * @param int $jobid
     * @param \context $context
     * @param int $userid
     * @param string $archivingmod
     */
    protected function __construct(
        int $taskid,
        int $jobid,
        \context $context,
        int $userid,
        string $archivingmod
    ) {
        $this->taskid = $taskid;
        $this->jobid = $jobid;
        $this->context = $context;
        $this->userid = $userid;
        $this->archivingmod = $archivingmod;
        $this->settings = null;
    }

    /**
     * TODO
     *
     * @param int $jobid
     * @param \context $context
     * @param int $userid
     * @param string $archivingmod
     * @param ?\stdClass $settings
     * @param int $status
     * @return task
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create(
        int $jobid,
        \context $context,
        int $userid,
        string $archivingmod,
        ?\stdClass $settings = null,
        int $status = task_status::STATUS_UNINITIALIZED
    ): task {
        global $DB;

        // Validate input.
        if (!($context instanceof \context_module)) {
            throw new \moodle_exception('invalid_context', 'local_archiving');
        }
        if (!plugin_util::get_subplugin_by_name('archivingmod', $archivingmod)) {
            throw new \moodle_exception('invalid_archivingmod', 'local_archiving');
        }

        // Create instance in DB.
        $now = time();
        $taskid = $DB->insert_record(db_table::ACTIVITY_TASK, [
            'jobid' => $jobid,
            'archivingmod' => $archivingmod,
            'contextid' => $context->id,
            'userid' => $context->userid,
            'status' => $status,
            'progress' => 0,
            'settings' => $settings ? json_encode($settings) : null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return new self($taskid, $jobid, $context, $userid, $archivingmod);
    }

    /**
     * TODO
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_by_id(int $taskid): task {
        global $DB;

        $task = $DB->get_record(db_table::ACTIVITY_TASK, ['id' => $taskid], '*', MUST_EXIST);
        $context = \context::instance_by_id($task->contextid);

        return new self($task->id, $task->jobid, $context, $task->userid, $task->archivingmod);
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
    }

}
