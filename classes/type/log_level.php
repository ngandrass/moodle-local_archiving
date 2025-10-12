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
 * Supported log levels
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use local_archiving\trait\enum_listable;


/**
 * Supported log levels
 *
 * Backed int values are written to the database for storage. Supported range is
 * 0 to 99.
 */
enum log_level: int {
    use enum_listable;

    /** @var self Information for tracing code */
    case TRACE = 0;

    /** @var self Information for debuggin */
    case DEBUG = 10;

    /** @var self General information */
    case INFO = 20;

    /** @var self Non-critical warning */
    case WARN = 30;

    /** @var self Error occured */
    case ERROR = 40;

    /** @var self Fatal unrecoverable case */
    case FATAL = 50;
}
