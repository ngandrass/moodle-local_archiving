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
 * Valid variables for attempt filename patterns
 *
 * @package     archivingmod_quiz
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\type;

use local_archiving\trait\enum_listable;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Valid variables for attempt filename patterns
 */
enum attempt_filename_variable: string {
    use enum_listable;

    /** @var string Course ID */
    case COURSEID = 'courseid';

    /** @var string Full name of the course */
    case COURSENAME = 'coursename';

    /** @var string Short name of the course */
    case COURSESHORTNAME = 'courseshortname';

    /** @var string Course module ID */
    case CMID = 'cmid';

    /** @var string IDs of all groups the attempt user belongs to */
    case GROUPIDS = 'groupids';

    /** @var string External group ID numbers of all groups the attempt user belongs to */
    case GROUPIDNUMBERS = 'groupidnumbers';

    /** @var string Names of all groups the attempt user belongs to */
    case GROUPNAMES = 'groupnames';

    /** @var string Quiz ID */
    case QUIZID = 'quizid';

    /** @var string Quiz name */
    case QUIZNAME = 'quizname';

    /** @var string Attempt ID */
    case ATTEMPTID = 'attemptid';

    /** @var string Username of the attempt user */
    case USERNAME = 'username';

    /** @var string First name of the attempt user */
    case FIRSTNAME = 'firstname';

    /** @var string Last name of the attempt user */
    case LASTNAME = 'lastname';

    /** @var string ID number of the attempt user (NOT userid!) */
    case IDNUMBER = 'idnumber';

    /** @var string Attempt start time */
    case TIMESTART = 'timestart';

    /** @var string Attempt finish time */
    case TIMEFINISH = 'timefinish';

    /** @var string Current date */
    case DATE = 'date';

    /** @var string Current time */
    case TIME = 'time';

    /** @var string Current UNIX timestamp */
    case TIMESTAMP = 'timestamp';
}
