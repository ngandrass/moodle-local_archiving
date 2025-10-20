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
 * Scheduled task for deleting job artifacts that have expired retention times
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\task;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use local_archiving\file_handle;


/**
 * Scheduled task for deleting expired job artifact files
 *
 * If automatic deletion is enabled for a job artifact, the file_handle contains
 * a retention time. After this unix timestamp has passed, the file should be
 * deleted automatically. This scheduled task performs this cleanup.
 */
class delete_expired_artifacts extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for the task (shown to admins)
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('delete_expired_artifacts', 'local_archiving');
    }

    /**
     * Removes all files that have not been accessed recently from the local
     * Moodle filestore cache.
     *
     * Note: The timemodified field is set initially when the file is cached and
     * is updated whenever it is downloaded to from the local cache. This
     * prevents actively used cache copies from being deleted.
     */
    public function execute() {
        // Get all expired artifact file handles.
        $handles = file_handle::get_expired();
        if (empty($handles)) {
            mtrace('No expired artifact files found, nothing to delete. Exiting.');
            return;
        }

        mtrace("Found " . count($handles) . " expired artifact files, deleting...");
        foreach ($handles as $handle) {
            try {
                $handle->archivingstore()->delete($handle);
                $handle->mark_as_deleted();
                mtrace("✅ Deleted artifact file: {$handle->filename} (Job-ID: {$handle->jobid}, File-ID: {$handle->id})");
            } catch (\Exception $e) {
                mtrace(
                    "❌ Failed to delete artifact file: {$handle->filename} (Job-ID: {$handle->jobid}, File-ID: {$handle->id}) " .
                    $e->getMessage()
                );
            }
        }
    }
}
