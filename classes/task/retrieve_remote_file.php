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
 * Ad-hoc task for retrieving a single file from a (remote) storage
 *
 * @package     local_archiving
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\task;

use local_archiving\file_handle;
use local_archiving\local\exception\storage_exception;
use local_archiving\remote_file_fetcher;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Ad-hoc task that retrieves a single file from a (remote) storage driver on demand,
 * reporting progress via remote_file_fetcher.
 */
class retrieve_remote_file extends \core\task\adhoc_task {
    /**
     * Creates a new task instance that retrieves the given file
     *
     * @param file_handle $handle File handle of the file to retrieve
     * @param int $userid ID of the user who triggered this retrieval
     * @return retrieve_remote_file New task instance
     * @throws \moodle_exception If the file handle was already deleted
     */
    public static function create(file_handle $handle, int $userid): retrieve_remote_file {
        if ($handle->deleted) {
            throw new \moodle_exception('deleted_file_can_not_be_retrieved', 'local_archiving');
        }

        $task = new self();
        $task->set_custom_data((object) [
            'filehandleid' => $handle->id,
        ]);
        $task->set_userid($userid);
        $task->set_attempts_available(1);

        return $task;
    }

    /**
     * Retrieves the file handle this task is associated with
     *
     * @return file_handle File handle this task retrieves the artifact for
     * @throws \dml_exception
     */
    public function get_file_handle(): file_handle {
        return file_handle::get_by_id($this->get_custom_data()->filehandleid);
    }

    /**
     * Cancels any outstanding retrieval for the given file handle and purges its tracking state
     *
     * Deletes any not-yet-started adhoc task queued for this file handle outright. If a task is
     * already running (and thus not deletable this way), requests cooperative cancellation
     * instead - the running task's own is_cancel_requested() checks in execute() will notice and
     * clean up. Always purges the remote_file_fetcher cache record regardless of task state, so no
     * stale entry can outlive the file handle it refers to.
     *
     * @param int $filehandleid ID of the file handle to cancel and purge retrieval state for
     * @return void
     */
    public static function cancel_and_purge(int $filehandleid): void {
        // Handle pending tasks.
        foreach (\core\task\manager::get_adhoc_tasks(self::class, skiprunning: true) as $task) {
            if ($task->get_custom_data()->filehandleid === $filehandleid) {
                \core\task\manager::delete_adhoc_task($task->get_id());
                remote_file_fetcher::delete($filehandleid);
            }
        }

        // Request cancellation for running tasks.
        remote_file_fetcher::request_cancel($filehandleid);
    }

    #[\Override]
    public function execute(): void {
        // Validate file handle.
        try {
            $handle = $this->get_file_handle();
        } catch (\dml_exception $e) {
            mtrace("File handle for this retrieval task no longer exists, nothing to do.");
            return;
        }
        $filehandleid = $handle->id;

        // Catch alreday cached files.
        if ($handle->get_local_file()) {
            mtrace("File handle {$filehandleid} already cached, nothing to do.");
            remote_file_fetcher::mark_complete($filehandleid);
            return;
        }

        // Prevent run if file was deleted after task was scheduled.
        if ($handle->deleted) {
            mtrace("File handle {$filehandleid} was deleted before retrieval could start.");
            remote_file_fetcher::mark_failed(
                $filehandleid,
                get_string('deleted_file_can_not_be_retrieved', 'local_archiving')
            );
            return;
        }

        // Honor cancellation requests.
        if (remote_file_fetcher::is_cancel_requested($filehandleid)) {
            mtrace("Retrieval for file handle {$filehandleid} was cancelled before it started.");
            remote_file_fetcher::delete($filehandleid);
            return;
        }

        // Fetch the requested file.
        try {
            mtrace("Retrieving remote file for file handle {$filehandleid} ...");
            remote_file_fetcher::mark_fetching($filehandleid, 0, $handle->filesize);

            $handle->archivingstore()->retrieve(
                $handle,
                $handle->generate_retrieval_fileinfo_record(),
                remote_file_fetcher::progress_callback($filehandleid)
            );

            remote_file_fetcher::mark_complete($filehandleid);
            mtrace("Retrieval for file handle {$filehandleid} complete.");
        } catch (storage_exception $e) {
            // Handle cooperative fetch cancellation.
            if ($e->errorcode === 'error_retrieval_cancelled') {
                mtrace("Retrieval for file handle {$filehandleid} was cancelled.");
                remote_file_fetcher::delete($filehandleid);
                return;
            }

            // Any other error.
            remote_file_fetcher::mark_failed($filehandleid, $e->getMessage());
            mtrace("Retrieval for file handle {$filehandleid} failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
