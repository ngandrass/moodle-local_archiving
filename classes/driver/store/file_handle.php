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
class file_handle {

    /** @var string Name of the storage driver that works with this file handle */
    protected string $archivingstore;

    /** @var string Name of the referenced file */
    protected string $filename;

    /** @var string Path of the referenced file */
    protected string $filepath;

    /** @var int Filesize in bytes */
    protected int $filesize;

    /** @var string Optional unique key for identifying the file */
    protected string $filekey;

    /**
     * Constructor
     *
     * @param string $archivingstore Name of the storage driver that works with this file handle
     * @param string $filename Name of the referenced file
     * @param string $filepath Path of the referenced file
     * @param int $filesize Filesize in bytes
     * @param string $filekey Optional unique key for identifying the file
     * @throws \coding_exception If given arguments are invalid
     */
    public function __construct(
        string $archivingstore,
        string $filename,
        string $filepath,
        int $filesize,
        string $filekey = ''
    ) {
        // Validate arguments.
        if (empty($archivingstore)) {
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

        // Store arguments.
        $this->archivingstore = $archivingstore;
        $this->filename = $filename;
        $this->filepath = $filepath;
        $this->filesize = $filesize;
        $this->filekey = $filekey;
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
