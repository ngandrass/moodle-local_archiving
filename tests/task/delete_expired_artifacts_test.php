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

use local_archiving\file_handle;
use local_archiving\local\type\db_table;

/**
 * Tests for the delete_expired_artifacts task.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the delete_expired_artifacts task.
 */
final class delete_expired_artifacts_test extends \advanced_testcase {
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
     * @covers \local_archiving\task\delete_expired_artifacts
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_get_name(): void {
        $task = new delete_expired_artifacts();
        $this->assertNotEmpty($task->get_name(), 'Task name should not be empty');
    }

    /**
     * Tests that expired artifacts are removed but active files are kept.
     *
     * @covers \local_archiving\task\delete_expired_artifacts
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_expired_artifacts(): void {
        // Prepare mixed test files.
        $this->resetAfterTest();
        $generator = $this->generator();

        $expiredfiles = [
            $generator->create_file_handle(['retentiontime' => time() - 1]),
            $generator->create_file_handle(['retentiontime' => time() - MINSECS]),
            $generator->create_file_handle(['retentiontime' => time() - YEARSECS]),
        ];

        $activefiles = [
            $generator->create_file_handle(['retentiontime' => null]),
            $generator->create_file_handle(['retentiontime' => time() + MINSECS]),
            $generator->create_file_handle(['retentiontime' => time() + YEARSECS]),
        ];

        // Run the cleanup task (ignore trace output).
        $task = new delete_expired_artifacts();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Check that expired files were deleted.
        foreach ($expiredfiles as $file) {
            $freshhandle = file_handle::get_by_id($file->id);
            $this->assertTrue($freshhandle->deleted, 'Expired file should be marked as deleted');
        }

        // Check that active files still exist.
        foreach ($activefiles as $file) {
            $freshhandle = file_handle::get_by_id($file->id);
            $this->assertFalse($freshhandle->deleted, 'Active file should not be marked as deleted');
        }
    }

    /**
     * Tests that cached copies and TSP data is also removed when an expired artifact is deleted.
     *
     * @covers \local_archiving\task\delete_expired_artifacts
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_expired_artifact_removes_cache_file_and_tsp_data(): void {
        global $DB;

        // Prepare expired artifact with local cache copy and TSP data.
        $this->resetAfterTest();
        $generator = $this->generator();
        $expiredfile = $generator->create_file_handle(['retentiontime' => time() - 1]);
        $generator->create_filestore_cache_file($expiredfile->id);
        $DB->insert_record(db_table::TSP->value, [
            'filehandleid' => $expiredfile->id,
            'timecreated' => time(),
            'server' => 'localhost',
            'timestampquery' => 'sample-query',
            'timestampreply' => 'sample-reply',
        ]);

        // Run the cleanup task (ignore trace output).
        $task = new delete_expired_artifacts();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Check that the cache file and TSP data are gone.
        $handle = file_handle::get_by_id($expiredfile->id);
        $this->assertNull($handle->get_local_file(), 'Cache file should be removed once the artifact expires.');
        $this->assertFalse(
            $DB->record_exists(db_table::TSP->value, ['filehandleid' => $expiredfile->id]),
            'TSP data should be removed once the artifact expires.'
        );
    }

    /**
     * Tests that no actions are performed if only active artifacts exist.
     *
     * @covers \local_archiving\task\delete_expired_artifacts
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_no_expired_artifacts(): void {
        // Prepare only active test files.
        $this->resetAfterTest();
        $generator = $this->generator();

        $activefiles = [
            $generator->create_file_handle(['retentiontime' => null]),
            $generator->create_file_handle(['retentiontime' => time() + MINSECS]),
            $generator->create_file_handle(['retentiontime' => time() + YEARSECS]),
        ];

        // Run the cleanup task (ignore trace output).
        $task = new delete_expired_artifacts();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Check that no files were deleted.
        foreach ($activefiles as $file) {
            $freshhandle = file_handle::get_by_id($file->id);
            $this->assertFalse($freshhandle->deleted, 'Active file should not be marked as deleted');
        }
    }
}
