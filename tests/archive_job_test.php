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

namespace local_archiving;

use local_archiving\type\archive_job_status;
use local_archiving\type\db_table;
use local_archiving\type\log_level;
use mod_lti\search\activity;
use ReflectionClass;

/**
 * Tests for the archive_job class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for archive_job
 */
final class archive_job_test extends \advanced_testcase {

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
     * Invokes a protected or private method of an archive_job instance.
     *
     * @param archive_job $instance Archive job instance to call the method on.
     * @param string $methodname Name of the method to call.
     * @param array $args Optional arguments to pass to the method.
     * @return mixed Method return value.
     * @throws \ReflectionException
     */
    private static function call_protected_archive_job_method(
        archive_job $instance,
        string $methodname,
        array $args = []
    ): mixed {
        $class = new \ReflectionClass($instance);
        $method = $class->getMethod($methodname);
        $method->setAccessible(true);
        return $method->invokeArgs($instance, $args);
    }

    /**
     * Teste creating and retrieving an archive job.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_and_retrieve(): void {
        $this->resetAfterTest();

        // Prepare course, activity, and misc.
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $ctx = \context_module::instance($cm->cmid);

        $settings = (object) [
            'foo' => 13374242,
            'lorem' => 'ipsum',
            'sadness' => false,
            'nested' => (object) [
                'foo' => null,
                'baz' => 42,
            ],
        ];

        // Create new archive job.
        $createdjob = archive_job::create(
            context: $ctx,
            userid: get_admin()->id,
            settings: $settings,
            cleansettings: true
        );
        $this->assertNotNull($createdjob, 'Created job should not be null');
        $this->assertGreaterThan(0, $createdjob->get_id(), 'Created job should have a valid ID');

        // Try to retrieve the job again.
        $retrievedjob = archive_job::get_by_id($createdjob->get_id());
        $this->assertEquals($createdjob, $retrievedjob, 'Retrieved job should be the same as created job');
        $this->assertSame(
            $createdjob->get_timecreated(),
            $retrievedjob->get_timecreated(),
            'Creation time should not be altered by retrieval'
        );
        $this->assertSame(
            $createdjob->get_timemodified(),
            $retrievedjob->get_timemodified(),
            'Modification time should not be altered by retrieval'
        );

        // Validate stored data.
        $this->assertEquals($ctx->id, $retrievedjob->get_context()->id, 'Context ID should match');
        $this->assertEquals(get_admin()->id, $retrievedjob->get_userid(), 'User ID should match');
        $this->assertEquals($settings, $retrievedjob->get_settings(), 'Settings should match');
    }

    /**
     * Tests the locking mechanism of archive jobs.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \ReflectionException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_locking(): void {
        // Prepare archive job and lock factory.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $anotherjob = $this->generator()->create_archive_job();

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_archiving_archive_job');
        $lockresource = self::call_protected_archive_job_method($job, 'get_lock_resource');

        // Try to acquire the lock.
        $lock = self::call_protected_archive_job_method($job, 'lock');
        $this->assertInstanceOf(\core\lock\lock::class, $lock, 'Invalid lock class received');
        $lock->release();

        // Try to lock again.
        $trylock = self::call_protected_archive_job_method($job, 'try_lock');
        $this->assertInstanceOf(\core\lock\lock::class, $trylock, 'Lock should be acquired again');

        // Try to lock another job.
        $anotherlock = self::call_protected_archive_job_method($anotherjob, 'try_lock');
        $this->assertInstanceOf(\core\lock\lock::class, $anotherlock, 'Another job should acquire its own lock');
        $this->assertNotEquals($trylock->get_key(), $anotherlock->get_key(), 'Locks should not share the same key');

        // Release everything.
        $trylock->release();
        $anotherlock->release();
    }

    /**
     * Tests that an archive job can be enqueued to be processed by an ad-hoc task.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_enqueuing(): void {
        // Prepare archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // Make sure that we have no tasks in the queue.
        $this->assertNull(\core\task\manager::get_next_adhoc_task(time()), 'No ad-hoc task should queued before enqueuing');

        // Enqueue the job and check if it was added to the queue.
        $job->enqueue();
        $this->assertEquals(archive_job_status::QUEUED, $job->get_status());
        $task = \core\task\manager::get_next_adhoc_task(
            timestart: time(),
            classname: \local_archiving\task\process_archive_job::class
        );
        $this->assertInstanceOf(
            \local_archiving\task\process_archive_job::class,
            $task,
            'Ad-hoc task should be queued after enqueuing the job'
        );
        \core\task\manager::adhoc_task_failed($task);
    }

    /**
     * Tests that a previously completed archive job can not be enqueued again.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_enqueuing_completed_job(): void {
        // Prepare a completed archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $job->set_status(archive_job_status::COMPLETED);

        // Ensure that an completed job can not be enqueued.
        $this->expectException(\moodle_exception::class, 'Enqueuing a completed job should throw an exception');
        $job->enqueue();
    }

    /**
     * Tests that a previously failed archive job can not be enqueued again.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_enqueuing_failed_job(): void {
        // Prepare a completed archive job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $job->set_status(archive_job_status::FAILURE);

        // Ensure that an completed job can not be enqueued.
        $this->expectException(\moodle_exception::class, 'Enqueuing a failed job should throw an exception');
        $job->enqueue();
    }

    /**
     * Tests the execution of an archive job including activity archiving tasks
     * and Moodle backup export.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute(): void {
        // Create a new job with a quiz.
        $this->resetAfterTest();
        $this->setAdminUser();
        $job = $this->generator()->create_archive_job([
            'settings' => (object) [
                'export_course_backup' => true,
                'export_cm_backup' => true,
                'storage_driver' => 'localdir',
            ],
        ]);
        $job->set_status(archive_job_status::QUEUED);

        // Try to complete the job.
        $executioncycle = 0;
        do {
            try {
                $job->execute();

                // Check job progress.
                $this->assertGreaterThanOrEqual(0, $job->get_progress(), 'Job progress should be non-negative');
                $this->assertLessThanOrEqual(100, $job->get_progress(), 'Job progress should not exceed 100%');

                // Let backups async backups complete.
                while ($task = \core\task\manager::get_next_adhoc_task(time())) {
                    ob_start();
                    $task->execute();
                    \core\task\manager::adhoc_task_complete($task);
                    ob_end_clean();
                }
            } finally {
                // Make sure that we always see the error logs of the job for debugging purposes.
                $this->assertSame(
                    $job->get_logger()->get_logs(log_level::ERROR),
                    [],
                    'Job should not produce any error or fatal log events during execution'
                );
            }

            if ($executioncycle > 5) {
                $this->fail('Job did not complete after 5 execution cycles');
            } else {
                $executioncycle++;
            }
        } while (!$job->is_completed());

        // Verify that job completed successfully and did not produce any errors.
        $this->assertSame(archive_job_status::COMPLETED, $job->get_status(), 'Job should complete successfully');
        $this->assertSame( // Use assertSame instead of assertEmpty to ensure error logs are printed during test execution.
            $job->get_logger()->get_logs(log_level::ERROR),
            [],
            'Job should not produce any error or fatal log events during execution'
        );

        // Ensure that the job actually created an activity archive task and produced an arctifact file.
        $activitytasks = activity_archiving_task::get_by_jobid($job->get_id());
        $this->assertNotEmpty($activitytasks, 'Job should create at least one activity archiving task');
        foreach ($activitytasks as $task) {
            $this->assertTrue($task->is_completed(), 'Activity archiving task should be completed when job is completed');
        }

        $filehandles = file_handle::get_by_jobid($job->get_id());
        $this->assertNotEmpty($filehandles, 'Job should create at least one file handle for the archived content');
    }

    /**
     * Tests deletion and cleanup routine of an archive job.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_delete(): void {
        global $DB;

        // Create a new job and let it complete.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        $jobid = $job->get_id();
        $job->set_metadata_entry('foo', 'bar');
        $job->set_status(archive_job_status::QUEUED);

        $cycle = 0;
        do {
            $job->execute();

            if ($cycle > 5) {
                $this->fail('Job did not complete after 5 execution cycles');
            } else {
                $cycle++;
            }
        } while (!$job->is_completed());

        // Ensure that we have activity archiving tasks and file handles.
        $this->assertNotEmpty(
            activity_archiving_task::get_by_jobid($jobid),
            'Job should create at least one activity archiving task'
        );
        $this->assertNotEmpty(
            file_handle::get_by_jobid($jobid),
            'Job should create at least one file handle for the archived content'
        );

        // Delete the job and check that everything was cleaned up correctly.
        $job->delete();
        $this->assertEmpty(
            $DB->get_records(db_table::JOB->value, ['id' => $jobid]),
            'Job should be deleted from the database'
        );
        $this->assertEmpty(
            $DB->get_records(db_table::METADATA->value, ['jobid' => $jobid]),
            'Job metadata should be deleted from the database'
        );
        $this->assertEmpty(
            $DB->get_records(db_table::TEMPFILE->value, ['jobid' => $jobid]),
            'Job temp files should be deleted from the database'
        );
        $this->assertEmpty(
            $DB->get_records(db_table::ACTIVITY_TASK->value, ['jobid' => $jobid]),
            'Activity archiving tasks should be deleted from the database'
        );
        $this->assertEmpty(
            $DB->get_records(db_table::FILE_HANDLE->value, ['jobid' => $jobid]),
            'Job file handles should be deleted from the database'
        );
        $this->assertEmpty(
            $DB->get_records(db_table::LOG->value, ['jobid' => $jobid]),
            'Job logs should be deleted from the database'
        );
    }

    /**
     * Tests changing task status values and completion criterion.
     *
     * @covers \local_archiving\archive_job
     * @dataProvider status_data_provider
     *
     * @param archive_job_status $oldstatus Old status value to create task with.
     * @param archive_job_status $newstatus New target status value to set.
     * @param bool $completed Whether the job should be considered completed after the status change.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_status(archive_job_status $oldstatus, archive_job_status $newstatus, bool $completed): void {
        // Prepare.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $job->set_status($oldstatus);

        // Ensure that we start with the old status value.
        $this->assertSame($oldstatus, $job->get_status(), 'Job should start with the old status');

        // Change status and check if it was changed correctly.
        $job->set_status($newstatus);
        $this->assertSame($newstatus, $job->get_status(), 'Job status should be changed to the new status');
        $this->assertSame($completed, $job->is_completed(), 'Job completion status should match the expected value');

        // Try to use locally cached version and check that it was updated too.
        $this->assertSame(
            $newstatus,
            $job->get_status(usecached: true),
            'Cached job status should match the new status'
        );
    }

    /**
     * Test data provider for test_status.
     *
     * @return array Test data for test_status.
     */
    public static function status_data_provider(): array {
        $res = [];

        foreach (archive_job_status::cases() as $status) {
            $res[$status->name] = [
                'oldstatus' => archive_job_status::UNKNOWN,
                'newstatus' => $status,
                'completed' => match ($status) {
                    archive_job_status::COMPLETED,
                    archive_job_status::DELETED,
                    archive_job_status::TIMEOUT,
                    archive_job_status::FAILURE,
                        => true,
                    default => false
                },
            ];
        }

        return $res;
    }

    /**
     * Tests timeout detection for an archive job.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_timeout(): void {
        // Create a new job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // Configure timeout to be instant.
        set_config('job_timeout_min', -1, 'local_archiving');

        // Check if the job is considered timed out.
        $this->assertTrue($job->is_overdue(), 'Job should be considered overdue with instant timeout');
    }

    /**
     * Tests the calculation of job progress.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_job_progress_calculation(): void {
        // Prepare job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // Check initial progress.
        $this->assertSame(0, $job->get_progress(), 'Initial job progress should be 0%');
        $job->set_status(archive_job_status::QUEUED);
        $this->assertSame(0, $job->get_progress(), 'Queued jobs are still at 0% progress');

        // Ensure we get progress during activity.
        $intermetiatestates = [
            archive_job_status::ACTIVITY_ARCHIVING,
            archive_job_status::BACKUP_COLLECTION,
            archive_job_status::POST_PROCESSING,
            archive_job_status::STORE,
            archive_job_status::SIGN,
            archive_job_status::CLEANUP,
        ];
        foreach ($intermetiatestates as $state) {
            $job->set_status($state);
            $this->assertGreaterThan(
                0,
                $job->get_progress(),
                "Progress should be greater than 0% during {$state->name} state"
            );
        }

        // Check that final success states reach 100%.
        $completedstates = [
            archive_job_status::COMPLETED,
            archive_job_status::DELETED,
        ];
        foreach ($completedstates as $state) {
            $job->set_status($state);
            $this->assertSame(100, $job->get_progress(), "Progress should be 100% after {$state->name} state");
        }
    }

    /**
     * Tests creation, retrieval, and cleanup of job settings.
     *
     * @covers \local_archiving\archive_job
     * @dataProvider job_settings_data_provider
     *
     * @param \stdClass $settings Settings object to store in the job.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_job_settings(\stdClass $settings): void {
        // Create job with settings.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job(['settings' => $settings]);

        // Retrieve settings and compare.
        $this->assertEquals($settings, $job->get_settings(), 'Job settings should match the provided settings');

        // Check to retrieve a single setting if possible.
        foreach (get_object_vars($settings) as $key => $value) {
            $this->assertEquals(
                $value,
                $job->get_setting($key),
                "Job setting '{$key}' should match the provided value"
            );
        }

        // Cleanup job settings for a completed job.
        $job->set_status(archive_job_status::COMPLETED);
        $job->clear_settings();
        $this->assertEmpty(
            get_object_vars($job->get_settings()),
            'Job settings should be empty after clearing'
        );
    }

    /**
     * Data provider for test_job_settings.
     *
     * @return array Various test data structures to store as job settings.
     */
    public static function job_settings_data_provider(): array {
        return [
            'Simple list' => [(object) [1, 2, 3]],
            'Simple object' => [(object) ['foo' => 'bar', 'baz' => 42]],
            'Nested object' => [(object) [
                'foo' => 'bar',
                'nested' => (object) [
                    'lorem' => 'ipsum',
                    'dolor' => 123,
                    'nestedagain' => (object) [
                        'foo' => 'bar',
                    ],
                ],
            ]],
        ];
    }

    /**
     * Tests that clearing settings for an incomplete job produces an error.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_job_settings_clear_incomplete_job(): void {
        // Create job with settings.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job(['settings' => (object) ['foo' => 'bar']]);

        // Cleanup job settings for an incomplete job.
        $this->expectException(\moodle_exception::class, 'Job settings should not be cleared for incomplete jobs');
        $job->clear_settings();
        $this->assertNotEmpty($job->get_settings(), 'Job settings should not be cleared for incomplete jobs');
    }

    /**
     * Tests that retrieving non-existing job settings fails according to requested strictness.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_job_settings_retrieve_nonexisting(): void {
        // Create job with no settings.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job(['settings' => (object) []]);

        // Try to retrieve a non-existing setting (silent fail).
        $this->assertNull(
            $job->get_setting('nonexisting', strict: false),
            'Retrieving a non-existing setting should return null'
        );

        // Try to retrieve a non-existing setting with strict mode.
        $this->expectException(
            \coding_exception::class,
            'Retrieving a non-existing setting with strict mode should throw an exception'
        );
        $job->get_setting('nonexisting', strict: true);
    }

    /**
     * Tests creation, retrieval, and cleanup of job metadata.
     *
     * @covers \local_archiving\archive_job
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_job_metadata(): void {
        // Create test job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // A fresh job should not have any metadata.
        $this->assertEmpty($job->get_metadata_entries(), 'Newly created job should not have any metadata entries');

        // Add single metadata entry.
        $job->set_metadata_entry('foo', 'bar');
        $this->assertEquals(['foo' => 'bar'], $job->get_metadata_entries());
        $this->assertEquals('bar', $job->get_metadata_entry('foo'));

        // Add more entries.
        $job->set_metadata_entry('bar', '42');
        $job->set_metadata_entry('baz', 1337);
        $this->assertCount(3, $job->get_metadata_entries(), 'Job should have three metadata entries now');

        // Override one entry.
        $job->set_metadata_entry('foo', 'lorem ipsum');
        $this->assertCount(3, $job->get_metadata_entries(), 'Job should update existing metadata entries');
        $this->assertSame('lorem ipsum', $job->get_metadata_entry('foo'), 'Update of existing metadata entry failed');

        // Retrieve non-existing metadata entry (fail silently).
        $this->assertNull(
            $job->get_metadata_entry('nonexisting', strict: false),
            'Retrieving a non-existing metadata entry should return null'
        );

        // Retrieve non-existing metadata entry (strict mode).
        $this->expectException(
            \coding_exception::class,
            'Retrieving a non-existing metadata entry with strict mode should throw an exception'
        );
        $job->get_metadata_entry('nonexisting', strict: true);
    }

    /**
     * Tests variable-based generation of archive name prefixes.
     *
     * @covers \local_archiving\archive_job
     * @dataProvider archive_name_prefix_generation_data_provider
     *
     * @param string $pattern Archive filename pattern to test.
     * @param bool $isvalid Whether the pattern is expected to be valid or not.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_archive_name_prefix_generation(string $pattern, bool $isvalid): void {
        // Prepare job instance.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job([
            'settings' => (object) [
                'archive_filename_pattern' => $pattern,
            ],
        ]);

        // Expect failure if pattern is invalid.
        if (!$isvalid) {
            $this->expectException(
                \invalid_parameter_exception::class,
                'Invalid archive filename pattern not detected!'
            );
        }

        // Generate archive name prefix.
        $this->assertNotEmpty($job->generate_archive_name_prefix());
    }

    /**
     * Data provider for test_archive_name_prefix_generation.
     *
     * @return array[] Array of test cases
     */
    public static function archive_name_prefix_generation_data_provider(): array {
        return [
            'Default pattern' => [
                'pattern' => 'archive-${courseshortname}-${courseid}-${cmtype}-${cmname}-${cmid}_${date}-${time}',
                'isvalid' => true,
            ],
            'All allowed variables' => [
                'pattern' => array_reduce(
                    \local_archiving\type\archive_filename_variable::values(),
                    function ($carry, $item) {
                        return $carry . '${' . $item . '}';
                    },
                    ''
                ),
                'isvalid' => true,
            ],
            'Allowed variables with additional brackets' => [
                'pattern' => 'quiz-{cmname}_${cmname}-{cmid}_${cmid}',
                'isvalid' => true,
            ],
            'Invalid variable' => [
                'pattern' => 'Foo ${foo} Bar ${bar} Baz ${baz}',
                'isvalid' => false,
            ],
            'Forbidden characters' => [
                'pattern' => 'quiz-archive: foo!bar',
                'isvalid' => false,
            ],
            'Slashes' => [
                'pattern' => 'foo/bar',
                'isvalid' => false,
            ],
            'Only invalid characters' => [
                'pattern' => '.!',
                'isvalid' => false,
            ],
            'Dot' => [
                'pattern' => '.',
                'isvalid' => false,
            ],
            'Empty pattern' => [
                'pattern' => '',
                'isvalid' => false,
            ],
        ];
    }

}
