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

use core\exception\coding_exception;
use local_archiving\local\exception\storage_exception;
use local_archiving\local\type\file_fetch_status;

/**
 * Tests for the remote_file_fetcher class
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for remote_file_fetcher
 */
final class remote_file_fetcher_test extends \advanced_testcase {
    /**
     * Tests that no record exists for a file handle that was never touched.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_get_non_existing(): void {
        $this->resetAfterTest();
        $this->assertNull(remote_file_fetcher::get(1234567), 'Expected null for a file handle with no record.');
    }

    /**
     * Tests marking a file handle as queued.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_mark_queued(): void {
        $this->resetAfterTest();
        remote_file_fetcher::mark_queued(1);

        $record = remote_file_fetcher::get(1);
        $this->assertSame(file_fetch_status::QUEUED->value, $record['status']);
        $this->assertSame(0, $record['bytestransferred']);
        $this->assertSame(0, $record['bytestotal']);
        $this->assertNull($record['error']);
    }

    /**
     * Tests marking a file handle as currently fetching.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_mark_fetching(): void {
        $this->resetAfterTest();
        remote_file_fetcher::mark_fetching(1, 50, 200);

        $record = remote_file_fetcher::get(1);
        $this->assertSame(file_fetch_status::FETCHING->value, $record['status']);
        $this->assertSame(50, $record['bytestransferred']);
        $this->assertSame(200, $record['bytestotal']);
    }

    /**
     * Tests marking a file handle as successfully retrieved.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_mark_complete(): void {
        $this->resetAfterTest();
        remote_file_fetcher::mark_complete(1);

        $record = remote_file_fetcher::get(1);
        $this->assertSame(file_fetch_status::COMPLETE->value, $record['status']);
    }

    /**
     * Tests marking a file handle as failed to retrieve.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_mark_failed(): void {
        $this->resetAfterTest();
        remote_file_fetcher::mark_failed(1, 'Something went wrong');

        $record = remote_file_fetcher::get(1);
        $this->assertSame(file_fetch_status::FAILED->value, $record['status']);
        $this->assertSame('Something went wrong', $record['error']);
    }

    /**
     * Tests staleness detection for records in various states.
     *
     * @covers \local_archiving\remote_file_fetcher
     * @dataProvider is_stale_data_provider
     *
     * @param string $status Status to test staleness for
     * @param int $ageseconds How many seconds in the past to backdate lastupdated
     * @param bool $expected Expected staleness result
     * @return void
     */
    public function test_is_stale(string $status, int $ageseconds, bool $expected): void {
        $record = [
            'status' => $status,
            'bytestransferred' => 0,
            'bytestotal' => 0,
            'error' => null,
            'lastupdated' => time() - $ageseconds,
        ];

        $this->assertSame($expected, remote_file_fetcher::is_stale($record));
    }

    /**
     * Data provider for test_is_stale.
     *
     * @return array[] Test cases
     */
    public static function is_stale_data_provider(): array {
        return [
            'queued, fresh' => [file_fetch_status::QUEUED->value, 10, false],
            'queued, stale' => [file_fetch_status::QUEUED->value, remote_file_fetcher::QUEUED_STALE_SECONDS + 1, true],
            'fetching, fresh' => [file_fetch_status::FETCHING->value, 5, false],
            'fetching, stale' => [file_fetch_status::FETCHING->value, remote_file_fetcher::FETCHING_STALE_SECONDS + 1, true],
            'complete, never stale' => [file_fetch_status::COMPLETE->value, YEARSECS, false],
            'failed, never stale' => [file_fetch_status::FAILED->value, YEARSECS, false],
        ];
    }

    /**
     * Tests that no progress is recorded when the reported total size is zero.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_progress_callback_zero_total(): void {
        $this->resetAfterTest();
        $callback = remote_file_fetcher::progress_callback(1);
        $callback(0, 0);

        $this->assertNull(remote_file_fetcher::get(1), 'No record should be written.');
    }

    /**
     * Tests that the first observed progress tick is always recorded.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_progress_callback_records_first_tick(): void {
        $this->resetAfterTest();
        $callback = remote_file_fetcher::progress_callback(1);
        $callback(50, 200);

        $record = remote_file_fetcher::get(1);
        $this->assertSame(file_fetch_status::FETCHING->value, $record['status']);
        $this->assertSame(50, $record['bytestransferred']);
        $this->assertSame(200, $record['bytestotal']);
    }

    /**
     * Tests that intermediate progress ticks are throttled to at most one write every 10 seconds.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_progress_callback_throttles_intermediate_ticks(): void {
        $this->resetAfterTest();
        $callback = remote_file_fetcher::progress_callback(1);
        $callback(50, 200);
        $callback(100, 200);

        $record = remote_file_fetcher::get(1);
        $this->assertSame(50, $record['bytestransferred'], 'A second tick within 10 seconds should not update the record.');
    }

    /**
     * Tests that the final 100% completion tick is always recorded, even within the throttle window.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_progress_callback_always_records_completion(): void {
        $this->resetAfterTest();
        $callback = remote_file_fetcher::progress_callback(1);
        $callback(50, 200);
        $callback(200, 200);

        $record = remote_file_fetcher::get(1);
        $this->assertSame(200, $record['bytestransferred'], 'Completion tick should always be recorded.');
    }

    /**
     * Tests that the progress callback throws a cancellation-tagged storage_exception once
     * cancellation has been requested, even when the total size is zero/unknown.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_progress_callback_throws_when_cancelled(): void {
        $this->resetAfterTest();
        remote_file_fetcher::mark_queued(1);
        remote_file_fetcher::request_cancel(1);

        $callback = remote_file_fetcher::progress_callback(1);
        try {
            $callback(0, 0);
            $this->fail('Expected a storage_exception to be thrown even with an unknown total size.');
        } catch (storage_exception $e) {
            $this->assertSame('error_retrieval_cancelled', $e->errorcode);
        }
    }

    /**
     * Tests requesting and querying cancellation for a file handle with an existing record.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_request_cancel(): void {
        $this->resetAfterTest();
        remote_file_fetcher::mark_fetching(1, 50, 200);
        $this->assertFalse(remote_file_fetcher::is_cancel_requested(1));

        remote_file_fetcher::request_cancel(1);

        $this->assertTrue(remote_file_fetcher::is_cancel_requested(1));
        // Cancelling must not otherwise disturb the record.
        $record = remote_file_fetcher::get(1);
        $this->assertSame(file_fetch_status::FETCHING->value, $record['status']);
        $this->assertSame(50, $record['bytestransferred']);
    }

    /**
     * Tests that requesting cancellation for a file handle with no existing record is a no-op.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_request_cancel_noop_without_existing_record(): void {
        $this->resetAfterTest();
        remote_file_fetcher::request_cancel(1234567);

        $this->assertNull(remote_file_fetcher::get(1234567), 'No record should be created by request_cancel().');
        $this->assertFalse(remote_file_fetcher::is_cancel_requested(1234567));
    }

    /**
     * Tests that mark_fetching() preserves a previously requested cancellation across progress
     * writes, while a fresh mark_queued() resets it.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_cancel_flag_preserved_across_fetching_but_reset_by_queued(): void {
        $this->resetAfterTest();
        remote_file_fetcher::mark_fetching(1, 0, 200);
        remote_file_fetcher::request_cancel(1);
        $this->assertTrue(remote_file_fetcher::is_cancel_requested(1));

        // A subsequent progress tick must not clear the cancel flag.
        remote_file_fetcher::mark_fetching(1, 50, 200);
        $this->assertTrue(remote_file_fetcher::is_cancel_requested(1), 'mark_fetching() should preserve in-flight cancel request.');

        // Starting a brand new attempt must reset it.
        remote_file_fetcher::mark_queued(1);
        $this->assertFalse(remote_file_fetcher::is_cancel_requested(1), 'mark_queued() should start with a clean cancel flag.');
    }

    /**
     * Tests that delete() clears the record entirely.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     * @throws coding_exception
     */
    public function test_delete(): void {
        $this->resetAfterTest();
        remote_file_fetcher::mark_fetching(1, 50, 200);
        $this->assertNotNull(remote_file_fetcher::get(1));

        remote_file_fetcher::delete(1);

        $this->assertNull(remote_file_fetcher::get(1));
    }

    /**
     * Tests acquiring and releasing a lock for a file handle.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     */
    public function test_acquire_and_release_lock(): void {
        $this->resetAfterTest();
        $this->assertTrue(remote_file_fetcher::lock(1), 'Lock should be acquired when free.');
        remote_file_fetcher::unlock(1);

        // Should be acquirable again after release.
        $this->assertTrue(remote_file_fetcher::lock(1), 'Lock should be re-acquirable after release.');
        remote_file_fetcher::unlock(1);
    }

    /**
     * Tests that a second, concurrent acquire_lock() call for the same file handle fails
     * (non-blocking) while the first lock is still held.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     */
    public function test_acquire_lock_fails_while_held(): void {
        $this->resetAfterTest();
        $this->assertTrue(remote_file_fetcher::lock(1));

        $this->assertFalse(
            remote_file_fetcher::lock(1),
            'A second concurrent acquire_lock() for the same file handle should fail while held.'
        );

        remote_file_fetcher::unlock(1);
    }

    /**
     * Tests that locks are independent per file handle: locking one id does not block another.
     *
     * @covers \local_archiving\remote_file_fetcher
     *
     * @return void
     */
    public function test_lock_is_scoped_per_filehandle(): void {
        $this->resetAfterTest();
        $this->assertTrue(remote_file_fetcher::lock(1));
        $this->assertTrue(
            remote_file_fetcher::lock(2),
            'Locking one file handle must not block locking a different one.'
        );

        remote_file_fetcher::unlock(1);
        remote_file_fetcher::unlock(2);
    }
}
