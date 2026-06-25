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
 * Driver for storing archive data inside via the Moodle File API
 *
 * @package     archivingstore_moodle
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_moodle;

use local_archiving\archive_job;
use local_archiving\exception\storage_exception;
use local_archiving\file_handle;
use local_archiving\storage;
use local_archiving\type\storage_tier;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Driver for storing archive data inside via the Moodle File API
 */
class archivingstore extends \local_archiving\driver\archivingstore {
    /** @var string Name of the component passed to the Moodle file API */
    public const FS_COMPONENT = 'archivingstore_moodle';

    /** @var string Name of the filearea to store artifacts in */
    public const FS_FILEAREA_ARTIFACTS = 'artifacts';

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
        global $CFG;

        return disk_free_space($CFG->dataroot) ?: null;
    }

    #[\Override]
    public function store(int $jobid, \stored_file $file, string $path): file_handle {
        // Get job.
        $job = archive_job::get_by_id($jobid);

        // Store the file inside the permanent Moodle file storage.
        try {
            $fs = get_file_storage();
            $moodlestorefile = $fs->create_file_from_storedfile([
                'contextid' => $job->get_context()->id,
                'component' => self::FS_COMPONENT,
                'filearea' => self::FS_FILEAREA_ARTIFACTS,
                'itemid' => $jobid,
                'filepath' => "/{$job->get_context()->id}/{$jobid}/",
                'filename' => $file->get_filename(),
                'timecreated' => $file->get_timecreated(),
                'timemodified' => time(),
            ], $file);
        } catch (\Exception) {
            throw new storage_exception('filestorefailed', 'archivingstore_moodle');
        }

        // Create file handle for the freshly stored file.
        return file_handle::create(
            jobid: $jobid,
            archivingstorename: 'moodle',
            filename: $file->get_filename(),
            filepath: trim($path, '/'),
            filesize: $file->get_filesize(),
            sha256sum: storage::hash_file($file),
            mimetype: $file->get_mimetype(),
            filekey: $moodlestorefile->get_id()
        );
    }

    #[\Override]
    public function retrieve(file_handle $handle, \stdClass $fileinfo): \stored_file {
        // Retrieve the file from Moodle file storage.
        $fs = get_file_storage();
        $moodlestorefile = $fs->get_file_by_id($handle->filekey);

        if (!$moodlestorefile) {
            throw new storage_exception('filenotfound', 'error');
        }

        // Copy the file to the desired location.
        try {
            $retrievedfile = $fs->create_file_from_storedfile($fileinfo, $moodlestorefile);
        } catch (\Exception) {
            throw new storage_exception('filestorefailed', 'archivingstore_moodle');
        }

        if (!$retrievedfile) {
            throw new storage_exception('filestorefailed', 'archivingstore_moodle');
        }

        return $retrievedfile;
    }

    #[\Override]
    public function delete(file_handle $handle, bool $strict = false): void {
        // Try to retrieve file for file_handle.
        $fs = get_file_storage();
        $moodlestorefile = $fs->get_file_by_id($handle->filekey);

        // Handle missing files.
        if (!$moodlestorefile) {
            if ($strict) {
                throw new storage_exception('filenotfound', 'error');
            } else {
                return;
            }
        }

        // Delete file.
        $moodlestorefile->delete();
    }
}
