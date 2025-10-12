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
 * Interface definitions for storage drivers
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver;

use local_archiving\exception\storage_exception;
use local_archiving\file_handle;
use local_archiving\type\storage_tier;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Interface for storage driver (archivingstore) sub-plugins
 */
abstract class archivingstore extends base {
    /**
     * Returns the storage tier of this storage plugin.
     *
     * This is used to classify the storage plugin based on the way stored data
     * is accessed and retrieved. See storage_tier type for more details.
     *
     * @return storage_tier Storage tier for this storage plugin
     */
    abstract public static function get_storage_tier(): storage_tier;

    /**
     * Determines if this storage plugin supports retrieving previously stored
     * files from the storage.
     *
     * @return bool True if file retrieval is supported, false otherwise
     */
    abstract public static function supports_retrieve(): bool;

    /**
     * Checks if this storage is available for use
     *
     * @return bool True if the storage is available, false otherwise
     */
    abstract public function is_available(): bool;

    /**
     * Returns the amount of free space available in this storage in bytes
     *
     * @return int|null Available space in bytes or null if the storage does not
     * support this operation
     */
    abstract public function get_free_bytes(): ?int;

    /**
     * Transfers the given Moodle file to this storage under the given path
     *
     * ATTENTION: Ownership of the source stored_file stays with the caller. The
     * caller is responsible for cleaning it up once it is not required anymore!
     *
     * @param int $jobid ID of the archive job this file is associated with
     * @param \stored_file $file The Moodle file to be stored
     * @param string $path The path to store the file under
     * @return file_handle Handle of the stored file
     * @throws storage_exception
     */
    abstract public function store(int $jobid, \stored_file $file, string $path): file_handle;

    /**
     * Retrieves the file stored for the given file handle
     *
     * This function retrieves a local copy of the referenced file and stores it
     * inside the Moodle file storage for local access. It MUST the provided
     * file info object when storing the file inside the Moodle file storage.
     *
     * ATTENTION: The caller takes ownership of the file and is responsible for
     * cleaning it up once it is not required anymore!
     *
     * @param file_handle $handle Handle of the file to retrieve
     * @param \stdClass $fileinfo The file info object to use for storing the file
     * @return \stored_file The retrieved file
     * @throws storage_exception
     */
    abstract public function retrieve(file_handle $handle, \stdClass $fileinfo): \stored_file;

    /**
     * Deletes the given file from storage if possible
     *
     * @param file_handle $handle Handle of the file to delete
     * @param bool $strict If true, a storage_exception will be thrown if the
     * file does not exist in the storage destination. If false, missing files
     * will be silently ignored.
     * @throws storage_exception
     */
    abstract public function delete(file_handle $handle, bool $strict = false): void;
}
