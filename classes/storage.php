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

// @codingStandardsIgnoreLine
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
    public const FILENAME_FORBIDDEN_CHARACTERS = self::FOLDERNAME_FORBIDDEN_CHARACTERS + ["/"];

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

        return trim($res);
    }

    /**
     * Sanitizes the given foldername by removing all forbidden characters as
     * well as leading- and trailing slashes
     *
     * @param string $foldername Foldername to sanitize
     * @return string Sanitized foldername
     */
    protected static function sanitize_foldername(string $foldername): string {
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

}
