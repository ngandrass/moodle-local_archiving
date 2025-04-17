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
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Status values an archive job can have
 */
enum archive_job_status: int {

    /** @var int Job is uninitialized */
    case STATUS_UNINITIALIZED = 0;

    /** @var int Job is initialized and queued for processing */
    case STATUS_QUEUED = 10;

    /** @var int Job is currently being processed */
    case STATUS_PROCESSING = 20;

    /** @var int Activity archiving currently takes place */
    case STATUS_ACTIVITY_ARCHIVING = 30;

    /** @var int Job data is being post-processed */
    case STATUS_POST_PROCESSING = 40;

    /** @var int Job data is being stored */
    case STATUS_STORE = 50;

    /** @var int Temporary job data is being cleaned up */
    case STATUS_CLEANUP = 60;

    /** @var int Job is completed. This state is final until future deletion. */
    case STATUS_COMPLETED = 100;

    /** @var int Job completed in the past and now the archive data is deleted. */
    case STATUS_DELETED = 110;

    /** @var int An error occurred that yet needs to be triaged */
    case STATUS_ERROR = 200;

    /** @var int An error occurred that can be recovered from */
    case STATUS_RECOVERABLE_ERROR = 210;

    /** @var int Internal error handling / post-processing is running */
    case STATUS_ERROR_HANDLING = 220;

    /** @var int Job has exceeded its maximum processing time and was aborted. This state is final. */
    case STATUS_TIMEOUT = 230;

    /** @var int An error occurred that cannot be recovered from. This state is final. */
    case STATUS_FAILURE = 240;

    /** @var int Job status is unknown due to an internal data error */
    case STATUS_UNKNOWN = 255;

    /**
     * Returns the localized string representation of the given archive job
     * status value
     *
     * @return string Localized status name
     * @throws \coding_exception
     */
    public function name(): string {
        return get_string('job_status_'.$this->value, 'local_archiving');
    }

    /**
     * Returns the localized help string for the given status value
     *
     * @return string Localized help string
     * @throws \coding_exception
     */
    public function help(): string {
        return get_string('job_status_'.$this->value.'_help', 'local_archiving');
    }

    /**
     * Returns the color class for the given status value
     *
     * @return string CSS color class name
     */
    public function color(): string {
        return match($this) {
            self::STATUS_PROCESSING,
            self::STATUS_ACTIVITY_ARCHIVING,
            self::STATUS_POST_PROCESSING
                => 'primary',
            self::STATUS_STORE,
            self::STATUS_CLEANUP
                => 'info',
            self::STATUS_COMPLETED
                => 'success',
            self::STATUS_ERROR,
            self::STATUS_RECOVERABLE_ERROR,
            self::STATUS_ERROR_HANDLING,
            self::STATUS_TIMEOUT,
            self::STATUS_FAILURE
                => 'danger',
            default
                => 'secondary',
        };
    }

    /**
     * Returns localized status name and help text as well as the respective
     * CSS color class for the given status value
     *
     * @return object Object with color, text and help properties
     * @throws \coding_exception
     */
    public function status_display_args(): object {
        return (object) [
            'text' => $this->name(),
            'help' => $this->help(),
            'color' => $this->color(),
        ];
    }

}
