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
 * Scheduled task for cleaning up orphaned internal Moodle backups that were
 * created by an archiving job but failed to be deleted due to prior errors.
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\task;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use local_archiving\backup_manager;


/**
 * Scheduled task for cleaning up orphaned internal Moodle backups.
 *
 * When an archiving job requests creation of a Moodle backup, it is created
 * asynchronously by a separate Moodle ad-hoc task. If the archiving job fails
 * prior to the backup being created, the backup file will remain in the target
 * course as an orphaned file. This scheduled task will clean up such orphaned
 * backup files.
 */
class cleanup_orphaned_backups extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for the task (shown to admins)
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('cleanup_orphaned_backups', 'local_archiving');
    }

    /**
     * Looks for orphaned internal Moodle backups that were created but never
     * cleaned up.
     */
    public function execute() {
        $orphanedfiles = backup_manager::get_orphaned_backup_files(DAYSECS);

        if (empty($orphanedfiles)) {
            mtrace('No orphaned backup files found.');
            return;
        }

        foreach ($orphanedfiles as $f) {
            try {
                $f->delete();
                mtrace('Deleted orphaned backup file: '.$f->get_filename());
            } catch (\Exception $e) {
                mtrace('Failed to delete orphaned backup file "'.$f->get_filename().'" with error: ' . $e->getMessage());
            }
        }
    }

}
