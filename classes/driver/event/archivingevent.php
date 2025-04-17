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
 * Interface definitions for external event connectors
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver\event;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Interface for external event connector (archivingevent) sub-plugins
 */
abstract class archivingevent {

    /**
     * Returns the name of this external event connector
     *
     * @return string Name of the external event connector
     */
    abstract public static function get_name(): string;

    /**
     * Returns the internal identifier for this external event connector. This
     * function should return the last part of the frankenstyle plugin name
     * (e.g., 'cms' for 'archivingevent_cms').
     *
     * @return string Internal identifier of external event connector
     */
    abstract public static function get_plugname(): string;

}
