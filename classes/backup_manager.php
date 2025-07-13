<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file defines the backup_manager class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use backup;
use backup_controller;
use context_course;
use context_module;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php'); // @codeCoverageIgnore

/**
 * Manages everything related to backups via the Moodle Backup API
 */
class backup_manager {

    /** @var \stdClass Backup controller metadata from DB */
    protected \stdClass $backupmetadata;

    /** @var array Define what to include and exclude in backups */
    public const BACKUP_SETTINGS = [
        'users' => true,
        'anonymize' => false,
        'role_assignments' => true,
        'activities' => true,
        'blocks' => true,
        'files' => true,
        'filters' => true,
        'comments' => true,
        'badges' => true,
        'calendarevents' => true,
        'userscompletion' => true,
        'logs' => true,
        'grade_histories' => true,
        'groups' => true,
        'contentbankcontent' => true,
        'legacyfiles' => true,
    ];

    /**
     * Creates a new BackupManager instance
     *
     * @param string $backupid ID of the backup_controller associated with this backup
     * @throws \dml_exception
     */
    public function __construct(string $backupid) {
        global $DB;

        $this->backupmetadata = $DB->get_record(
            'backup_controllers',
            ['backupid' => $backupid],
            'id, backupid, operation, type, itemid, userid, timecreated',
            MUST_EXIST
        );
        if ($this->backupmetadata->operation != 'backup') {
            throw new \ValueError('Only backup operations are supported.');
        }
    }

    /**
     * Determines if the backup finished successfully
     *
     * @return bool True if backup finished successfully
     * @throws \dml_exception
     */
    public function is_finished_successfully(): bool {
        return $this->get_status() === backup::STATUS_FINISHED_OK;
    }

    /**
     * Determines if the backup failed
     *
     * @return bool True if backup finished with error
     * @throws \dml_exception
     */
    public function is_failed(): bool {
        return $this->get_status() === backup::STATUS_FINISHED_ERR;
    }

    /**
     * Retrieves the current status of this backup
     *
     * @return int Raw backup status value according to backup_controller::STATUS_*
     * @throws \dml_exception
     */
    public function get_status(): int {
        global $DB;
        return $DB->get_record('backup_controllers', ['id' => $this->backupmetadata->id], 'status')->status;
    }

    /**
     * Retrieves the backupid of this instance
     *
     * @return string Backup ID
     */
    public function get_backupid(): string {
        return $this->backupmetadata->backupid;
    }

    /**
     * Retrieves the type of this backup controller
     *
     * @return string Type of this backup controller (e.g. course, activity)
     */
    public function get_type(): string {
        return $this->backupmetadata->type;
    }

    /**
     * Retrieves the ID of the user that initiated this backup
     *
     * @return int User-ID of the backup initiator
     */
    public function get_userid(): int {
        return $this->backupmetadata->userid;
    }

    /**
     * Retrieves the timestamp when the underlying backup controller was created
     *
     * @return int Timestamp of the backup controller creation
     */
    public function get_timecreated(): int {
        return $this->backupmetadata->timecreated;
    }

    /**
     * Retrieves the ID of the backup target object (e.g., courseid or cmid)
     *
     * @return int ID of the backup target object
     */
    public function get_itemid(): int {
        return $this->backupmetadata->itemid;
    }

    /**
     * Retrieves an instance of the backup targets context
     *
     * @return \context Instance of the backup targets context
     */
    public function get_context(): \context {
        return self::get_context_of_backup_type($this->get_type(), $this->get_itemid());
    }

    /**
     * Retrieves the filename for this backup
     *
     * @return string Filename of the backup archive
     */
    public function get_filename(): string {
        return self::generate_backup_filename($this->get_type(), $this->get_itemid(), $this->get_timecreated());
    }

    /**
     * Retrieves a context instance for the given object, based on the given backup type
     *
     * @param string $backuptype Type of the backup controller (one of backup::TYPE_*)
     * @param int $itemid ID of the backup target object
     * @return \context Instance of the context for the given object
     */
    public static function get_context_of_backup_type(string $backuptype, int $itemid): \context {
        return match ($backuptype) {
            backup::TYPE_1COURSE => context_course::instance($itemid),
            backup::TYPE_1ACTIVITY => context_module::instance($itemid),
            default => throw new \ValueError("Backup type not supported"),
        };
    }

    /**
     * Generates the target filename for this backup controller
     *
     * @param string $backuptype Type of the backup controller (one of backup::TYPE_*)
     * @param int $itemid ID of the backup target object
     * @param int $timecreated Timestamp of the backup controller creation
     * @return string Filename for the backup target archive including its extension
     */
    public static function generate_backup_filename(string $backuptype, int $itemid, int $timecreated): string {
        return 'local_archiving-'.$backuptype.'-backup-'.$itemid.'-'.date("Ymd-His", $timecreated).'.mbz';

    }

    /**
     * Retrieves the stored_file for this backup, if available
     *
     * @return \stored_file|null Stored file of the backup artifact or null if not available
     * @throws \dml_exception
     */
    public function get_backupfile(): ?\stored_file {
        if (!$this->is_finished_successfully()) {
            return null;
        }

        $context = $this->get_context();

        $fs = get_file_storage();
        $backupfile = $fs->get_file(
            contextid: $context->id,
            component: 'backup',
            filearea: $this->get_type(),
            itemid: 0,
            filepath: '/',
            filename: $this->get_filename(),
        );

        return $backupfile ?: null;
    }

    /**
     * Cleans up everything related to this backup task, including the deletion
     * of the backup artifact file!
     *
     * @return bool True, if files were deleted
     * @throws \dml_exception
     */
    public function cleanup(): bool {
        $f = $this->get_backupfile();
        if (!$f) {
            return false;
        }

        $f->delete();
        return true;
    }

    /**
     * Initiates a new Moodle backup
     *
     * @param string $type Type of the backup, based on backup::TYPE_*
     * @param int $id ID of the backup object
     * @param int $userid User-ID to associate this backup with
     * @return object Backup metadata object
     * @throws \base_setting_exception
     * @throws \base_task_exception
     * @throws \dml_exception
     */
    protected static function initiate_backup(string $type, int $id, int $userid): object {
        global $CFG, $DB;

        // Initialize backup.
        $context = self::get_context_of_backup_type($type, $id);
        $bc = new backup_controller(
            $type,
            $id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_ASYNC,
            $userid,
            backup::RELEASESESSION_YES
        );

        // Save controller and get assigned IDs / timestamps.
        $bc->save_controller(includeobj: false);
        $backupid = $bc->get_backupid();
        $timecreated = $DB->get_field('backup_controllers', 'timecreated', ['backupid' => $backupid]);
        $filename = self::generate_backup_filename($type, $id, $timecreated);

        // Configure backup.
        $tasks = $bc->get_plan()->get_tasks();
        foreach ($tasks as $task) {
            if ($task instanceof \backup_root_task) {
                $task->get_setting('filename')->set_value($filename);

                foreach (self::BACKUP_SETTINGS as $name => $value) {
                    $task->get_setting($name)->set_value($value);
                }

                // Questions are not automatically included in Moodle 4.5 and below.
                if ($CFG->branch <= 405) {
                    $task->get_setting('questionbank')->set_value(true);
                }
            }
        }

        // Enqueue as adhoc task.
        $bc->set_status(backup::STATUS_AWAITING);
        $asynctask = new \core\task\asynchronous_backup_task();
        $asynctask->set_custom_data(['backupid' => $backupid]);
        $asynctask->set_userid($userid);
        \core\task\manager::queue_adhoc_task($asynctask);

        // Generate backup file url.
        $url = strval(\moodle_url::make_webservice_pluginfile_url(
            $context->id,
            'backup',
            $type,
            null,  // The make_webservice_pluginfile_url expects null if no itemid is given against it's PHPDoc specification ...
            '/',
            $filename
        ));

        $internalwwwroot = get_config('local_archiving')->internal_wwwroot;
        if ($internalwwwroot) {
            $url = str_replace(rtrim($CFG->wwwroot, '/'), rtrim($internalwwwroot, '/'), $url);
        }

        return (object) [
            'backupid' => $backupid,
            'userid' => $userid,
            'context' => $context->id,
            'component' => 'backup',
            'filearea' => $type,
            'filepath' => '/',
            'filename' => $filename,
            'itemid' => null,
            'pathnamehash' => \file_storage::get_pathname_hash($context->id, 'backup', $type, 0, '/', $filename),
            'file_download_url' => $url,
        ];
    }

    /**
     * Initiates a new activity backup
     *
     * @param int $cmid ID of the targeted course module / activity
     * @param int $userid User-ID to associate this backup with
     * @return object Backup metadata object
     * @throws \base_setting_exception
     * @throws \base_task_exception
     * @throws \dml_exception
     */
    public static function initiate_activity_backup(int $cmid, int $userid): object {
        return self::initiate_backup(backup::TYPE_1ACTIVITY, $cmid, $userid);
    }

    /**
     * Initiates a new course backup
     *
     * @param int $courseid ID of the course to backup
     * @param int $userid User-ID to associate this backup with
     * @return object Backup metadata object
     * @throws \base_setting_exception
     * @throws \base_task_exception
     * @throws \dml_exception
     */
    public static function initiate_course_backup(int $courseid, int $userid): object {
        return self::initiate_backup(backup::TYPE_1COURSE, $courseid, $userid);
    }

    /**
     * Searches for backup files that were created by this plugin but were never cleaned up
     *
     * This can happen, if an archiving job requested a backup but failed before
     * the asynchronous backup creation task finished.
     *
     * @param int $agethresholdsec Number of seconds after which a backup file is considered orphaned
     * @return \stored_file[] Array of stored_file instances representing orphaned backup files
     * @throws \dml_exception On database errors
     * @throws \moodle_exception If the age threshold is not greater than zero
     */
    public static function get_orphaned_backup_files(int $agethresholdsec = DAYSECS): array {
        global $DB;

        // Validate given threshold.
        if ($agethresholdsec <= 0) {
            throw new \moodle_exception('Age threshold must be greater than zero.');
        }

        // Find all backup files that are older than the given threshold.
        $orphanedfilerecords = $DB->get_records_select(
            'files',
            "timemodified < :threshold AND
            component = 'backup' AND
            mimetype = 'application/vnd.moodle.backup' AND
            (
                (filename LIKE 'local_archiving-course-backup-%' AND filearea = 'course') OR
                (filename LIKE 'local_archiving-activity-backup-%' AND filearea = 'activity')
            )",
            ['threshold' => time() - $agethresholdsec],
        );

        // Return stored_file instances for each record.
        $fs = get_file_storage();
        $orphanedfiles = [];
        foreach ($orphanedfilerecords as $record) {
            $file = $fs->get_file_instance($record);
            if ($file) {
                $orphanedfiles[] = $file;
            }
        }

        return $orphanedfiles;
    }

}
