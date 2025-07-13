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
 * Valid variables for archive filename patterns
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

use local_archiving\trait\enum_listable;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Valid variables for archive filename patterns
 */
enum archive_filename_variable : string {
    use enum_listable;

    /** @var string Course ID */
    case COURSEID = 'courseid';

    /** @var string Full name of the course */
    case COURSENAME = 'coursename';

    /** @var string Short name of the course */
    case COURSESHORTNAME = 'courseshortname';

    /** @var string Course module ID */
    case CMID = 'cmid';

    /** @var string Course module type (e.g. 'quiz') */
    case CMTYPE = 'cmtype';

    /** @var string Course module name */
    case CMNAME = 'cmname';

    /** @var string Current date */
    case DATE = 'date';

    /** @var string Current time */
    case TIME = 'time';

    /** @var string Current UNIX timestamp */
    case TIMESTAMP = 'timestamp';

}
