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
 * Tests for the cleanup_filestore_cache task.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the cleanup_filestore_cache task.
 */
final class cleanup_filestore_cache_test extends \advanced_testcase {
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
     * @covers \local_archiving\task\cleanup_filestore_cache
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_get_name(): void {
        $task = new cleanup_filestore_cache();
        $this->assertNotEmpty($task->get_name(), 'Task name should not be empty');
    }

    /**
     * Tests that expired artifacts are removed from the Moodle filestore.
     *
     * @covers \local_archiving\task\cleanup_filestore_cache
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_expired_artifacts(): void {
        // Prepare mixed test files.
        $this->resetAfterTest();

        $expiredfiles = [
            // Accessed once, but never after.
            $this->generator()->create_filestore_cache_file(
                timecreated: time() - WEEKSECS * 2,
                timemodified: time() - WEEKSECS * 2
            ),
            // Cached a long time ago, but accessed until recently. Last access is still too old.
            $this->generator()->create_filestore_cache_file(
                timecreated: time() - WEEKSECS * 4,
                timemodified: time() - WEEKSECS
            ),
        ];

        $activefiles = [
            // Cached just now.
            $this->generator()->create_filestore_cache_file(
                timecreated: time(),
                timemodified: time()
            ),
            // Cached today, only accessed once.
            $this->generator()->create_filestore_cache_file(
                timecreated: time() - DAYSECS / 2,
                timemodified: time() - DAYSECS / 2
            ),
            // Cached a long time ago, but accessed today.
            $this->generator()->create_filestore_cache_file(
                timecreated: time() - WEEKSECS * 4,
                timemodified: time() - HOURSECS
            ),
        ];

        // Run the cleanup task (ignore trace output).
        $task = new cleanup_filestore_cache();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Check that expired files were deleted.
        $fs = get_file_storage();
        foreach ($expiredfiles as $file) {
            $this->assertFalse($fs->get_file_by_id($file->get_id()), 'Expired file should be deleted: ' . $file->get_filename());
        }

        // Check that active files still exist.
        foreach ($activefiles as $file) {
            $this->assertEquals(
                $file->get_contenthash(),
                $fs->get_file_by_id($file->get_id())->get_contenthash(),
                'Active file should still exist: ' . $file->get_filename()
            );
        }
    }

    /**
     * Tests that no actions are performed if only active files exist.
     *
     * @covers \local_archiving\task\cleanup_filestore_cache
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_no_expired_artifacts(): void {
        // Prepare only active test files.
        $this->resetAfterTest();
        $activefiles = [
            $this->generator()->create_filestore_cache_file(timecreated: time(), timemodified: time()),
            $this->generator()->create_filestore_cache_file(timecreated: time() - HOURSECS, timemodified: time() - HOURSECS),
            $this->generator()->create_filestore_cache_file(timecreated: time() - WEEKSECS * 4, timemodified: time() - MINSECS),
        ];

        // Run the cleanup task (ignore trace output).
        $task = new cleanup_filestore_cache();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Check that no files were deleted.
        $fs = get_file_storage();
        foreach ($activefiles as $file) {
            $this->assertEquals(
                $file->get_contenthash(),
                $fs->get_file_by_id($file->get_id())->get_contenthash(),
                'Active file should still exist: ' . $file->get_filename()
            );
        }
    }
}
