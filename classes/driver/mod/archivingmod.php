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
 * Interface definitions for activity archiving drivers
 *
 * @package     local_archiving
 * @category    driver
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver\mod;

use local_archiving\archive_job;
use local_archiving\exception\yield_exception;
use local_archiving\form\job_create_form;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Interface for activity archiving driver (archivingmod) sub-plugins
 */
abstract class archivingmod {

    /** @var \context_module Moodle context this driver instance is for */
    protected \context_module $context;

    /** @var int ID of the course the targeted activity is part of */
    protected int $courseid;

    /** @var int ID of the targeted course module / activity */
    protected int $cmid;

    /**
     * Create a new activity archiving driver instance
     *
     * @param \context_module $context Moodle context this driver instance is for
     */
    public function __construct(\context_module $context) {
        $this->context = $context;
        $this->courseid = $context->get_course_context()->instanceid;
        $this->cmid = $context->instanceid;
    }

    /**
     * Returns the localized name of this driver
     *
     * @return string Localized name of the driver
     */
    abstract public static function get_name(): string;

    /**
     * Returns the internal identifier for this driver. This function should
     * return the last part of the frankenstyle plugin name (e.g., 'quiz' for
     * 'archivingmod_quiz').
     *
     * @return string Internal identifier of this driver
     */
    abstract public static function get_plugname(): string;

    /**
     * Returns a list of supported Moodle activities by this driver as a list of
     * frankenstyle plugin names (e.g., 'mod_quiz')
     *
     * @return array List of supported activities
     */
    abstract public static function get_supported_activities(): array;

    /**
     * Determines if the targeted activity is ready to be archived.
     *
     * @return bool True if the activity is ready to be archived
     */
    abstract public function can_be_archived(): bool;

    /**
     * Executes the given task
     *
     * This function has to be implemented by the respective activity archiving
     * driver and handles all the activity-specific stuff.
     *
     * @param task $task The activity archiving task to execute
     * @return void
     * @throws yield_exception If the task is waiting for an asynchronous
     * operation to completed or event to occur.
     */
    abstract public function execute_task(task $task): void;

    /**
     * Provides access to the Moodle form that holds all settings for creating a
     * single archiving job. Generic settings are populated by the base class
     * and can be extended as needed.
     *
     * @param string $handler Name of the archivingmod sub-plugin that handles this job
     * @param \cm_info $cminfo Info object for the targeted course module
     * @return job_create_form Form for the task settings
     * @throws \dml_exception
     */
    public function get_job_create_form(string $handler, \cm_info $cminfo): job_create_form {
        return new job_create_form($handler, $cminfo);
    }

    /**
     * Creates a new activity archiving task. This function can be overridden to
     * perform additional activity-specific actions before or after creating a
     * new activity archiving task.
     *
     * @param archive_job $job Archive job this task will be associated with
     * @param \stdClass $tasksettings All task settings from the job_create_form
     * @return task A newly created task object that is not yet scheduled for execution
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_task(archive_job $job, \stdClass $tasksettings): task {
        return task::create(
            $job->get_id(),
            $this->context,
            $job->get_userid(),
            $this->get_plugname(),
            $tasksettings,
        );
    }

    /**
     * Executes all tasks that are associated with the given jobid
     *
     * @param int $jobid ID of the job to execute tasks for
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws yield_exception
     */
    public function execute_all_tasks_for_job(int $jobid): void {
        foreach (task::get_by_jobid($jobid) as $task) {
            $shouldyield = false;
            try {
                $this->execute_task($task);
            } catch (yield_exception $e) {
                $shouldyield = true;
            } finally {
                if ($shouldyield) {
                    throw new yield_exception();
                }
            }
        }
    }

    /**
     * Determines if all tasks associated with the given job are completed
     *
     * @param int $jobid ID of the job to check
     * @return bool True if all tasks are completed, false otherwise
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function is_all_tasks_for_job_completed(int $jobid): bool {
        $tasks = task::get_by_jobid($jobid);

        foreach ($tasks as $task) {
            if (!$task->is_completed()) {
                return false;
            }
        }

        return true;
    }

}
