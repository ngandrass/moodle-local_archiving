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
 * Status categories archive jobs can be filtered by
 *
 * @package     local_archiving
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\local\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * User-facing status categories that group multiple archive_job_status values
 */
enum archive_job_status_filter: string {
    /** @var string Job is not finished yet */
    case IN_PROGRESS = 'inprogress';

    /** @var string Job completed successfully */
    case COMPLETED = 'completed';

    /** @var string Job encountered an error */
    case FAILED = 'failed';

    /** @var string Archive data of the job was deleted */
    case DELETED = 'deleted';

    /**
     * Retrieves all archive job states that belong to this status category
     *
     * @return archive_job_status[] List of states within this category
     */
    public function states(): array {
        return match ($this) {
            self::IN_PROGRESS => [
                archive_job_status::UNINITIALIZED,
                archive_job_status::QUEUED,
                archive_job_status::PRE_PROCESSING,
                archive_job_status::ACTIVITY_ARCHIVING,
                archive_job_status::BACKUP_COLLECTION,
                archive_job_status::POST_PROCESSING,
                archive_job_status::STORE,
                archive_job_status::SIGN,
                archive_job_status::CLEANUP,
            ],
            self::COMPLETED => [
                archive_job_status::COMPLETED,
            ],
            self::FAILED => [
                archive_job_status::ERROR,
                archive_job_status::RECOVERABLE_ERROR,
                archive_job_status::ERROR_HANDLING,
                archive_job_status::TIMEOUT,
                archive_job_status::FAILURE,
                archive_job_status::UNKNOWN,
            ],
            self::DELETED => [
                archive_job_status::DELETED,
            ],
        };
    }

    /**
     * Returns the localized name of this status category
     *
     * @return string Localized name
     * @throws \coding_exception
     */
    public function name(): string {
        return get_string('job_status_filter_' . $this->value, 'local_archiving');
    }
}
