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
 * @package   archivingmod_quiz
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;


/**
 * Tests for the remote_archive_worker class
 */
final class remote_archive_worker_test extends \advanced_testcase {
    /**
     * Returns the data generator for the archivingmod_quiz plugin
     *
     * @return \archivingmod_quiz_generator The data generator for the archivingmod_quiz plugin
     */
    // phpcs:ignore
    public static function getDataGenerator(): \archivingmod_quiz_generator {
        return parent::getDataGenerator()->get_plugin_generator('archivingmod_quiz');
    }

    /**
     * Tests the get_status method of the remote archive worker.
     *
     * @covers \archivingmod_quiz\remote_archive_worker
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_get_status(): void {
        // Create a worker instance.
        $this->resetAfterTest();
        set_config('worker_url', 'http://lorem.ipsum', 'archivingmod_quiz');
        set_config('internal_wwwroot', 'http://internal.moodle', 'archivingmod_quiz');
        $worker = remote_archive_worker::instance();

        // Try to get the status.
        $this->expectException(\moodle_exception::class, 'Since we have not set up a real worker, this should fail.');
        $worker->get_status();
    }

    /**
     * Tests enqueuing an archive job with valid data.
     *
     * @covers \archivingmod_quiz\remote_archive_worker
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_enqueue_archive_job(): void {
        // Create a worker instance and an activity archiving task.
        $this->resetAfterTest();
        set_config('worker_url', 'http://lorem.ipsum', 'archivingmod_quiz');
        set_config('internal_wwwroot', 'http://internal.moodle', 'archivingmod_quiz');
        $worker = remote_archive_worker::instance();
        $mocks = $this->getDataGenerator()->create_mock_task(createattempt: true);

        // Try to enqueue a job.
        $this->expectException(\moodle_exception::class, 'Since we have not set up a real worker, this should fail.');
        $worker->enqueue_archive_job('faketoken', $mocks->task, [$mocks->attempts[0]->attemptid]);
    }

    /**
     * Tests enqueuing an archive job with no attempts.
     *
     * @covers \archivingmod_quiz\remote_archive_worker
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_enqueue_archive_job_no_attempts(): void {
        // Create a worker instance and an activity archiving task.
        $this->resetAfterTest();
        set_config('worker_url', 'http://lorem.ipsum', 'archivingmod_quiz');
        set_config('internal_wwwroot', 'http://internal.moodle', 'archivingmod_quiz');
        $worker = remote_archive_worker::instance();
        $mocks = $this->getDataGenerator()->create_mock_task(createattempt: false);

        // Try to enqueue a job.
        $this->expectException(\coding_exception::class, 'Since there are no attempts, this should fail.');
        $worker->enqueue_archive_job('faketoken', $mocks->task, [$mocks->attempts[0]->attemptid]);
    }
}
