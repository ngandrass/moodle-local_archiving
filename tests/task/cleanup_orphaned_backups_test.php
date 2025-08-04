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

namespace local_archiving\task;

/**
 * Tests for the cleanup_orphaned_backups task.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the cleanup_orphaned_backups task.
 */
final class cleanup_orphaned_backups_test extends \advanced_testcase {

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
     * Tests retrieval of the task name.
     *
     * @covers \local_archiving\task\cleanup_orphaned_backups
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_get_name(): void {
        $task = new cleanup_orphaned_backups();
        $this->assertNotEmpty($task->get_name(), 'Task name should not be empty');
    }

    /**
     * Tests that orphaned backups are deleted correctly while active backups remain untouched.
     *
     * @covers \local_archiving\task\cleanup_orphaned_backups
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_delete_orphaned_backups(): void {
        // Prepare orphaned and non-orphaned backups.
        $this->resetAfterTest();

        $orphanedbackups = [
            $this->generator()->create_moodle_course_backup_stub_file(time() - WEEKSECS),
            $this->generator()->create_moodle_course_backup_stub_file(time() - 2 * DAYSECS),
        ];

        $activebackups = [
            $this->generator()->create_moodle_course_backup_stub_file(time() - 42 * MINSECS),
            $this->generator()->create_moodle_course_backup_stub_file(time() - 2 * HOURSECS),
        ];

        // Execute the task (suppressing trace output).
        $task = new cleanup_orphaned_backups();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify that orphaned backups were deleted.
        $fs = get_file_storage();
        foreach ($orphanedbackups as $f) {
            $this->assertFalse(
                $fs->get_file_by_id($f->get_id()),
                'Orphaned backup file should have been deleted: ' . $f->get_filename()
            );
        }

        // Verify that active backups were not deleted.
        foreach ($activebackups as $f) {
            $this->assertSame(
                $f->get_contenthash(),
                $fs->get_file_by_id($f->get_id())->get_contenthash(),
                'Active backup file should not have been deleted: ' . $f->get_filename()
            );
        }
    }

    /**
     * Tests that no actions are performed if no orphaned backups exist.
     *
     * @covers \local_archiving\task\cleanup_orphaned_backups
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_delete_orphaned_backups_no_orphans(): void {
        $this->resetAfterTest();
        $activebackup = $this->generator()->create_moodle_course_backup_stub_file();

        // Execute the task (suppressing trace output).
        $task = new cleanup_orphaned_backups();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify that the active backup was not deleted.
        $fs = get_file_storage();
        $this->assertSame(
            $activebackup->get_contenthash(),
            $fs->get_file_by_id($activebackup->get_id())->get_contenthash(),
            'Active backup file should not have been deleted: ' . $activebackup->get_filename()
        );
    }

}
