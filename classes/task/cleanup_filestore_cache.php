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
 * Scheduled task for cleaning up the filestore cache
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\task;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use local_archiving\type\filearea;


/**
 * Scheduled task for cleaning up the filestore cache
 *
 * When job artifacts should be accessed through Moodle, e.g., when downloading
 * through the web UI, they are stored in a caching file area inside the Moodle
 * storage system. This task cleans up expired local job artifacts in this
 * file area.
 *
 * Note: This DOES NOT affect any actual job artifacts but is instead limited
 * to the locally cached copies.
 */
class cleanup_filestore_cache extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for the task (shown to admins)
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('cleanup_filestore_cache', 'local_archiving');
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
        global $DB;

        // Retrieve all expired files.
        $deletethreshold = time() - DAYSECS;
        $expiredfilerows = $DB->get_records_select(
            table: 'files',
            select: "filename != '.' AND component = :component AND filearea = :filearea AND timemodified < :deletethreshold",
            params: [
                'component' => filearea::FILESTORE_CACHE->get_component(),
                'filearea' => filearea::FILESTORE_CACHE->value,
                'deletethreshold' => $deletethreshold,
            ],
            sort: 'timemodified ASC',
            fields: 'id',
        );

        if (!$expiredfilerows) {
            mtrace('No expired files found in filestore cache.');
        }

        // Delete expired files.
        $fs = get_file_storage();
        foreach ($expiredfilerows as $filerow) {
            $file = $fs->get_file_by_id($filerow->id);
            $file->delete();
            mtrace(
                "Deleted expired file: ".
                "{$file->get_filename()} (file id: {$file->get_id()}) (file handle id: {$file->get_itemid()})"
            );
        }
    }

}
