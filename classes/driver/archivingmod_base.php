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
 * Interface definitions for activity archiving drivers
 *
 * @package     local_archiving
 * @category    driver
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver;

use local_archiving\form\job_create_form;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Interface for activity archiving driver (archivingmod) sub-plugins
 */
abstract class archivingmod_base {

    /**
     * Returns the name of this driver
     *
     * @return string Name of the driver
     */
    abstract public static function get_name(): string;

    /**
     * Returns a list of supported Moodle activities by this driver as a list of
     * frankenstyle plugin names (e.g., 'mod_quiz')
     *
     * @return array List of supported activities
     */
    abstract public static function get_supported_activities(): array;

    /**
     * Provides access to the Moodle form that holds all settings for creating a
     * single archiving job. Generic settings are populated by the base class
     * and can be extended as needed.
     *
     * @param string $handler Name of the archivingmod sub-plugin that handles this job
     * @param \cm_info $cminfo Info object for the targeted course module
     * @return job_create_form Form for the task settings
     * @throws \dml_exception
     */
    public function get_job_create_form(string $handler, \cm_info $cminfo): job_create_form {
        return new job_create_form($handler, $cminfo);
    }

}
