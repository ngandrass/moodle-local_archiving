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
 * Driver for storing archive data on S3-compatible object storage
 *
 * @package     archivingstore_s3
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_s3;

use archivingstore_s3\local\s3_client;
use local_archiving\file_handle;
use local_archiving\local\exception\storage_exception;
use local_archiving\local\type\storage_tier;
use local_archiving\storage;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Driver for storing archive data on S3-compatible object storage
 */
class archivingstore extends \local_archiving\local\driver\archivingstore {
    /**
     * Derives the S3 object key for a stored file from its file handle's path and filename
     *
     * @param string $filepath Path the file was stored under, as recorded in the file handle
     * @param string $filename Name of the file, as recorded in the file handle
     * @return string Object key, relative to the configured bucket key prefix
     */
    private function s3_object_key(string $filepath, string $filename): string {
        return trim(trim($filepath, '/') . '/' . $filename, '/');
    }

    /**
     * Determines if all settings required to operate this storage driver are present
     *
     * This is a cheap, local-only check (no network access) used to decide
     * whether this driver is ready to be used at all. It does not verify that
     * the configured endpoint is actually reachable or that the credentials
     * are valid - use is_available() for that.
     *
     * @return bool True if all required settings are configured
     * @throws \dml_exception
     */
    public static function is_configured(): bool {
        $config = get_config('archivingstore_s3');

        return !empty($config->endpoint)
            && !empty($config->region)
            && !empty($config->bucket_path)
            && !empty($config->access_key)
            && !empty($config->secret_key);
    }

    #[\Override]
    public static function get_storage_tier(): storage_tier {
        return storage_tier::REMOTE_FAST;
    }

    #[\Override]
    public static function supports_retrieve(): bool {
        return true;
    }

    #[\Override]
    public static function is_ready(): bool {
        return self::is_configured();
    }

    #[\Override]
    public function is_available(): bool {
        // Require configuration data.
        if (!self::is_ready()) {
            return false;
        }

        // Check if the s3 target is reachable.
        try {
            return s3_client::instance()->check_connection()->is_ok();
        } catch (\Exception) {
            // Just a safeguard that should never trip ...
            return false; // @codeCoverageIgnore
        }
    }

    #[\Override]
    public function get_free_bytes(): ?int {
        // S3 has no meaningful "free space" concept. Maybe explore quota exposure in the future ...
        return null;
    }

    #[\Override]
    public function store(int $jobid, \stored_file $file, string $path, ?callable $progresscallback = null): file_handle {
        // Prepare file metadata.
        $sha256 = storage::hash_file($file);
        $filepath = trim($path, '/');
        $filename = $file->get_filename();
        $localpath = get_file_storage()->get_file_system()->get_local_path_from_storedfile($file, true);

        // Perform the upload.
        s3_client::instance()->put_object(
            $this->s3_object_key($filepath, $filename),
            $localpath,
            $sha256,
            $progresscallback
        );

        // @codeCoverageIgnoreStart
        // Create file handle for stored file.
        return file_handle::create(
            jobid: $jobid,
            archivingstorename: 's3',
            filename: $filename,
            filepath: $filepath,
            filesize: $file->get_filesize(),
            sha256sum: $sha256,
            mimetype: $file->get_mimetype()
        );
        // @codeCoverageIgnoreEnd
    }

    #[\Override]
    public function retrieve(file_handle $handle, \stdClass $fileinfo, ?callable $progresscallback = null): \stored_file {
        // Create temporary request dir for retrieval.
        $tmppath = tempnam(make_request_directory(), 'archivingmod_s3_get');

        try {
            // Fetch desired file from object store into temporary file.
            s3_client::instance()->get_object(
                $this->s3_object_key($handle->filepath, $handle->filename),
                $tmppath,
                $progresscallback
            );

            // @codeCoverageIgnoreStart
            // Move downloaded file into local Moodle file storage.
            try {
                $storedfile = get_file_storage()->create_file_from_pathname($fileinfo, $tmppath);
            } catch (\file_exception $e) {
                throw new storage_exception('filestorefailed', 'local_archiving');
            }
            if (!$storedfile) {
                throw new storage_exception('filestorefailed', 'local_archiving');
            }

            return $storedfile;
            // @codeCoverageIgnoreEnd
        } finally {
            @unlink($tmppath);
        }
    }

    #[\Override]
    public function delete(file_handle $handle, bool $strict = false): void {
        $key = $this->s3_object_key($handle->filepath, $handle->filename);
        $client = s3_client::instance();

        // If strict deletion is requested we need to check if the file exists (s3 deletion is idempotent).
        if ($strict && !$client->object_exists($key)) {
            throw new storage_exception('filenotfound', 'error');
        }

        $client->delete_object($key);
    }
}
