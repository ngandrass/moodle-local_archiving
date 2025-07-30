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

namespace local_archiving\driver;

use local_archiving\activity_archiving_task;
use local_archiving\exception\yield_exception;
use local_archiving\type\activity_archiving_task_status;

/**
 * Tests for the archivingmod driver base.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingmod driver base.
 */
final class archivingmod_test extends \advanced_testcase {

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
     * Creates a mock instance of the abstract archivingmod driver base class.
     *
     * @param \context_module $context Moodle context this driver instance is associated with
     * @param string $classname Optional custom name of the mock class (e.g. 'archivingmod_mock').
     * @return archivingmod Mock instance of the archivingmod driver base class.
     */
    private function instance(\context_module $context, string $classname = 'archivingmod_mock'): archivingmod {
        return $this->getMockForAbstractClass(archivingmod::class, [$context], $classname);
    }

    /**
     * Tests the creation of an archivingmod instance based on a given module context.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     */
    public function test_creation(): void {
        // Prepare course with cm.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);

        // Create an instance.
        $archivingmod = $this->instance(\context_module::instance($cm->cmid));

        // Check if the properties are set correctly.
        $reflection = new \ReflectionClass(archivingmod::class);
        $courseid = $reflection->getProperty('courseid')->getValue($archivingmod);
        $cmid = $reflection->getProperty('cmid')->getValue($archivingmod);

        $this->assertEquals($course->id, $courseid, 'Course ID should match the course ID of the context.');
        $this->assertEquals($cm->cmid, $cmid, 'CM ID should match the CM ID of the context.');
    }

    /**
     * Tries to get an instance of the default job create form.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_job_create_form(): void {
        // Prepare course with cm.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course, get_admin()->id);

        // Create an instance and get the job create form.
        $archivingmod = $this->instance(\context_module::instance($cm->cmid));
        $form = $archivingmod->get_job_create_form('quiz', $modinfo->get_cm($cm->cmid));
        $this->assertFalse(
            is_subclass_of($form, \local_archiving\form\job_create_form::class),
            'The returned form should be an instance of the base job_create_form.'
        );

        // Form initialization will mess with $PAGE->url so we expect that debug call.
        $this->assertdebuggingcalledcount(1);
    }

    /**
     * Tests default creation routine for activity archiving tasks.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_task(): void {
        // Prepare an archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // Create an archivingmod instance and let it create a basic activity archiving task.
        $archivingmod = $this->instance($job->get_context(), 'archivingmod_quiz');
        $tasksettings = (object) ['lorem' => 'ipsum'];
        $task = $archivingmod->create_task($job, $tasksettings);

        $this->assertSame(
            $archivingmod->get_plugin_name(),
            $task->get_archivingmodname(),
            'The task should have the same handler as the archivingmod instance.'
        );
        $this->assertSame($job->get_id(), $task->get_jobid(), 'The task should be associated with the job.');
        $this->assertSame($job->get_context(), $task->get_context(), 'The task should have the same context as the job.');
        $this->assertSame($job->get_userid(), $task->get_userid(), 'The task should have the same user ID as the job.');
        $this->assertEquals($tasksettings, $task->get_settings(), 'The task settings should match the provided settings.');
    }

    /**
     * Tests the cancelation of an activity archiving task.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_cancel_task(): void {
        // Prepare an archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // Create an archivingmod instance and let it create a basic activity archiving task.
        $archivingmod = $this->instance($job->get_context(), 'archivingmod_quiz');
        $tasksettings = (object) ['lorem' => 'ipsum'];
        $task = $archivingmod->create_task($job, $tasksettings);
        $this->assertNotSame(
            activity_archiving_task_status::CANCELED,
            $task->get_status(),
            'The task should not be completed before cancellation.'
        );

        // Cancel the task.
        $archivingmod->cancel_task($task);
        $this->assertSame(
            activity_archiving_task_status::CANCELED,
            $task->get_status(),
            'The task should be marked as canceled after cancellation.'
        );
    }

    /**
     * Tests deletion of an activity archiving task.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_delete_task(): void {
        // Prepare an archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // Create an archivingmod instance and let it create a basic activity archiving task.
        $archivingmod = $this->instance($job->get_context(), 'archivingmod_quiz');
        $tasksettings = (object) ['lorem' => 'ipsum'];
        $task = $archivingmod->create_task($job, $tasksettings);
        $this->assertNotNull(
            activity_archiving_task::get_by_id($task->get_id()),
            'The task should exist before deletion.'
        );

        // Delete the task.
        $archivingmod->delete_task($task);
        $this->expectException(\dml_missing_record_exception::class);
        activity_archiving_task::get_by_id($task->get_id());
    }

    /**
     * Tests execution of multiple activity archiving tasks for a job.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws yield_exception
     */
    public function test_execute_all_tasks_for_job(): void {
        // Prepare an archive job with multiple tasks.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $archivingmod = $this->instance($job->get_context(), 'archivingmod_quiz');
        $tasks = [
            $archivingmod->create_task($job, new \stdClass()),
            $archivingmod->create_task($job, new \stdClass()),
            $archivingmod->create_task($job, new \stdClass()),
        ];

        // Make all tasks complete instantly.
        $archivingmod->expects($this->any())
            ->method('execute_task')
            ->willReturnCallback(function (activity_archiving_task $task) {
                $task->set_status(activity_archiving_task_status::FINISHED);
            });

        // Execute all tasks for the job.
        $archivingmod->execute_all_tasks_for_job($job->get_id());

        // Check if all tasks have been executed.
        foreach ($tasks as $task) {
            $this->assertTrue($task->is_completed());
        }
    }

    /**
     * Tests if the execute_all_tasks_for_job method correctly handles yielding.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_all_tasks_for_job_yielding(): void {
        // Prepare an archive job with a task that yields.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $archivingmod = $this->instance($job->get_context(), 'archivingmod_quiz');
        $archivingmod->create_task($job, new \stdClass());

        // Make the task yield.
        $archivingmod->expects($this->any())
            ->method('execute_task')
            ->willThrowException(new yield_exception('Yielding for async operation'));

        // Execute all tasks for the job and expect a yield exception.
        $this->expectException(yield_exception::class);
        $archivingmod->execute_all_tasks_for_job($job->get_id());
    }

    /**
     * Tests if the execute_all_tasks_for_job method correctly handles exceptions
     * during execution of tasks.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_all_tasks_for_job_exception_handling(): void {
        // Prepare an archive job with a task that throws an exception.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $archivingmod = $this->instance($job->get_context(), 'archivingmod_quiz');
        $task = $archivingmod->create_task($job, new \stdClass());

        // Make the task yield.
        $archivingmod->expects($this->any())
            ->method('execute_task')
            ->willThrowException(new \RuntimeException('somthing bad happened ...'));

        // Execute all tasks for the job and expect a yield exception.
        try {
            $archivingmod->execute_all_tasks_for_job($job->get_id());
            $this->fail('Expected moodle_exception to be thrown due to task failure.');
        } catch (\moodle_exception $e) {
            $this->assertSame(
                'activity_archiving_task_failed',
                $e->errorcode,
                'The exception should be of type moodle_exception with code activity_archiving_task_failed.'
            );
        }

        $this->assertSame(
            activity_archiving_task_status::FAILED,
            $task->get_status(),
            'The task should be marked as failed after an exception during execution.'
        );
    }

    /**
     * Tests if task completion state is correctly evaluated.
     *
     * @covers \local_archiving\driver\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_is_all_tasks_for_job_completed(): void {
        // Prepare an archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $archivingmod = $this->instance($job->get_context(), 'archivingmod_quiz');

        // Without any tasks, everything must be completed, right?
        $this->assertTrue(
            $archivingmod->is_all_tasks_for_job_completed($job->get_id()),
            'Without any tasks, the job should be considered completed.'
        );

        // Add a new task.
        $task1 = $archivingmod->create_task($job, new \stdClass());
        $this->assertFalse(
            $archivingmod->is_all_tasks_for_job_completed($job->get_id()),
            'With one task, the job should not be considered completed.'
        );

        // Add another task.
        $task2 = $archivingmod->create_task($job, new \stdClass());
        $this->assertFalse(
            $archivingmod->is_all_tasks_for_job_completed($job->get_id()),
            'With two tasks, the job should not be considered completed.'
        );

        // Mark the first task as completed.
        $task1->set_status(activity_archiving_task_status::FINISHED);
        $this->assertFalse(
            $archivingmod->is_all_tasks_for_job_completed($job->get_id()),
            'With only one task completed, the job should not be considered completed.'
        );

        // Mark the second task as completed.
        $task2->set_status(activity_archiving_task_status::FINISHED);
        $this->assertTrue(
            $archivingmod->is_all_tasks_for_job_completed($job->get_id()),
            'With both tasks completed, the job should be considered completed.'
        );
    }

}
