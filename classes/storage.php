<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file defines the storage class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use local_archiving\type\db_table;
use stored_file;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Interacts with archive storage drivers and handles file tasks that are not
 * covered by storage drivers.
 */
class storage {

    /** @var int Number of characters a string variable will be cut off after expansion */
    public const FILENAME_VARIABLE_MAX_LENGTH = 128;

    /** @var int Number of characters after a single filename is trimmed */
    public const FILENAME_MAX_LENGTH = 240;

    /** @var string[] Characters that are forbidden in a folder name pattern */
    public const FOLDERNAME_FORBIDDEN_CHARACTERS = ["\\", ".", ":", ";", "*", "?", "!", "\"", "<", ">", "|", "\0"];

    /** @var string[] Characters that are forbidden in a filename pattern */
    public const FILENAME_FORBIDDEN_CHARACTERS = ["\\", ".", ":", ";", "*", "?", "!", "\"", "<", ">", "|", "\0", "/"];

    /**
     * Determines if the given filename pattern contains only allowed variables
     * and no orphaned dollar signs
     *
     * @param string $pattern Filename pattern to test
     * @param string[] $allowedvariables List of allowed variables
     * @param string[] $forbiddenchars List of forbidden characters
     * @return bool True if the pattern is valid
     */
    public static function is_valid_filename_pattern(
        string $pattern,
        array $allowedvariables,
        array $forbiddenchars
    ): bool {
        // Check for minimal length.
        if (strlen($pattern) < 1) {
            return false;
        }

        // Check for variables.
        $residue = preg_replace('/\$\{\s*('.implode('|', $allowedvariables).')\s*\}/m', '', $pattern);
        if (str_contains($residue, '$')) {
            return false;
        }

        // Check for forbidden characters.
        foreach ($forbiddenchars as $char) {
            if (str_contains($pattern, $char)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitizes the given filename by removing all forbidden characters
     *
     * @param string $filename Filename to sanitize
     * @return string Sanitized filename
     */
    public static function sanitize_filename(string $filename): string {
        $res = $filename;
        foreach (self::FILENAME_FORBIDDEN_CHARACTERS as $char) {
            $res = str_replace($char, '', $res);
        }

        return substr(trim($res), 0, self::FILENAME_MAX_LENGTH);
    }

    /**
     * Sanitizes the given foldername by removing all forbidden characters as
     * well as leading- and trailing slashes
     *
     * @param string $foldername Foldername to sanitize
     * @return string Sanitized foldername
     */
    public static function sanitize_foldername(string $foldername): string {
        $res = $foldername;
        foreach (self::FOLDERNAME_FORBIDDEN_CHARACTERS as $char) {
            $res = str_replace($char, '', $res);
        }

        // Trim whole path and each segment / "file"name.
        $res = trim($res, " \n\r\t\v\x00/\\");
        $parts = explode('/', $res);
        $trimmedparts = array_map(fn($part) => substr($part, 0, self::FILENAME_MAX_LENGTH), $parts);

        return join('/', $trimmedparts);
    }

    /**
     * Calculates the contenthash of a large file chunk-wise.
     *
     * @param stored_file $file File which contents should be hashed
     * @param string $algo Hashing algorithm. Must be one of hash_algos()
     * @return string|null Hexadecimal hash
     */
    public static function hash_file(stored_file $file, string $algo = 'sha256'): ?string {
        // Validate requested hash algorithm.
        if (!array_search($algo, hash_algos())) {
            return null;
        }

        // Calculate file hash chunk-wise.
        $fh = $file->get_content_file_handle(stored_file::FILE_HANDLE_FOPEN);
        $hashctx = hash_init($algo);
        while (!feof($fh)) {
            hash_update($hashctx, fgets($fh, 4096));
        }

        return hash_final($hashctx);
    }

    /**
     * Determines if the given string is a valid SHA256 checksum
     *
     * @param string $sha256sum SHA256 checksum to test
     * @return bool True if the checksum is valid
     */
    public static function is_valid_sha256sum(string $sha256sum): bool {
        return preg_match('/^[a-f0-9]{64}$/', $sha256sum) === 1;
    }

    /**
     * Calculates usage statistics for the given archiving store based on existing file handles.
     *
     * @param string $archivingstorename Name of the archiving store to calculate stats for
     * @return \stdClass Object containing usage bytes, file count, and job count
     * @throws \dml_exception
     */
    public static function calculate_archivingstore_stats(string $archivingstorename): \stdClass {
        global $DB;

        // Calculate stats in the database.
        $res = $DB->get_record_sql('
                SELECT SUM(filesize) AS usagebytes, COUNT(*) AS filecount, COUNT(DISTINCT jobid) AS jobcount
                FROM {' . db_table::FILE_HANDLE->value . '}
                WHERE archivingstore = :archivingstore;
            ',
            ['archivingstore' => $archivingstorename]
        );

        // Build response.
        return (object) [
            'usagebytes' => $res->usagebytes ?? 0,
            'filecount' => $res->filecount ?? 0,
            'jobcount' => $res->jobcount ?? 0,
        ];
    }

    /**
     * Checks if the given directory is empty.
     *
     * @param string $dir Directory path to check
     * @return bool True if the directory exists and is empty, false otherwise
     */
    public static function is_dir_empty(string $dir): bool {
        // Check if the directory exists.
        if (!is_dir($dir)) {
            return false;
        }

        // Scan the directory for files and folders.
        $files = scandir($dir);
        if ($files === false) {
            return false;
        }

        // Rule out the obvious case of a non empty directory.
        if (count($files) > 2) {
            return false;
        }

        // Filter out '.' and '..' entries.
        $files = array_diff($files, ['.', '..']);

        // If there are no files or folders left, the directory is empty.
        return empty($files);
    }

}
