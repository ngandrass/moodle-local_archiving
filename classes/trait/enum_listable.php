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
 * Allows to get all values of a backed enum as an array
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\trait;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Allows to get all values of a backed enum as an array
 */
trait enum_listable {

    /**
     * Returns all backed values of the enum as an array
     *
     * @return array List of all backed values
     */
    public static function values(): array {
        return array_map(
            fn (self $enum) => $enum->value,
            self::cases()
        );
    }

    /**
     * Returns all names of the enum as an array
     *
     * @return array List of all names
     */
    public static function keys(): array {
        return array_map(
            fn (self $enum) => $enum->name,
            self::cases()
        );
    }

}
