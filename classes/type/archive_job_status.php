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
    case UNINITIALIZED = 0;

    /** @var int Job is initialized and queued for processing */
    case QUEUED = 10;

    /** @var int Job is currently being processed */
    case PROCESSING = 20;

    /** @var int Activity archiving currently takes place */
    case ACTIVITY_ARCHIVING = 30;

    /** @var int Waiting for pending Moodle backups */
    case BACKUP_COLLECTION = 40;

    /** @var int Job data is being post-processed */
    case POST_PROCESSING = 50;

    /** @var int Job data is being stored */
    case STORE = 60;

    /** @var int Job artifacts are being signed */
    case SIGN = 70;

    /** @var int Temporary job data is being cleaned up */
    case CLEANUP = 90;

    /** @var int Job is completed. This state is final until future deletion. */
    case COMPLETED = 100;

    /** @var int Job completed in the past and now the archive data is deleted. */
    case DELETED = 110;

    /** @var int An error occurred that yet needs to be triaged */
    case ERROR = 200;

    /** @var int An error occurred that can be recovered from */
    case RECOVERABLE_ERROR = 210;

    /** @var int Internal error handling / post-processing is running */
    case ERROR_HANDLING = 220;

    /** @var int Job has exceeded its maximum processing time and was aborted. This state is final. */
    case TIMEOUT = 230;

    /** @var int An error occurred that cannot be recovered from. This state is final. */
    case FAILURE = 240;

    /** @var int Job status is unknown due to an internal data error */
    case UNKNOWN = 255;

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
            self::PROCESSING,
            self::ACTIVITY_ARCHIVING,
            self::BACKUP_COLLECTION,
            self::POST_PROCESSING
                => 'primary',
            self::STORE,
            self::SIGN,
            self::CLEANUP
                => 'info',
            self::COMPLETED
                => 'success',
            self::ERROR,
            self::RECOVERABLE_ERROR,
            self::ERROR_HANDLING,
            self::TIMEOUT,
            self::FAILURE
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
