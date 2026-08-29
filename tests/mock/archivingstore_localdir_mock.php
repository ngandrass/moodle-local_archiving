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
 * Mock driver for storing archive data inside a directory on the local
 * filesystem during unit tests.
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\file_handle;
use local_archiving\local\exception\storage_exception;
use local_archiving\local\type\storage_tier;
use local_archiving\storage;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Driver for storing archive data inside a directory on the local filesystem
 */
class archivingstore_localdir_mock extends \local_archiving\local\driver\archivingstore {
    /**
     * @var bool If true, retrieve() throws a storage_exception instead of succeeding.
     * Used to test error-handling paths of callers.
     */
    public static bool $forcefailretrieve = false;

    /**
     * @var bool If true, retrieve() throws the same "cancelled" storage_exception a real driver's
     * progress callback would throw mid-transfer, simulating a cancellation noticed during the transfer.
     */
    public static bool $cancelduringretrieve = false;

    #[\Override]
    public function is_enabled(): bool {
        return true;
    }

    #[\Override]
    public static function is_ready(): bool {
        return true;
    }


    #[\Override]
    public static function get_storage_tier(): storage_tier {
        return storage_tier::LOCAL;
    }

    #[\Override]
    public static function supports_retrieve(): bool {
        return true;
    }

    #[\Override]
    public function is_available(): bool {
        return true;
    }

    #[\Override]
    public function get_free_bytes(): ?int {
        return 1024 * 1024 * 1024; // There is always one gigabyte just waiting for you ...
    }

    #[\Override]
    public function store(int $jobid, \stored_file $file, string $path, ?callable $progresscallback = null): file_handle {
        // Only create file handles.
        $handle = file_handle::create(
            jobid: $jobid,
            archivingstorename: 'localdir',
            filename: $file->get_filename(),
            filepath: trim($path, '/'),
            filesize: $file->get_filesize(),
            sha256sum: storage::hash_file($file),
            mimetype: $file->get_mimetype()
        );

        return $handle;
    }

    #[\Override]
    public function retrieve(file_handle $handle, \stdClass $fileinfo, ?callable $progresscallback = null): \stored_file {
        if (self::$cancelduringretrieve) {
            throw new storage_exception('error_retrieval_cancelled', 'local_archiving');
        }

        if (self::$forcefailretrieve) {
            throw new storage_exception('filenotfound', 'error');
        }

        return get_file_storage()->create_file_from_string(
            $fileinfo,
            'Mock test file content for file handle with ID ' . $handle->id
        );
    }

    #[\Override]
    public function delete(file_handle $handle, bool $strict = false): void {
        return;
    }
}
