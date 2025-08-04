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

namespace local_archiving\task;

use local_archiving\type\archive_job_status;

/**
 * Tests for the process_archive_job ad-hoc task.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the process_archive_job ad-hoc task.
 */
final class process_archive_job_test extends \advanced_testcase {

    /**
     * Helper to get the test data generator for local_archiving
     *
     * @return \local_archiving_generator
     */
    private function generator(): \local_archiving_generator {
        /** @var \local_archiving_generator */ // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        return self::getDataGenerator()->get_plugin_generator('local_archiving');
    }

    /**
     * Tests the creation of a new process_archive_job task instance and ensures
     * that it is associated with the given archive job.
     *
     * @covers \local_archiving\task\process_archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_and_job_linking(): void {
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // Assert that there are no existing ad-hoc tasks.
        $existingtasks = \core\task\manager::get_adhoc_tasks(process_archive_job::class);
        $this->assertEmpty($existingtasks, 'There should be no existing ad-hoc tasks for process_archive_job.');

        // Create a new task instance for the job.
        $task = process_archive_job::create($job);
        \core\task\manager::queue_adhoc_task($task);
        $existingtasks = \core\task\manager::get_adhoc_tasks(process_archive_job::class);
        $this->assertCount(1, $existingtasks, 'There should be exactly one ad-hoc task for process_archive_job.');

        $createdtask = array_shift($existingtasks);
        $this->assertEquals(
            $job,
            $task->get_archive_job(),
            'The initial task should be associated with the correct archive job.'
        );
        $this->assertEquals($job,
            $createdtask->get_archive_job(),
            'The created task should also be associated with the correct archive job.'
        );
    }

    /**
     * Tests that completed jobs can not be re-processed.
     *
     * @covers \local_archiving\task\process_archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_for_completed_job(): void {
        // Prepare completed archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $job->set_status(archive_job_status::COMPLETED);
        $this->assertTrue($job->is_completed(), 'The job should be marked as completed.');

        // Attempt to create a task for a completed job should throw an exception.
        $this->expectException(\moodle_exception::class);
        process_archive_job::create($job);
    }

    /**
     * Teste rescheduling of an existing process_archive_job task.
     *
     * @covers \local_archiving\task\process_archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_reschedule(): void {
        // Create a new task.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $firsttask = process_archive_job::create($job);
        \core\task\manager::queue_adhoc_task($firsttask);
        $this->assertCount(
            1,
            \core\task\manager::get_adhoc_tasks(process_archive_job::class),
            'There should be one ad-hoc task created.'
        );

        // Assert that we have exactly one task.
        ob_start();
        $firsttask->reschedule();
        ob_end_clean();

        $tasks = \core\task\manager::get_adhoc_tasks(process_archive_job::class);
        $this->assertCount(
            2,
            $tasks,
            'A new task should have been created upon rescheduling.'
        );

        // Check the newly created / re-scheduled task.
        $secondtask = array_pop($tasks);
        $this->assertNotEquals(
            $firsttask->get_id(),
            $secondtask->get_id(),
            'Rescheduling should create a new task instance with a different ID.'
        );
        $this->assertGreaterThan(
            time(),
            $secondtask->get_next_run_time(),
            'The next run time of the rescheduled task should be in the future.'
        );
    }

    /**
     * Tests execution and self-rescheduling of a process_archive_job task.
     *
     * @covers \local_archiving\task\process_archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute(): void {
        // Prepare a new archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $job->set_status(archive_job_status::QUEUED);
        $task = process_archive_job::create($job);
        $this->assertEmpty(
            \core\task\manager::get_adhoc_tasks(process_archive_job::class),
            'There should be no existing ad-hoc tasks before execution.'
        );

        // Try to execute the task multiple times.
        ob_start();
        $task->execute();
        $task->execute();
        $task->execute();
        $output = ob_get_clean();

        // Ensure that the job completed.
        $this->assertTrue($job->is_completed(), 'The archive job should be marked as completed after execution.');
        $this->assertStringContainsString('Job not completed yet', $output);
        $this->assertStringContainsString('Job completed.', $output);

        // Assert that at least one reschedule occured.
        $this->assertNotEmpty(
            \core\task\manager::get_adhoc_tasks(process_archive_job::class),
            'There should be at least one rescheduled task after execution.'
        );
    }

}
