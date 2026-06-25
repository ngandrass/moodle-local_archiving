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
 * Driver for storing archive data inside a directory on the local filesystem
 *
 * @package     archivingstore_localdir
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_localdir;

use local_archiving\exception\storage_exception;
use local_archiving\file_handle;
use local_archiving\storage;
use local_archiving\type\storage_tier;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Driver for storing archive data inside a directory on the local filesystem
 */
class archivingstore extends \local_archiving\driver\archivingstore {
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
        return ($this->get_free_bytes() ?? 0) > 1024 * 1024 * 1024; // At least 1 GB free space required.
    }

    #[\Override]
    public function get_free_bytes(): ?int {
        try {
            return disk_free_space($this->get_storage_path()) ?: null;
        } catch (storage_exception $e) {
            return null; // If the storage path is not configured or does not exist, return null (unknown).
        }
    }

    #[\Override]
    public function store(int $jobid, \stored_file $file, string $path): file_handle {
        // Prepare file handle.
        $handle = file_handle::create(
            jobid: $jobid,
            archivingstorename: 'localdir',
            filename: $file->get_filename(),
            filepath: trim($path, '/'),
            filesize: $file->get_filesize(),
            sha256sum: storage::hash_file($file),
            mimetype: $file->get_mimetype()
        );

        // Create target storage path and write file to it.
        $abstargetpath = $this->get_storage_path() . '/' . $handle->filepath;
        if (!is_dir($abstargetpath)) {
            if (!mkdir($abstargetpath, 0777, true)) {
                $handle->destroy();
                throw new storage_exception('filestorefailed', 'local_archiving');
            }
        }
        if (!$file->copy_content_to($abstargetpath . '/' . $file->get_filename())) {
            $handle->destroy();
            throw new storage_exception('filestorefailed', 'local_archiving');
        }

        return $handle;
    }

    #[\Override]
    public function retrieve(file_handle $handle, \stdClass $fileinfo): \stored_file {
        // Find locally stored file.
        $absfilepath = $this->get_storage_path() . '/' . trim($handle->filepath, '/') . '/' . $handle->filename;
        if (!file_exists($absfilepath)) {
            throw new storage_exception('filenotfound', 'error');
        }

        // Transfer file to Moodle file storage.
        $fs = get_file_storage();
        $storedfile = $fs->create_file_from_pathname($fileinfo, $absfilepath);

        if (!$storedfile) {
            throw new storage_exception('filestorefailed', 'local_archiving');
        }

        return $storedfile;
    }

    #[\Override]
    public function delete(file_handle $handle, bool $strict = false): void {
        // Locate target file in local storage.
        $storagepath = $this->get_storage_path();
        $filefullpath = $storagepath . '/' . $handle->filepath . '/' . $handle->filename;
        if (!file_exists($filefullpath)) {
            if ($strict) {
                throw new storage_exception('filenotfound', 'error');
            } else {
                return;
            }
        }

        // Delete the file.
        if (!unlink($filefullpath)) {
            throw new storage_exception('filedeletefailed', 'local_archiving');
        }

        // Remove parent directories if these are empty now.
        $curdir = dirname($filefullpath);
        while (str_contains($curdir, $storagepath) && $curdir != $storagepath) {
            // Make sure the current directory is empty.
            if (!storage::is_dir_empty($curdir)) {
                break;
            }

            // Do not proceede if rmdir fails.
            if (!rmdir($curdir)) {
                break;
            }

            // Advance to the next parent dir.
            $curdir = dirname($curdir);
        }
    }

    /**
     * Returns the absolute base path for local file storage
     *
     * @return string Absolute path to the local storage root directory
     * @throws \dml_exception
     * @throws storage_exception
     */
    protected function get_storage_path(): string {
        $storagepath = get_config('archivingstore_localdir', 'storage_path');

        // Validate storage path.
        if (!$storagepath) {
            throw new storage_exception('storagepathnotconfigured', 'archivingstore_localdir');
        }

        if (!is_dir($storagepath)) {
            throw new storage_exception('storagepathdoesnotexist', 'archivingstore_localdir');
        }

        return $storagepath;
    }
}
