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
 * Handle for files stored by storage drivers
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use local_archiving\driver\archivingstore;
use local_archiving\exception\storage_exception;
use local_archiving\type\db_table;
use local_archiving\type\filearea;
use local_archiving\util\plugin_util;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Handle for a file stored by storage drivers
 *
 * @property-read int $id ID of the file handle
 * @property-read int $jobid ID of the archiving job this file is associated with
 * @property-read string $archivingstorename Name of the storage driver that works with this file handle
 * @property-read bool $deleted True, if the file was previously deleted from the storage (only metadata remains)
 * @property-read string $filename Name of the referenced file
 * @property-read string $filepath Path of the referenced file
 * @property-read int $filesize Filesize in bytes
 * @property-read string $sha256sum SHA256 checksum of the file
 * @property-read string $mimetype MIME type of the file
 * @property-read int $timecreated Timestamp when the file handle was created
 * @property-read int $timemodified Timestamp when the file handle was last modified
 * @property-read string $filekey Optional unique key for identifying the file
 */
final class file_handle {

    /** @var archivingstore|null Instance of the storage driver that is responsible for this file handle (lazy-loaded) */
    protected ?archivingstore $archivingstore;

    /**
     * Constructor
     *
     * @param int $id ID of this file handle
     * @param int $jobid ID of the archiving job this file is associated with
     * @param string $archivingstorename Name of the storage driver that works with this file handle
     * @param bool $deleted True, if the file was previously deleted from the storage (only metadata remains)
     * @param string $filename Name of the referenced file
     * @param string $filepath Path of the referenced file
     * @param int $filesize Filesize in bytes
     * @param string $sha256sum SHA256 checksum of the file
     * @param string $mimetype MIME type of the file
     * @param int $timecreated Timestamp when the file handle was created
     * @param int $timemodified Timestamp when the file handle was last modified
     * @param string $filekey Optional unique key for identifying the file
     */
    protected function __construct(
        /** @var int $id ID of this file handle */
        protected readonly int $id,
        /** @var int $jobid ID of the archiving job this file is associated with */
        protected readonly int $jobid,
        /** @var string $archivingstorename Name of the storage driver that works with this file handle */
        protected readonly string $archivingstorename,
        /** @var bool $deleted True, if the file was previously deleted from the storage (only metadata remains) */
        protected bool $deleted,
        /** @var string $filename Name of the referenced file */
        protected readonly string $filename,
        /** @var string $filepath Path of the referenced file */
        protected readonly string $filepath,
        /** @var int $filesize Filesize in bytes */
        protected readonly int $filesize,
        /** @var string $sha256sum SHA256 checksum of the file */
        protected readonly string $sha256sum,
        /** @var string $mimetype MIME type of the file */
        protected readonly string $mimetype,
        /** @var int $timecreated Timestamp when the file handle was created */
        protected readonly int $timecreated,
        /** @var int $timemodified Timestamp when the file handle was last modified */
        protected readonly int $timemodified,
        /** @var string $filekey Optional unique key for identifying the file */
        protected readonly string $filekey = ''
    ) {
        $this->archivingstore = null;
    }

    /**
     * Creates a new file_handle and persists it inside the database
     *
     * @param int $jobid ID of the archiving job this file is associated with
     * @param string $archivingstorename Name of the storage driver that works with this file handle
     * @param string $filename Name of the referenced file
     * @param string $filepath Path of the referenced file
     * @param int $filesize Filesize in bytes
     * @param string $sha256sum SHA256 checksum of the file
     * @param string $mimetype MIME type of the file
     * @param string $filekey Optional unique key for identifying the file
     * @return file_handle The created file handle
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create(
        int $jobid,
        string $archivingstorename,
        string $filename,
        string $filepath,
        int $filesize,
        string $sha256sum,
        string $mimetype,
        string $filekey = ''
    ): file_handle {
        global $DB;

        // Validate arguments.
        if ($jobid < 1) {
            throw new \coding_exception(get_string('invalid_jobid', 'local_archiving'));
        }

        if (empty($archivingstorename) || !plugin_util::get_subplugin_by_name('archivingstore', $archivingstorename)) {
            throw new \coding_exception(get_string('invalid_archivingstore', 'local_archiving'));
        }

        if (
            empty($filename) ||                   // Empty filenames are not allowed.
            basename($filename) !== $filename ||  // Filenames must not contain path separators.
            trim($filename, '/') !== $filename    // Filenames must not start or end with path separators.
        ) {
            throw new \coding_exception(get_string('invalid_filename', 'local_archiving'));
        }

        if ($filesize < 0) {
            throw new \coding_exception(get_string('invalid_filesize', 'local_archiving'));
        }

        if (!storage::is_valid_sha256sum($sha256sum)) {
            throw new \coding_exception(get_string('invalid_sha256sum', 'local_archiving'));
        }

        // Insert into DB.
        $now = time();
        $id = $DB->insert_record(db_table::FILE_HANDLE->value, [
            'jobid' => $jobid,
            'archivingstore' => $archivingstorename,
            'deleted' => false,
            'filename' => $filename,
            'filepath' => $filepath,
            'filesize' => $filesize,
            'sha256sum' => $sha256sum,
            'mimetype' => $mimetype,
            'filekey' => $filekey,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return new self(
            $id,
            $jobid,
            $archivingstorename,
            false,
            $filename,
            $filepath,
            $filesize,
            $sha256sum,
            $mimetype,
            $now,
            $now,
            $filekey
        );
    }

    /**
     * Loads an existing file handle from the database
     *
     * @param int $id ID of the file handle to load
     * @return file_handle The loaded file handle
     * @throws \dml_exception
     */
    public static function get_by_id(int $id): file_handle {
        global $DB;

        $handle = $DB->get_record(db_table::FILE_HANDLE->value, ['id' => $id], '*', MUST_EXIST);
        return new self(
            $handle->id,
            $handle->jobid,
            $handle->archivingstore,
            $handle->deleted,
            $handle->filename,
            $handle->filepath,
            $handle->filesize,
            $handle->sha256sum,
            $handle->mimetype,
            $handle->timecreated,
            $handle->timemodified,
            $handle->filekey,
        );
    }

    /**
     * Retrieves all file handles associated with the given jobid
     *
     * @param int $jobid ID of the job to retrieve file handles for
     * @return file_handle[] List of file handles associated with the job
     * @throws \dml_exception
     */
    public static function get_by_jobid(int $jobid): array {
        global $DB;

        $handles = $DB->get_records(db_table::FILE_HANDLE->value, ['jobid' => $jobid]);

        return array_map(fn ($handle) => new self(
            $handle->id,
            $handle->jobid,
            $handle->archivingstore,
            $handle->deleted,
            $handle->filename,
            $handle->filepath,
            $handle->filesize,
            $handle->sha256sum,
            $handle->mimetype,
            $handle->timecreated,
            $handle->timemodified,
            $handle->filekey
        ), $handles);
    }

    /**
     * Removes this file handle from the database and deletes the associated
     * file, if desired
     *
     * @param bool $removefile If true, the referenced file will be deleted as well
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function destroy(bool $removefile = false): void {
        global $DB;

        // Try to remove the file first because if this fails we do not loose the file handle metadata.
        if ($removefile) {
            if (!$this->deleted) {
                $this->archivingstore()->delete($this);
            }
        }

        // Remove the file handle from the database.
        $DB->delete_records(db_table::FILE_HANDLE->value, ['id' => $this->id]);
    }

    /**
     * Generates a complete fileinfo record that _MUST_ be used as a target when
     * retrieving the file for this file_handle.
     *
     * Files are stored temporarily inside the filestore cache file area. As the
     * ids of file handles are unique, those are used as itemid to easily
     * identify cached versions of the file.
     *
     * @return \stdClass Fileinfo record for the file to be retrieved
     * @throws \dml_exception
     */
    public function generate_retrieval_fileinfo_record(): \stdClass {
        $job = archive_job::get_by_id($this->jobid);

        return (object) [
            'contextid' => $job->get_context()->id,
            'component' => filearea::FILESTORE_CACHE->get_component(),
            'filearea' => filearea::FILESTORE_CACHE->value,
            'itemid' => $this->id,
            'filepath' => '/',
            'filename' => $this->filename,
        ];
    }

    /**
     * Retrieves the local stored_file for this file handle if currently present
     * in the filestore cache
     *
     * @return \stored_file|null The stored_file object or null if not found
     * @throws \dml_exception
     */
    public function get_local_file(): ?\stored_file {
        global $DB;

        $file = $DB->get_record_sql("
                SELECT id
                FROM {files}
                WHERE
                    filename != '.' AND
                    component = :component AND
                    filearea = :filearea AND
                    itemid = :itemid
            ",
            [
                'component' => filearea::FILESTORE_CACHE->get_component(),
                'filearea' => filearea::FILESTORE_CACHE->value,
                'itemid' => $this->id,
            ],
            IGNORE_MISSING
        );
        if (!$file) {
            return null;
        }

        return get_file_storage()->get_file_by_id($file->id);
    }

    /**
     * Retrieves the file associated with this file handle. If the file is not
     * already present in the filestore cache, it will be retrieved from the
     * storage driver and stored in the local filestore cache.
     *
     * Note: The retrieval process currently is a synchronous operation and
     * may take some time, depending on the size of the file and the storage
     * tier.
     *
     * @return \stored_file The stored_file object
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws storage_exception
     */
    public function retrieve_file(): \stored_file {
        // Do not retrieve deleted if deleted previously.
        if ($this->deleted) {
            throw new storage_exception('deleted_file_can_not_be_retrieved.', 'local_archiving');
        }

        // Check if the file is already present in the local filestore cache.
        $localfile = $this->get_local_file();
        if ($localfile) {
            // Touch file to extend its cache lifetime.
            $localfile->set_timemodified(time());

            return $localfile;
        }

        // File not found in the local filestore cache, retrieve it from the storage driver.
        // This is handled fully synchronously right now. When having storage
        // drivers that write to external storage this will most likely need to
        // be handled asynchronously. But lets focus on the more important parts
        // first and do not drown into premature optimizations..
        return $this->archivingstore()->retrieve(
            $this,
            $this->generate_retrieval_fileinfo_record()
        );
    }

    /**
     * Returns an instance of the storage driver that is responsible for this
     * file handle
     *
     * @return archivingstore Instance of the storage driver
     * @throws \moodle_exception
     */
    public function archivingstore(): archivingstore {
        if ($this->archivingstore instanceof archivingstore) {
            return $this->archivingstore;
        }

        $driverclass = plugin_util::get_subplugin_by_name('archivingstore', $this->archivingstorename);
        if (!$driverclass) {
            throw new \moodle_exception('invalid_archivingstore', 'local_archiving');
        }
        $this->archivingstore = new $driverclass();

        return $this->archivingstore;
    }

    /**
     * Allows read-only access to object properties
     *
     * @param string $name Name of the property to access
     * @return mixed Value of the requested property
     * @throws \coding_exception
     */
    public function __get(string $name): mixed {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new \coding_exception('Invalid property: ' . $name);
    }

    /**
     * Marks the file referenced by this file handle as deleted.
     *
     * Once a referenced file is marked as deleted, it will not be accessible
     * anymore via the retrieve_file() method. However, the metadata stored in
     * the file handle will still be available until the file handle is removed
     * via destroy().
     *
     * @return void
     * @throws \dml_exception
     */
    public function mark_as_deleted(): void {
        global $DB;

        $DB->update_record(db_table::FILE_HANDLE->value, [
            'id' => $this->id,
            'deleted' => true,
            'timemodified' => time(),
        ]);

        $this->deleted = true;
    }

}
