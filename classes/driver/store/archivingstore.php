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

namespace local_archiving\driver\store;

use local_archiving\exception\storage_exception;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Interface for storage driver (archivingstore) sub-plugins
 */
abstract class archivingstore {

    /**
     * Returns the localized name of this driver
     *
     * @return string Localized name of the driver
     */
    abstract public static function get_name(): string;

    /**
     * Returns the internal identifier for this driver. This function should
     * return the last part of the frankenstyle plugin name (e.g., 'localdir'
     * for 'archivingstore_localdir').
     *
     * @return string Internal identifier of this driver
     */
    abstract public static function get_plugname(): string;

    /**
     * Checks if this storage is available for use
     *
     * @return bool True if the storage is available, false otherwise
     */
    abstract public function is_available(): bool;

    /**
     * Returns the amount of free space available in this storage in bytes
     *
     * @return int Available space in bytes
     */
    abstract public function get_free_bytes(): int;

    /**
     * Transfers the given Moodle file to this storage under the given path
     *
     * @param int $jobid ID of the archive job this file is associated with
     * @param \stored_file $file The Moodle file to be stored
     * @param string $path The path to store the file under
     * @return file_handle Handle of the stored file
     * @throws storage_exception
     */
    abstract public function store(int $jobid, \stored_file $file, string $path): file_handle;

    /**
     * Retrieves the file stored under the given path
     *
     * @param file_handle $handle Handle of the file to retrieve
     * @return \stored_file The retrieved file
     * @throws storage_exception
     */
    abstract public function retrieve(file_handle $handle): \stored_file;

    /**
     * Deletes the given file from storage if possible
     *
     * @param file_handle $handle Handle of the file to delete
     * @param bool $strict If true, the file will be deleted even if it is not empty
     * @throws storage_exception
     */
    abstract public function delete(file_handle $handle, bool $strict = false): void;

}
