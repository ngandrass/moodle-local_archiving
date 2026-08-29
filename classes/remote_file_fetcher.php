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
 * State tracking class for on-demand file fetching operations.
 *
 * @package     local_archiving
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use core\exception\coding_exception;
use local_archiving\local\exception\storage_exception;
use local_archiving\local\type\file_fetch_status;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Tracks the progress of on-demand retrievals of files from (remote) storages.
 *
 * Ongoing fetch operations are tracked inside a Moodle application cache (see
 * db/caches.php). Fetch operations are keyed by the file handle ID to prevent
 * concurrent retrievals of the same file.
 */
final class remote_file_fetcher {
    /** @var int Seconds after which a queued but not yet started fetch operation is considered stale */
    public const QUEUED_STALE_SECONDS = 10 * MINSECS;

    /** @var int Seconds after which a running fetch without progress updates is considered stale */
    public const FETCHING_STALE_SECONDS = 2 * MINSECS;

    /** @var int Minimum number of seconds between two progress updates */
    private const PROGRESS_THROTTLE_SECONDS = 5;

    /** @var int Minimum number of seconds between two cancellation checks inside the progress callback */
    private const CANCEL_CHECK_THROTTLE_SECONDS = 1;

    /**
     * Returns the cache instance backing this class
     *
     * @return \cache Cache instance
     */
    private static function cache(): \cache {
        return \cache::make('local_archiving', 'filefetching');
    }

    /**
     * Retrieves the current retrieval record for the given file handle
     *
     * @param int $filehandleid ID of the file handle to look up
     * @return array|null Retrieval record or null if none exists
     * @throws coding_exception
     */
    public static function get(int $filehandleid): ?array {
        $record = self::cache()->get($filehandleid);

        // Handle non existing records.
        if ($record === false) {
            return null;
        }

        // Mark stale entries as failed.
        if (self::is_stale($record)) {
            self::mark_failed($filehandleid, get_string('fetch_status_stale', 'local_archiving'));
            $record = self::get($filehandleid);
        }

        return $record;
    }

    /**
     * Acquires a lock on the given file handle's retrieval cache entry
     *
     * Can be used to ensure that only a single caller is attempting to start or
     * cancel a retrieval for a given file handle at any given time.
     *
     * ATTENTION: The caller must call unlock() to release the lock once done!
     *
     * @param int $filehandleid ID of the file handle to lock
     * @return bool True if the lock was acquired, false if another caller currently holds it
     */
    public static function lock(int $filehandleid): bool {
        try {
            return self::cache()->acquire_lock($filehandleid);
        } catch (\moodle_exception $e) {
            return false;
        }
    }

    /**
     * Releases a previously acquired lock on the given file handle's retrieval cache entry
     *
     * @param int $filehandleid ID of the file handle to unlock
     * @return void
     */
    public static function unlock(int $filehandleid): void {
        self::cache()->release_lock($filehandleid);
    }

    /**
     * Writes a retrieval record for the given file handle
     *
     * @param int $filehandleid ID of the file handle to write a record for
     * @param file_fetch_status $status Status to record
     * @param int $bytestransferred Number of bytes transferred so far
     * @param int $bytestotal Total number of bytes to transfer
     * @param string|null $error Error message, only meaningful for fetch_status::FAILED
     * @param bool $cancelrequested Whether a cancellation is currently requested for this file handle.
     * @return void
     */
    private static function write(
        int $filehandleid,
        file_fetch_status $status,
        int $bytestransferred = 0,
        int $bytestotal = 0,
        ?string $error = null,
        bool $cancelrequested = false
    ): void {
        self::cache()->set($filehandleid, [
            'status' => $status->value,
            'bytestransferred' => $bytestransferred,
            'bytestotal' => $bytestotal,
            'error' => $error,
            'lastupdated' => time(),
            'cancelrequested' => $cancelrequested,
        ]);
    }

    /**
     * Marks the given file handle as queued for retrieval
     *
     * @param int $filehandleid ID of the file handle
     * @return void
     */
    public static function mark_queued(int $filehandleid): void {
        self::write($filehandleid, file_fetch_status::QUEUED);
    }

    /**
     * Marks the given file handle as currently being fetched and update the progress.
     *
     * This method should be called repeatedly during the fetch operation to update
     * the progress of the retrieval.
     *
     * @param int $filehandleid ID of the file handle
     * @param int $bytestransferred Number of bytes transferred so far
     * @param int $bytestotal Total number of bytes to transfer
     * @return void
     * @throws coding_exception
     */
    public static function mark_fetching(int $filehandleid, int $bytestransferred, int $bytestotal): void {
        self::write(
            $filehandleid,
            file_fetch_status::FETCHING,
            $bytestransferred,
            $bytestotal,
            cancelrequested: self::is_cancel_requested($filehandleid)
        );
    }

    /**
     * Marks the given file handle as successfully retrieved
     *
     * @param int $filehandleid ID of the file handle
     * @return void
     */
    public static function mark_complete(int $filehandleid): void {
        self::write($filehandleid, file_fetch_status::COMPLETE);
    }

    /**
     * Marks the given file handle as failed to retrieve
     *
     * @param int $filehandleid ID of the file handle
     * @param string $error Human-readable error message
     * @return void
     */
    public static function mark_failed(int $filehandleid, string $error): void {
        self::write($filehandleid, file_fetch_status::FAILED, error: $error);
    }

    /**
     * Determines whether the given retrieval record is stale, i.e. it has not been updated in a
     * reasonable amount of time given its current status.
     *
     * @param array $record Retrieval record to check
     * @return bool True if the record is stale
     */
    public static function is_stale(array $record): bool {
        $threshold = match ($record['status']) {
            file_fetch_status::QUEUED->value => self::QUEUED_STALE_SECONDS,
            file_fetch_status::FETCHING->value => self::FETCHING_STALE_SECONDS,
            default => null,
        };

        if ($threshold === null) {
            return false;
        }

        return (time() - $record['lastupdated']) > $threshold;
    }

    /**
     * Requests cancellation of an in-flight retrieval for the given file handle
     *
     * No-op if no record currently exists for this file handle (nothing to cancel).
     *
     * @param int $filehandleid ID of the file handle
     * @return void
     * @throws coding_exception
     */
    public static function request_cancel(int $filehandleid): void {
        $record = self::get($filehandleid);
        if ($record === null) {
            return;
        }

        $record['cancelrequested'] = true;
        self::cache()->set($filehandleid, $record);
    }

    /**
     * Determines whether cancellation has been requested for the given file handle
     *
     * @param int $filehandleid ID of the file handle
     * @return bool True if a cancellation is currently requested
     * @throws coding_exception
     */
    public static function is_cancel_requested(int $filehandleid): bool {
        return self::get($filehandleid)['cancelrequested'] ?? false;
    }

    /**
     * Clears the retrieval cache entry for the given file handle entirely
     *
     * @param int $filehandleid ID of the file handle
     * @return void
     */
    public static function delete(int $filehandleid): void {
        self::cache()->delete($filehandleid);
    }

    /**
     * Builds a progress callback for archivingstore::retrieve() that reports retrieval progress
     * into this cache, and signals cancellation requests back to the caller.
     *
     * @param int $filehandleid ID of the file handle to report progress for
     * @return callable A callback with signature function(int $bytesreceived, int $bytestotal):
     * void, throwing a storage_exception to request that the caller abort the transfer
     */
    public static function progress_callback(int $filehandleid): callable {
        // Prepare state to be inherrited by the colsure.
        $lastupdatetime = 0;
        $lastcancelchecktime = 0;
        $loggedcomplete = false;

        // Build the progress callback closure.
        return function (
            int $bytesreceived,
            int $bytestotal
        ) use (
            $filehandleid,
            &$lastupdatetime,
            &$lastcancelchecktime,
            &$loggedcomplete
        ): void {
            $now = time();

            // Handle cancelation requests.
            if ($now - $lastcancelchecktime >= self::CANCEL_CHECK_THROTTLE_SECONDS) {
                $lastcancelchecktime = $now;
                if (self::is_cancel_requested($filehandleid)) {
                    throw new storage_exception('error_retrieval_cancelled', 'local_archiving');
                }
            }

            // We do not work for nothing!
            if ($bytestotal <= 0) {
                return;
            }

            // Ensure we record 0% and 100% progress, but everything inbetween only throttled.
            $iscomplete = $bytesreceived >= $bytestotal;

            if ($iscomplete) {
                if ($loggedcomplete) {
                    return;
                }
                $loggedcomplete = true;
            } else if ($now - $lastupdatetime < self::PROGRESS_THROTTLE_SECONDS) {
                return;
            }

            // Update cache record.
            $lastupdatetime = $now;
            self::mark_fetching($filehandleid, $bytesreceived, $bytestotal);
        };
    }
}
