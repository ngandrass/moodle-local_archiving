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

// phpcs:disable moodle.Commenting.InlineComment.DocBlock

/**
 * Valid variables for submission filename patterns
 *
 * @package     archivingmod_assign
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\local\type;

use local_archiving\local\trait\enum_listable;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Valid variables for submission filename patterns
 */
enum submission_filename_variable: string {
    use enum_listable;

    /** @var string Course ID */
    case COURSEID = 'courseid';

    /** @var string Full name of the course */
    case COURSENAME = 'coursename';

    /** @var string Short name of the course */
    case COURSESHORTNAME = 'courseshortname';

    /** @var string Course module ID */
    case CMID = 'cmid';

    /** @var string IDs of all groups the submission user belongs to */
    case GROUPIDS = 'groupids';

    /** @var string External group ID numbers of all groups the submission user belongs to */
    case GROUPIDNUMBERS = 'groupidnumbers';

    /** @var string Names of all groups the submission user belongs to */
    case GROUPNAMES = 'groupnames';

    /** @var string Assignment ID */
    case ASSIGNMENTID = 'assignmentid';

    /** @var string Assignment title */
    case ASSIGNMENTNAME = 'assignmenttitle';

    /** @var string Submission ID */
    case SUBMISSIONID = 'submissionid';

    /** @var string Number of the submission attempt */
    case ATTEMPTNUMBER = 'attemptnumber';

    /** @var string Username of the submission user */
    case USERNAME = 'username';

    /** @var string First name of the submission user */
    case FIRSTNAME = 'firstname';

    /** @var string Last name of the submission user */
    case LASTNAME = 'lastname';

    /** @var string ID number of the submission user (NOT userid!) */
    case IDNUMBER = 'idnumber';

    /** @var string Submission start time */
    case TIMESTART = 'timestart';

    /** @var string Submission creation time */
    case TIMECREATED = 'timecreated';

    /** @var string Submission last modification time */
    case TIMEMODIFIED = 'timemodified';

    /** @var string Current date */
    case DATE = 'date';

    /** @var string Current time */
    case TIME = 'time';

    /** @var string Current UNIX timestamp */
    case TIMESTAMP = 'timestamp';
}
