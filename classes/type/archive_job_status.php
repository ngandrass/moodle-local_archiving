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
 * Status of an archive job
 *
 * @package     local_archiving
 * @category    type
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Status values an archive job can have
 */
class archive_job_status {

    /** @var int Job is uninitialized */
    public const STATUS_UNINITIALIZED = 0;

    /** @var int Job is initialized and queued for processing */
    public const STATUS_QUEUED = 10;

    /** @var int Job is currently being processed */
    public const STATUS_PROCESSING = 20;

    /** @var int Activity archiving currently takes place */
    public const STATUS_ACTIVITY_ARCHIVING = 30;

    /** @var int Job data is being post-processed */
    public const STATUS_POST_PROCESSING = 40;

    /** @var int Job data is being stored */
    public const STATUS_STORE = 50;

    /** @var int Temporary job data is being cleaned up */
    public const STATUS_CLEANUP = 60;

    /** @var int Job is completed. This state is final. */
    public const STATUS_COMPLETED = 100;

    /** @var int An error occurred that yet needs to be triaged */
    public const STATUS_ERROR = 200;

    /** @var int An error occurred that can be recovered from */
    public const STATUS_RECOVERABLE_ERROR = 210;

    /** @var int Internal error handling / post-processing is running */
    public const STATUS_ERROR_HANDLING = 220;

    /** @var int Job has exceeded its maximum processing time and was aborted. This state is final. */
    public const STATUS_TIMEOUT = 230;

    /** @var int An error occurred that cannot be recovered from. This state is final. */
    public const STATUS_FAILURE = 240;

    /** @var int Job stataus is unknown due to an internal data error */
    public const STATUS_UNKNOWN = 255;

}
