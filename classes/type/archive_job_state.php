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
 * State of an archive job
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
 * States an archive job can be in
 */
class archive_job_state {

    /** @var int Job is uninitialized  */
    public const STATE_UNINITIALIZED = 0;

    /** @var int Job is initialized and queued for processing */
    public const STATE_QUEUED = 10;

    /** @var int Job is currently being processed */
    public const STATE_PROCESSING = 20;

    /** @var int Activity archiving currently takes place */
    public const STATE_ACTIVITY_ARCHIVING = 30;

    /** @var int Job data is being post-processed */
    public const STATE_POST_PROCESSING = 40;

    /** @var int Job data is being stored */
    public const STATE_STORE = 50;

    /** @var int Temporary job data is being cleaned up */
    public const STATE_CLEANUP = 60;

    /** @var int Job is completed. This state is final. */
    public const STATE_COMPLETED = 70;

    /** @var int An error occurred that yet needs to be triaged */
    public const STATE_ERROR = 200;

    /** @var int An error occurred that can be recovered from */
    public const STATE_RECOVERABLE_ERROR = 210;

    /** @var int An error occurred that cannot be recovered from. This state is final. */
    public const STATE_FAILURE = 220;

    /** @var int Internal error handling / post-processing is running */
    public const STATE_ERROR_HANDLING = 230;

}
