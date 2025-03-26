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
 * Ad-hoc task for processing a given archive job asynchronously
 *
 * @package     local_archiving
 * @category    task
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\task;

use local_archiving\archive_job;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Ad-hoc task for a single archive job.
 *
 * One such task is spawned for each archive job upon creation. It manages the
 * full process flow from start to finish.
 */
class process_archive_job extends \core\task\adhoc_task {

    /**
     * Creates a new task instance that is associated with the given archive
     * job.
     *
     * @param archive_job $job Archive job this task is associated with
     * @return process_archive_job New task instance
     * @throws \moodle_exception
     */
    public static function create(archive_job $job): process_archive_job {
        // Validate given archive job.
        if ($job->is_completed()) {
            throw new \moodle_exception('completed_job_cant_be_started_again', 'local_archiving');
        }

        $task = new self();
        $task->set_custom_data((object) [
            'jobid' => $job->get_id(),
        ]);
        $task->set_userid($job->get_userid());
        $task->set_attempts_available(1); // FIXME: Lowered for development. Set back to sensible value before production use.

        return $task;
    }

    /**
     * Creates and schedules an identical task to be run in the future
     *
     * @param int $delaysec Number of seconds to at least wait before executing the scheduled task
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function reschedule(int $delaysec = 30): void {
        mtrace('Rescheduling self for future run after '.$delaysec.' seconds.');
        $task = self::create($this->get_archive_job());
        $task->set_next_run_time(time() + $delaysec);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Retrieves the archive job that is associated with this task
     *
     * @return archive_job Archive job this task processes
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_archive_job(): archive_job {
        // TODO: Maybe cache this inside self, if performance becomes an issue.
        return archive_job::get_by_id(
            $this->get_custom_data()->jobid
        );
    }

    /**
     * Processes the associated archive job. If the job is not completed after
     * execution, this task reschedules itself for another execution in the
     * future. This happens until the archive job has reached a final state or
     * a timeout occurred.
     *
     * Do not perform active waiting here, but instead reschedule the task and
     * yield to free up resources.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function execute(): void {
        $job = $this->get_archive_job();

        // TODO: Check and handle timeout.

        $job->execute();

        if (!$job->is_completed()) {
            mtrace('Job not completed yet ...');
            $this->reschedule();
        } else {
            mtrace('Job completed.');
        }
    }
}
