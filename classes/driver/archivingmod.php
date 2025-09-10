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
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver;

use local_archiving\activity_archiving_task;
use local_archiving\archive_job;
use local_archiving\exception\yield_exception;
use local_archiving\form\job_create_form;
use local_archiving\type\activity_archiving_task_status;
use local_archiving\type\cm_state_fingerprint;
use local_archiving\type\task_content_metadata;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Interface for activity archiving driver (archivingmod) sub-plugins
 */
abstract class archivingmod extends base {

    /** @var int ID of the course the targeted activity is part of */
    protected readonly int $courseid;

    /** @var int ID of the targeted course module / activity */
    protected readonly int $cmid;

    /**
     * Create a new activity archiving driver instance
     *
     * @param \context_module $context Moodle context this driver instance is associated with
     */
    public function __construct(
        /** @var \context_module Moodle context this driver instance is associated with */
        protected readonly \context_module $context
    ) {
        $this->courseid = $context->get_course_context()->instanceid;
        $this->cmid = $context->instanceid;
    }

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
     * @param activity_archiving_task $task The activity archiving task to execute
     * @return void
     * @throws yield_exception If the task is waiting for an asynchronous
     * operation to completed or event to occur.
     */
    abstract public function execute_task(activity_archiving_task $task): void;

    /**
     * Returns a list of metadata for each piece of data that is part of the
     * given activity archiving task (e.g., quiz attempt, assignment submission, etc.).
     *
     * One such metadata instance must be created for each piece of data that is
     * exported by a single activity archiving task. This metadata is used to
     * track the contents of generated archives.
     *
     * @param activity_archiving_task $task The task to get metadata for
     * @return task_content_metadata[] List of metadata for each piece of data
     */
    abstract public function get_task_content_metadata(activity_archiving_task $task): array;

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
     */
    abstract public function fingerprint(): cm_state_fingerprint;

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
     * @return activity_archiving_task A newly created task object that is not yet scheduled for execution
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_task(archive_job $job, \stdClass $tasksettings): activity_archiving_task {
        // Create stub fingerprint if this is used in a PHPUnit test because we need to mock this abstract class and
        // this breaks with cm_state_fingerprint being a final class (and should stay that way!).
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST === true) {
            $fingerprint = cm_state_fingerprint::from_raw_value(str_repeat('0', 64));
        } else {
            $fingerprint = $this->fingerprint();
        }

        return activity_archiving_task::create(
            $job->get_id(),
            $this->context,
            $fingerprint,
            $job->get_userid(),
            $this->get_plugin_name(),
            $tasksettings,
        );
    }

    /**
     * Cancels the given activity archiving task. This function can be overridden
     * if the activity archiving driver needs to perform additional actions
     * before or after a tasks status is set to STATUS_CANCELED.
     *
     * @param activity_archiving_task $task The task to cancel
     * @return void
     * @throws \dml_exception
     */
    public function cancel_task(activity_archiving_task $task): void {
        $task->set_status(activity_archiving_task_status::CANCELED);
    }

    /**
     * Deletes the given activity archiving task. This function can be overridden
     * if the activity archiving driver needs to perform additional actions
     * before or after a task is deleted from the database.
     *
     * @param activity_archiving_task $task
     * @return void
     * @throws \dml_exception
     */
    public function delete_task(activity_archiving_task $task): void {
        $task->delete_from_db();
    }

    /**
     * Executes all tasks that are associated with the given jobid.
     *
     * This method also handles yielding if a task is waiting for an
     * asynchronous operation to complete. It will also mark tasks as failed
     * that throw any other exception during execution.
     *
     * @param int $jobid ID of the job to execute tasks for
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws yield_exception
     */
    public function execute_all_tasks_for_job(int $jobid): void {
        $shouldyield = false;

        foreach (activity_archiving_task::get_by_jobid($jobid) as $task) {
            try {
                $this->execute_task($task);
            } catch (yield_exception $e) {
                // If the task is waiting for an asynchronous operation to complete,
                // we need to yield and let the worker continue later.
                $shouldyield = true;
            } catch (\Exception $e) {
                // If any other exception occurs, we cancel the task.
                $task->set_status(activity_archiving_task_status::FAILED);
                $task->get_logger()->error($e->getMessage());
                throw new \moodle_exception(
                    'activity_archiving_task_failed',
                    'local_archiving',
                );
            }
        }

        if ($shouldyield) {
            throw new yield_exception();
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
        $tasks = activity_archiving_task::get_by_jobid($jobid);

        foreach ($tasks as $task) {
            if (!$task->is_completed()) {
                return false;
            }
        }

        return true;
    }

}
