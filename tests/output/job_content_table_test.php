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
 * Tests for the job_content_table class.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\output;

use local_archiving\archive_job;
use local_archiving\local\type\task_content_metadata;

/**
 * Tests for the job_content_table class.
 */
final class job_content_table_test extends \advanced_testcase {
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
     * Creates a job content table with the given request parameters (e.g., filters) set
     *
     * @param archive_job $job Job to create the table for
     * @param array $params Request parameters to set during table creation
     * @return job_content_table Created table with its base URL defined
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function create_table(archive_job $job, array $params = []): job_content_table {
        global $PAGE;

        $_GET = array_merge($_GET, $params);
        try {
            $table = new job_content_table('contenttable', $job);
            $table->define_baseurl($PAGE->url);
        } finally {
            foreach (array_keys($params) as $name) {
                unset($_GET[$name]);
            }
        }

        return $table;
    }

    /**
     * Queries the user IDs of all rows listed in a job content table with the given request parameters
     *
     * @param archive_job $job Job to create the table for
     * @param array $params Request parameters to set during table creation
     * @return int[] User IDs of all listed rows
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function query_user_ids(archive_job $job, array $params = []): array {
        $table = $this->create_table($job, $params);
        $table->setup();
        $table->query_db(25, false);

        return array_values(array_map(fn ($row) => (int) $row->userid, $table->rawdata));
    }

    /**
     * Tests that the table lists archived objects with their users and labels
     *
     * @covers \local_archiving\output\job_content_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_table_output(): void {
        global $PAGE;
        $PAGE->set_url('/');

        // Prepare a job with archived content of two users.
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $usera = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Archived']);
        $userb = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Backup']);

        $job = $this->generator()->create_archive_job([], $course, $cm);
        $task = $this->generator()->create_activity_archiving_task([], $job);
        $task->store_task_content_metadata([
            new task_content_metadata($task->get_id(), $usera->id, 'quiz_attempts', 1337, null),
            new task_content_metadata($task->get_id(), $userb->id, null, null, 'Custom summary'),
        ]);

        // Render the table.
        $table = $this->create_table($job);
        ob_start();
        $table->out(25, false);
        $output = ob_get_clean();

        // Validate output.
        $this->assertStringContainsString('Alice Archived', $output, 'User A should be listed');
        $this->assertStringContainsString('Bob Backup', $output, 'User B should be listed');
        $this->assertStringContainsString('quiz_attempts #1337', $output, 'Default label should be shown');
        $this->assertStringContainsString('Custom summary', $output, 'Summary should be shown as fallback label');
    }

    /**
     * Tests that only contents of the given job are listed
     *
     * @covers \local_archiving\output\job_content_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_only_contents_of_job_are_listed(): void {
        global $PAGE;
        $PAGE->set_url('/');

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();

        $joba = $this->generator()->mock_archived_content($usera->id, $course, $cm);
        $jobb = $this->generator()->mock_archived_content($userb->id, $course, $cm);
        $jobempty = $this->generator()->create_archive_job([], $course, $cm);

        $this->assertEquals([$usera->id], $this->query_user_ids($joba));
        $this->assertEquals([$userb->id], $this->query_user_ids($jobb));
        $this->assertEmpty($this->query_user_ids($jobempty), 'A job without content should list no rows');
    }

    /**
     * Tests filtering the listed contents by user
     *
     * @covers \local_archiving\output\job_content_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_user_filter(): void {
        global $PAGE;
        $PAGE->set_url('/');

        // Prepare a job with archived content of two users.
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $usera = $this->getDataGenerator()->create_and_enrol($course);
        $userb = $this->getDataGenerator()->create_and_enrol($course);
        $unrelated = $this->getDataGenerator()->create_user();

        $job = $this->generator()->create_archive_job([], $course, $cm);
        $task = $this->generator()->create_activity_archiving_task([], $job);
        $task->store_task_content_metadata([
            new task_content_metadata($task->get_id(), $usera->id, 'quiz_attempts', 1, null),
            new task_content_metadata($task->get_id(), $userb->id, 'quiz_attempts', 2, null),
        ]);

        $this->assertEqualsCanonicalizing([$usera->id, $userb->id], $this->query_user_ids($job));
        $this->assertEquals([$usera->id], $this->query_user_ids($job, ['userid' => $usera->id]));
        $this->assertEquals([$userb->id], $this->query_user_ids($job, ['userid' => $userb->id]));
        $this->assertEqualsCanonicalizing(
            [$usera->id, $userb->id],
            $this->query_user_ids($job, ['userid' => $unrelated->id]),
            'Users without relation to the course should be ignored as filter'
        );
    }
}
