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

namespace local_archiving;

/**
 * Tests for the backup_manager class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for backup_manager
 */
final class backup_manager_test extends \advanced_testcase {

    /**
     * Helper to get the test data generator for local_archiving
     *
     * @return \local_archiving_generator
     */
    private function generator(): \local_archiving_generator {
        /** @var \local_archiving_generator */ // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        return self::getDataGenerator()->get_plugin_generator('local_archiving');
    }

    /**
     * Pops the next ad-hoc task from the queue and executes it.
     *
     * @return void
     * @throws \moodle_exception
     */
    private function execute_next_adhoc_backup_task(): void {
        // Supress task log output.
        ob_start();

        // Execute the task.
        $now = time();
        $task = \core\task\manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\core\\task\\asynchronous_backup_task', $task);
        $task->execute();
        \core\task\manager::adhoc_task_complete($task);

        // Throw away console output.
        ob_end_clean();
    }

    /**
     * Tests creation and retrieval of an activity backup.
     *
     * @dataProvider backup_data_provider
     * @covers \local_archiving\backup_manager
     *
     * @param string $type The type of backup to initiate, either 'activity' or 'course'.
     * @return void
     * @throws \base_setting_exception
     * @throws \base_task_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_backup(string $type): void {
        // Prepare activity to backup.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $context = match ($type) {
            'activity' => \context_module::instance($cm->cmid),
            'course' => \context_course::instance($course->id),
            default => throw new \moodle_exception('Invalid backup type specified')
        };

        // Initiate new backup.
        $res = match ($type) {
            'activity' => backup_manager::initiate_activity_backup($cm->cmid, get_admin()->id),
            'course' => backup_manager::initiate_course_backup($course->id, get_admin()->id),
            default => throw new \moodle_exception('Invalid backup type specified')
        };
        $this->assertNotEmpty($res->backupid, 'Backup ID should not be empty');
        $this->assertEquals(get_admin()->id, $res->userid, 'User ID should match admin ID');
        $this->assertSame($context->id, $res->context, 'Context ID should match context of backed up object');
        $this->assertSame('backup', $res->component, 'Component should be "backup"');
        $this->assertSame($type, $res->filearea, 'Filearea should be backup type');
        $this->assertNotEmpty($res->filename, 'Filename should not be empty');
        $this->assertNotEmpty($res->pathnamehash, 'Pathname hash should not be empty');
        $this->assertNotEmpty($res->file_download_url, 'File download URL should not be empty');

        // Try to retrieve backup metadata from db.
        $backup = new backup_manager($res->backupid);
        $this->assertNotNull($backup);
        $this->assertEquals($res->backupid, $backup->get_backupid(), 'Backup ID mismatch');
        $this->assertEquals(get_admin()->id, $backup->get_userid(), 'Backup created via wrong user');
        $this->assertSame($type, $backup->get_type(), 'Backup type mismatch');

        // Check that backup is not yet completed.
        $this->assertFalse($backup->is_finished_successfully(), 'Backup should not be finished successfully yet');
        $this->assertFalse($backup->is_failed(), 'Backup should not be failed');

        // Incomplete backups should not return a backup file.
        $this->assertNull($backup->get_backupfile(), 'Backup file should be null before execution');

        // Execute the backup and check the result.
        $this->execute_next_adhoc_backup_task();
        $backupfile = $backup->get_backupfile();
        $this->assertInstanceOf(\stored_file::class, $backupfile, 'Backup file should be an instance of stored_file');
    }

    /**
     * Data provider for test_backup
     *
     * @return array[] Test data
     */
    public static function backup_data_provider(): array {
        return [
            "Activity backup" => ['activity'],
            "Course backup" => ['course'],
        ];
    }

    /**
     * Tests cleanup of backup files after a successful backup creation.
     *
     * @covers \local_archiving\backup_manager
     *
     * @return void
     * @throws \base_setting_exception
     * @throws \base_task_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_cleanup(): void {
        // Prepare backup.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $backupmeta = backup_manager::initiate_course_backup($course->id, get_admin()->id);
        $backup = new backup_manager($backupmeta->backupid);
        $this->execute_next_adhoc_backup_task();
        $this->assertTrue($backup->is_finished_successfully(), 'Backup should be finished successfully');

        // Perform cleanup.
        $backupfile = $backup->get_backupfile();
        $this->assertInstanceOf(\stored_file::class, $backupfile, 'Backup file should be an instance of stored_file');
        $res = $backup->cleanup();
        $this->assertTrue($res, 'Cleanup method should return true');

        $backupfile = $backup->get_backupfile();
        $this->assertNull($backupfile, 'Backup file should be null after cleanup');

        // Subsequent cleanup should return false.
        $this->assertFalse($backup->cleanup(), 'Double cleanup method should fail');
    }

    /**
     * Tests the retrieval of orphaned backup files.
     *
     * @covers \local_archiving\backup_manager
     *
     * @return void
     * @throws \base_setting_exception
     * @throws \base_task_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_orphaned_backup_files(): void {
        // Prepare backup.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $backupmeta = backup_manager::initiate_course_backup($course->id, get_admin()->id);
        $backup = new backup_manager($backupmeta->backupid);
        $this->execute_next_adhoc_backup_task();
        $this->assertTrue($backup->is_finished_successfully(), 'Backup should be finished successfully');

        // Try to retrieve only very files (recent backup should not be retrieved).
        $this->assertEmpty(
            backup_manager::get_orphaned_backup_files(agethresholdsec: HOURSECS),
            'Recent backup should not be retrieved as orphaned backup file'
        );

        // Try to retrieve fresh files.
        $res = backup_manager::get_orphaned_backup_files(agethresholdsec: -1);
        $this->assertCount(1, $res, 'One orphaned backup file should be retrieved');
    }

}
