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
 * Tests for the remote_archive_worker class
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;


/**
 * Tests for the remote_archive_worker class
 */
final class remote_archive_worker_test extends \advanced_testcase {
    /**
     * Returns the data generator for the archivingmod_assign plugin
     *
     * @return \archivingmod_assign_generator The data generator for the archivingmod_assign plugin
     */
    // phpcs:ignore
    public static function getDataGenerator(): \archivingmod_assign_generator {
        return parent::getDataGenerator()->get_plugin_generator('archivingmod_assign');
    }

    /**
     * Tests the get_status method of the remote archive worker.
     *
     * @covers \archivingmod_assign\remote_archive_worker
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_get_status(): void {
        // Create a worker instance.
        $this->resetAfterTest();
        set_config('worker_url', 'http://lorem.ipsum', 'archivingmod_assign');
        set_config('internal_wwwroot', 'http://internal.moodle', 'archivingmod_assign');
        $worker = remote_archive_worker::instance();

        // Try to get the status.
        $this->expectException(\moodle_exception::class, 'Since we have not set up a real worker, this should fail.');
        $worker->get_status();
    }

    /**
     * Tests enqueuing an archive job with valid data.
     *
     * @covers \archivingmod_assign\remote_archive_worker
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_enqueue_archive_job(): void {
        // Create a worker instance and an activity archiving task.
        $this->resetAfterTest();
        set_config('worker_url', 'http://lorem.ipsum', 'archivingmod_assign');
        set_config('internal_wwwroot', 'http://internal.moodle', 'archivingmod_assign');
        $worker = remote_archive_worker::instance();
        $mocks = $this->getDataGenerator()->create_mock_task(populateassignment: true);

        // Try to enqueue a job.
        $this->expectException(\moodle_exception::class, 'Since we have not set up a real worker, this should fail.');
        $worker->enqueue_archive_job('faketoken', $mocks->task, [$mocks->submission->id]);
    }

    /**
     * Tests enqueuing an archive job with no submissions.
     *
     * @covers \archivingmod_assign\remote_archive_worker
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_enqueue_archive_job_no_submissions(): void {
        // Create a worker instance and an activity archiving task.
        $this->resetAfterTest();
        set_config('worker_url', 'http://lorem.ipsum', 'archivingmod_assign');
        set_config('internal_wwwroot', 'http://internal.moodle', 'archivingmod_assign');
        $worker = remote_archive_worker::instance();
        $mocks = $this->getDataGenerator()->create_mock_task(populateassignment: false);

        // Try to enqueue a job without any submission IDs.
        $this->expectException(\coding_exception::class, 'Since there are no submission IDs, this should fail.');
        $worker->enqueue_archive_job('faketoken', $mocks->task, []);
    }
}
