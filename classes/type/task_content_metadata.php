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
 * A single metadata instance for data that is part of an activity archiving task
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Metadata for a single piece of data that is part of an activity archiving
 * task (e.g., quiz attempt, assignment submission, etc.).
 *
 * One such metadata instance is created for each piece of data that is exported
 * by a single activity archiving task. This metadata is used to track the
 * contents of generated archives.
 */
final class task_content_metadata {

    /**
     * Creates a new task_content_metadata instance.
     *
     * @param int $taskid ID of the activity archiving task this metadata belongs to
     * @param int $userid ID of the user that owns the referenced data
     * @param string|null $reftable Name of the database table that contains the
     * referenced data, or null if not applicable
     * @param int|null $refid ID of the referenced data in the reftable, or null
     * if not applicable
     * @param string|null $summary Summary of the referenced data (e.g., "Quiz
     * attempt 1234"), or null if not applicable
     * @throws \moodle_exception If neither a summary nor a reference table and ID is provided
     */
    public function __construct(
        /** @var int ID of the activity archiving task this metadata belongs to */
        public readonly int $taskid,
        /** @var int ID of the user that owns the referenced data */
        public readonly int $userid,
        /** @var string|null Name of the database table that contains the referenced data, or null if not applicable */
        public readonly ?string $reftable,
        /** @var int|null ID of the referenced data in the reftable, or null if not applicable */
        public readonly ?int $refid,
        /** @var string|null Summary of the referenced data (e.g., "Quiz attempt 1234"), or null if not applicable */
        public readonly ?string $summary
    ) {
        // Ensure that at least a summary or a reference table and ID is provided.
        if (empty($this->summary) && (empty($this->reftable) || empty($this->refid))) {
            throw new \moodle_exception('task_content_metadata_must_contain_summary_or_ref', 'local_archiving');
        }
    }

    /**
     * Returns the metadata as an associative array.
     *
     * @return array Associative array representation of the metadata
     */
    public function as_array(): array {
        return get_object_vars($this);
    }

}
