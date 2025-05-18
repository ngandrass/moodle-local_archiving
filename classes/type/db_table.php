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
 * Database table name mappings
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Database table name mappings
 */
enum db_table: string {

    /** @var string Name of the job table */
    case JOB = 'local_archiving_job';

    /** @var string Name of the metadata table */
    case METADATA = 'local_archiving_metadata';

    /** @var string Name of the activity tasks table */
    case ACTIVITY_TASK = 'local_archiving_activity_task';

    /** @var string Name of the table associating temporary Moodle files with jobs / tasks */
    case TEMPFILE = 'local_archiving_tempfile';

    /** @var string Name of the table that holds persistent file handles */
    case FILE_HANDLE = 'local_archiving_file';

    /** @var string Name of the table used for job logging */
    case LOG = 'local_archiving_log';

}
