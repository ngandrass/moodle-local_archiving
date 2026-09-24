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

use local_archiving\file_handle;
use local_archiving\local\exception\storage_exception;
use local_archiving\local\type\file_fetch_status;
use local_archiving\remote_file_fetcher;

/**
 * Tests for the retrieve_remote_file ad-hoc task.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the retrieve_remote_file ad-hoc task.
 */
final class retrieve_remote_file_test extends \advanced_testcase {
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
     * Creates a file handle linked to a freshly created archive job.
     *
     * @param array $params Optional parameters to override defaults.
     * @return file_handle Freshly created file handle.
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function create_handle(array $params = []): file_handle {
        $job = $this->generator()->create_archive_job();

        return $this->generator()->create_file_handle(array_merge([
            'jobid' => $job->get_id(),
            'archivingstorename' => 'localdir',
        ], $params));
    }

    /**
     * Tests creating and queueing a new task instance works without errors.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_and_queue(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();

        $this->assertEmpty(
            \core\task\manager::get_adhoc_tasks(retrieve_remote_file::class),
            'There should be no existing ad-hoc tasks for retrieve_remote_file.'
        );

        $task = retrieve_remote_file::create($handle, get_admin()->id);
        \core\task\manager::queue_adhoc_task($task);

        $this->assertCount(
            1,
            \core\task\manager::get_adhoc_tasks(retrieve_remote_file::class),
            'There should be exactly one ad-hoc task for retrieve_remote_file.'
        );
    }

    /**
     * Tests that a deleted file handle can not be queued for retrieval.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_for_deleted_handle(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $handle->mark_as_deleted();

        $this->expectException(\moodle_exception::class);
        retrieve_remote_file::create($handle, get_admin()->id);
    }

    /**
     * Tests a successful execution of the task.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_success(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $task = retrieve_remote_file::create($handle, get_admin()->id);

        ob_start();
        $task->execute();
        ob_end_clean();

        $record = remote_file_fetcher::get($handle->id);
        $this->assertSame(file_fetch_status::COMPLETE->value, $record['status']);
        $this->assertInstanceOf(
            \stored_file::class,
            file_handle::get_by_id($handle->id)->get_local_file(),
            'File should be cached locally after successful retrieval.'
        );
    }

    /**
     * Tests that execution exits without error if the file is already cached by the
     * time the task runs.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function test_execute_already_cached(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $this->generator()->create_filestore_cache_file($handle->id);

        $task = retrieve_remote_file::create($handle, get_admin()->id);

        ob_start();
        $task->execute();
        ob_end_clean();

        $record = remote_file_fetcher::get($handle->id);
        $this->assertSame(file_fetch_status::COMPLETE->value, $record['status']);
    }

    /**
     * Tests that execution handles a file being deleted between task creation
     * and task execution gracefully.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_deleted_between_queue_and_run(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $task = retrieve_remote_file::create($handle, get_admin()->id);
        $handle->mark_as_deleted();

        ob_start();
        $task->execute();
        ob_end_clean();

        $record = remote_file_fetcher::get($handle->id);
        $this->assertSame(file_fetch_status::FAILED->value, $record['status']);
    }

    /**
     * Tests that a storage_exception raised by the storage driver is recorded
     * and re-thrown.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_storage_exception(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $task = retrieve_remote_file::create($handle, get_admin()->id);

        \archivingstore_localdir_mock::$forcefailretrieve = true;
        try {
            ob_start();
            try {
                $task->execute();
                $this->fail('Expected a storage_exception to be thrown.');
            } catch (storage_exception) { // phpcs:ignore
                // Expected.
            }
        } finally {
            ob_end_clean();
            \archivingstore_localdir_mock::$forcefailretrieve = false;
        }

        $record = remote_file_fetcher::get($handle->id);
        $this->assertSame(file_fetch_status::FAILED->value, $record['status']);
    }

    /**
     * Tests that execution exits cleanly if cancellation was requested before
     * the task got a chance to start.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_cancelled_before_start(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $task = retrieve_remote_file::create($handle, get_admin()->id);

        remote_file_fetcher::mark_queued($handle->id);
        remote_file_fetcher::request_cancel($handle->id);

        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertNull(remote_file_fetcher::get($handle->id), 'Record should be cleared, not marked failed.');
        $this->assertNull(
            file_handle::get_by_id($handle->id)->get_local_file(),
            'The driver should never have been invoked.'
        );
    }

    /**
     * Tests that a storage_exception raised while a cancellation is in flight is treated as a
     * deliberate cancellation.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_cancelled_during_fetch(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $task = retrieve_remote_file::create($handle, get_admin()->id);

        \archivingstore_localdir_mock::$cancelduringretrieve = true;
        try {
            ob_start();
            $task->execute();
            ob_end_clean();
        } finally {
            \archivingstore_localdir_mock::$cancelduringretrieve = false;
        }

        $this->assertNull(remote_file_fetcher::get($handle->id), 'Record should be cleared, not marked failed.');
    }

    /**
     * Tests that execute() returns cleanly, without throwing, if the file handle backing this
     * task's custom data no longer exists at all (as opposed to being merely soft-deleted).
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_file_handle_no_longer_exists(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $task = retrieve_remote_file::create($handle, get_admin()->id);

        // Hard-delete the underlying row, as archive_job::delete() -> file_handle::destroy() does.
        $handle->destroy();

        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertNull(remote_file_fetcher::get($handle->id), 'No record should have been written.');
    }

    /**
     * Tests that cancel_and_purge() removes a not-yet-started queued task and its tracking record.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_cancel_and_purge_removes_queued_task_and_record(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();
        $task = retrieve_remote_file::create($handle, get_admin()->id);
        \core\task\manager::queue_adhoc_task($task);
        remote_file_fetcher::mark_queued($handle->id);

        retrieve_remote_file::cancel_and_purge($handle->id);

        $this->assertEmpty(
            \core\task\manager::get_adhoc_tasks(retrieve_remote_file::class),
            'The queued task should have been deleted.'
        );
        $this->assertNull(remote_file_fetcher::get($handle->id), 'The tracking record should have been purged.');
    }

    /**
     * Tests that cancel_and_purge() is a no-op when nothing is queued or tracked
     * for the given file handle.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_cancel_and_purge_noop_when_nothing_queued(): void {
        $this->resetAfterTest();
        $handle = $this->create_handle();

        retrieve_remote_file::cancel_and_purge($handle->id);

        $this->assertNull(remote_file_fetcher::get($handle->id));
    }

    /**
     * Tests that cancel_and_purge() leaves an already-running task in place but
     * still requests cooperative cancellation for it.
     *
     * @covers \local_archiving\task\retrieve_remote_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_cancel_and_purge_requests_cancel_for_running_task(): void {
        global $DB;

        $this->resetAfterTest();
        $handle = $this->create_handle();
        $task = retrieve_remote_file::create($handle, get_admin()->id);
        \core\task\manager::queue_adhoc_task($task);
        remote_file_fetcher::mark_fetching($handle->id, 0, $handle->filesize);

        // Simulate the task having already started running.
        $DB->set_field('task_adhoc', 'timestarted', time(), ['classname' => '\\' . retrieve_remote_file::class]);

        retrieve_remote_file::cancel_and_purge($handle->id);

        $this->assertCount(
            1,
            \core\task\manager::get_adhoc_tasks(retrieve_remote_file::class),
            'A running task should not be deleted.'
        );
        $this->assertTrue(
            remote_file_fetcher::is_cancel_requested($handle->id),
            'Cooperative cancellation should have been requested for the running task.'
        );
    }
}
