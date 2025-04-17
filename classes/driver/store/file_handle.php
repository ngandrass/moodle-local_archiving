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
 * @category    driver
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver\store;

use local_archiving\type\db_table;
use local_archiving\util\plugin_util;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Handle for a file stored by storage drivers
 *
 * @property-read string $archivingstore Name of the storage driver that works with this file handle
 * @property-read string $filename Name of the referenced file
 * @property-read string $filepath Path of the referenced file
 * @property-read int $filesize Filesize in bytes
 * @property-read string $filekey Optional unique key for identifying the file
 */
final class file_handle {

    /** @var archivingstore|null Instance of the storage driver that is responsible for this file handle (lazy-loaded) */
    protected ?archivingstore $archivingstore;

    /**
     * Constructor
     *
     * @param int $jobid ID of the archiving job this file is associated with
     * @param string $archivingstorename Name of the storage driver that works with this file handle
     * @param string $filename Name of the referenced file
     * @param string $filepath Path of the referenced file
     * @param int $filesize Filesize in bytes
     * @param string $filekey Optional unique key for identifying the file
     */
    protected function __construct(
        protected readonly int $id,
        protected readonly int $jobid,
        protected readonly string $archivingstorename,
        protected readonly string $filename,
        protected readonly string $filepath,
        protected readonly int $filesize,
        protected readonly string $filekey = ''
    ) {

    }

    /**
     * Creates a new file_handle and persists it inside the database
     *
     * @param int $jobid ID of the archiving job this file is associated with
     * @param string $archivingstorename Name of the storage driver that works with this file handle
     * @param string $filename Name of the referenced file
     * @param string $filepath Path of the referenced file
     * @param int $filesize Filesize in bytes
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

        // Insert into DB.
        $id = $DB->insert_record(db_table::FILE_HANDLE, [
            'jobid' => $jobid,
            'archivingstore' => $archivingstorename,
            'filename' => $filename,
            'filepath' => $filepath,
            'filesize' => $filesize,
            'filekey' => $filekey,
        ]);

        return new self(
            $id,
            $jobid,
            $archivingstorename,
            $filename,
            $filepath,
            $filesize,
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

        $handle = $DB->get_record(db_table::FILE_HANDLE, ['id' => $id], '*', MUST_EXIST);
        return new self(
            $handle->id,
            $handle->jobid,
            $handle->archivingstore,
            $handle->filename,
            $handle->filepath,
            $handle->filesize,
            $handle->filekey
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

        $handles = $DB->get_records(db_table::FILE_HANDLE, ['jobid' => $jobid]);

        return array_map(fn ($handle) => new self(
            $handle->id,
            $handle->jobid,
            $handle->archivingstore,
            $handle->filename,
            $handle->filepath,
            $handle->filesize,
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

        $DB->delete_records(db_table::FILE_HANDLE, ['id' => $this->id]);
        if ($removefile) {
            $this->archivingstore()->delete($this);
        }
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
     * @return mixed Value of the requested property
     * @throws \coding_exception
     */
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new \coding_exception('Invalid property: ' . $name);
    }

}
