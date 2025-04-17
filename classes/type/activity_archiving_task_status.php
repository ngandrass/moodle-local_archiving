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
 * Status values of an activity archiving task
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Status values of an activity archiving task
 */
enum activity_archiving_task_status: int {

    /** @var int The task has not been initialized yet */
    case STATUS_UNINITIALIZED = 0;

    /** @var int The task was created and initialized */
    case STATUS_CREATED = 20;

    /** @var int The task is scheduled for execution but currently pending */
    case STATUS_AWAITING_PROCESSING = 40;

    /** @var int The task is currently being executed */
    case STATUS_RUNNING = 100;

    /** @var int The task is currently being finalized */
    case STATUS_FINALIZING = 200;

    /** @var int The task was finished successfully */
    case STATUS_FINISHED = 220;

    /** @var int The task was gracefully aborted */
    case STATUS_CANCELED = 240;

    /** @var int The task failed before if could be completed */
    case STATUS_FAILED = 250;

    /** @var int The task timed out and therefore could not be completed */
    case STATUS_TIMEOUT = 251;

    /** @var int The task status is unknown due to an internal data error */
    case STATUS_UNKNOWN = 255;

}
