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

namespace local_archiving\output;

use local_archiving\local\type\archive_job_status;
use local_archiving\local\type\archive_job_status_filter;
use local_archiving\local\type\task_content_metadata;

/**
 * Tests for the job_overview_table class.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the job_overview_table class.
 */
final class job_overview_table_test extends \advanced_testcase {
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
     * Creates a job overview table with the given request parameters (e.g., search or filters) set
     *
     * @param \context $ctx Context to create the table for
     * @param array $params Request parameters to set during table creation
     * @return job_overview_table Created table with its base URL defined
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \moodle_exception
     */
    private function create_table(\context $ctx, array $params = []): job_overview_table {
        global $PAGE;

        $_GET = array_merge($_GET, $params);
        try {
            $table = new job_overview_table('overviewtable', $ctx);
            $table->define_baseurl($PAGE->url);
        } finally {
            foreach (array_keys($params) as $name) {
                unset($_GET[$name]);
            }
        }

        return $table;
    }

    /**
     * Queries the IDs of all jobs listed in a job overview table with the given request parameters
     *
     * @param \context $ctx Context to create the table for
     * @param array $params Request parameters to set during table creation
     * @return int[] IDs of all listed jobs
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function query_job_ids(\context $ctx, array $params = []): array {
        $table = $this->create_table($ctx, $params);
        $table->setup();
        $table->query_db(25, false);

        return array_keys($table->rawdata);
    }

    /**
     * Basic tests generation of the job overview table output.
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_table_output(): void {
        // Set page URL to dummy value to prevent errors.
        global $PAGE;
        $PAGE->set_url('/');

        // Prepare archive jobs at various states.
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);

        $job1 = $this->generator()->create_archive_job([], $course, $cm);

        $job2 = $this->generator()->create_archive_job([], $course, $cm);
        $job2->set_status(archive_job_status::STORE);

        $job3 = $this->generator()->create_archive_job([], $course, $cm);
        $job3->set_status(archive_job_status::COMPLETED);
        $this->generator()->create_file_handle(['jobid' => $job3->get_id()]);

        $job4 = $this->generator()->create_archive_job([], $course, $cm);
        $job4->set_status(archive_job_status::FAILURE);

        // Create the table and output it.
        $table = new job_overview_table('overviewtable', $job1->get_context());
        $table->define_baseurl($PAGE->url);
        ob_start();
        $table->out(25, false);
        $output = ob_get_clean();

        // Validate output.
        $this->assertNotEmpty($output);
        $this->assertStringContainsString($job1->get_id(), $output, 'Job 1 ID should be in output');
        $this->assertStringContainsString($job2->get_id(), $output, 'Job 2 ID should be in output');
        $this->assertStringContainsString($job3->get_id(), $output, 'Job 3 ID should be in output');
        $this->assertStringContainsString($job4->get_id(), $output, 'Job 4 ID should be in output');
    }

    /**
     * Tests that the course level table does not include jobs from other
     * courses whose context path starts with the path of the current course.
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_course_context_excludes_jobs_from_other_courses(): void {
        global $DB, $PAGE;
        $PAGE->set_url('/');

        // Prepare two courses with one archive job each.
        $this->resetAfterTest();
        $this->setAdminUser();
        $course1 = $this->generator()->create_course();
        $cm1 = $this->generator()->create_module('quiz', ['course' => $course1->id]);
        $job1 = $this->generator()->create_archive_job([], $course1, $cm1);

        $course2 = $this->generator()->create_course();
        $cm2 = $this->generator()->create_module('quiz', ['course' => $course2->id]);
        $job2 = $this->generator()->create_archive_job([], $course2, $cm2);

        // Simulate a context path prefix collision (e.g., /1/3/25 vs. /1/3/250/42) without creating hundreds of contexts.
        $coursectx1 = \context_course::instance($course1->id);
        $cmctx2 = \context_module::instance($cm2->cmid);
        $DB->set_field('context', 'path', $coursectx1->path . '0/' . $cmctx2->id, ['id' => $cmctx2->id]);
        \context_helper::reset_caches();

        // Only jobs from course 1 must be selected.
        $table = new job_overview_table('overviewtable', $coursectx1);
        $table->define_baseurl($PAGE->url);
        $table->setup();
        $table->query_db(25, false);
        $ids = array_keys($table->rawdata);

        $this->assertContains($job1->get_id(), $ids, 'Job from course 1 should be listed');
        $this->assertNotContains($job2->get_id(), $ids, 'Job from course 2 must not be listed');
    }

    /**
     * Tests that the activity level table does not include jobs from sibling
     * activities whose context path starts with the path of the current activity.
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_module_context_excludes_jobs_from_sibling_activities(): void {
        global $DB, $PAGE;
        $PAGE->set_url('/');

        // Prepare one course with two activities and one archive job each.
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->generator()->create_course();
        $cm1 = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $job1 = $this->generator()->create_archive_job([], $course, $cm1);
        $cm2 = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $job2 = $this->generator()->create_archive_job([], $course, $cm2);

        // Simulate a context path prefix collision (e.g., /1/3/25/30 vs. /1/3/25/300).
        $cmctx1 = \context_module::instance($cm1->cmid);
        $cmctx2 = \context_module::instance($cm2->cmid);
        $DB->set_field('context', 'path', $cmctx1->path . '0', ['id' => $cmctx2->id]);
        \context_helper::reset_caches();

        // Only jobs from activity 1 must be selected.
        $table = new job_overview_table('overviewtable', $cmctx1);
        $table->define_baseurl($PAGE->url);
        $table->setup();
        $table->query_db(25, false);
        $ids = array_keys($table->rawdata);

        $this->assertContains($job1->get_id(), $ids, 'Job from activity 1 should be listed');
        $this->assertNotContains($job2->get_id(), $ids, 'Job from activity 2 must not be listed');
    }

    /**
     * Tests that only course and module contexts are accepted
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \moodle_exception
     */
    public function test_rejects_invalid_context(): void {
        global $PAGE;
        $PAGE->set_url('/');
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\coding_exception::class);
        new job_overview_table('overviewtable', \context_system::instance());
    }

    /**
     * Tests that jobs are filtered by their status category
     *
     * @covers \local_archiving\output\job_overview_table
     * @dataProvider status_filter_provider
     *
     * @param string $filter Value of the status filter to apply
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_status_filter(string $filter): void {
        global $PAGE;
        $PAGE->set_url('/');
        $this->resetAfterTest();
        $this->setAdminUser();

        // Prepare one job per representative status.
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $jobstatuses = [];
        $statuses = [
            archive_job_status::UNINITIALIZED,
            archive_job_status::STORE,
            archive_job_status::COMPLETED,
            archive_job_status::FAILURE,
            archive_job_status::DELETED,
        ];
        foreach ($statuses as $status) {
            $job = $this->generator()->create_archive_job([], $course, $cm);
            $job->set_status($status);
            $jobstatuses[$job->get_id()] = $status;
        }

        // Only jobs within the selected status category must be listed.
        $states = archive_job_status_filter::from($filter)->states();
        $expected = array_keys(array_filter($jobstatuses, fn ($s) => in_array($s, $states, true)));
        $this->assertNotEmpty($expected, 'Test setup should contain at least one job per status category');

        $ctx = \context_course::instance($course->id);
        $this->assertEqualsCanonicalizing($expected, $this->query_job_ids($ctx, ['status' => $filter]));
    }

    /**
     * Data provider for test_status_filter
     *
     * @return array[] Test cases, one per status filter
     */
    public static function status_filter_provider(): array {
        $cases = [];
        foreach (archive_job_status_filter::cases() as $filter) {
            $cases[$filter->value] = ['filter' => $filter->value];
        }

        return $cases;
    }

    /**
     * Tests that an invalid status filter is ignored
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_invalid_status_filter_is_ignored(): void {
        global $PAGE;
        $PAGE->set_url('/');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $job1 = $this->generator()->create_archive_job([], $course, $cm);
        $job2 = $this->generator()->create_archive_job([], $course, $cm);
        $job2->set_status(archive_job_status::COMPLETED);

        $this->assertEqualsCanonicalizing(
            [$job1->get_id(), $job2->get_id()],
            $this->query_job_ids(\context_course::instance($course->id), ['status' => 'bogus'])
        );
    }

    /**
     * Tests that jobs are filtered by the trigger they originate from
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_origin_filter(): void {
        global $PAGE;
        $PAGE->set_url('/');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $jobmanual = $this->generator()->create_archive_job(['trigger' => 'manual'], $course, $cm);
        $jobevent = $this->generator()->create_archive_job(['trigger' => 'event'], $course, $cm);
        $jobcron = $this->generator()->create_archive_job(['trigger' => 'cron'], $course, $cm);
        $ctx = \context_course::instance($course->id);

        $this->assertEquals([$jobmanual->get_id()], $this->query_job_ids($ctx, ['origin' => 'manual']));
        $this->assertEquals([$jobevent->get_id()], $this->query_job_ids($ctx, ['origin' => 'event']));
        $this->assertEquals([$jobcron->get_id()], $this->query_job_ids($ctx, ['origin' => 'cron']));
        $this->assertEqualsCanonicalizing(
            [$jobmanual->get_id(), $jobevent->get_id(), $jobcron->get_id()],
            $this->query_job_ids($ctx, ['origin' => 'nonexistent']),
            'Unknown origins must be ignored'
        );
    }

    /**
     * Tests that jobs are filtered by users whose data was archived
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_user_filter(): void {
        global $PAGE;
        $PAGE->set_url('/');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $usera = $this->generator()->create_user();
        $userb = $this->generator()->create_user();
        $unrelated = $this->generator()->create_user();
        $joba = $this->generator()->mock_archived_content($usera->id, $course, $cm);
        $jobb = $this->generator()->mock_archived_content($userb->id, $course, $cm);
        $jobempty = $this->generator()->create_archive_job([], $course, $cm);
        $ctx = \context_course::instance($course->id);
        $alljobs = [$joba->get_id(), $jobb->get_id(), $jobempty->get_id()];

        $this->assertEquals([$joba->get_id()], $this->query_job_ids($ctx, ['userid' => $usera->id]));
        $this->assertEquals([$jobb->get_id()], $this->query_job_ids($ctx, ['userid' => $userb->id]));
        $this->assertEqualsCanonicalizing(
            $alljobs,
            $this->query_job_ids($ctx, ['userid' => $unrelated->id]),
            'Users that are neither enrolled nor have archived data must be ignored'
        );
        $this->assertEqualsCanonicalizing(
            $alljobs,
            $this->query_job_ids($ctx, ['userid' => 999999]),
            'Nonexistent users must be ignored'
        );
    }

    /**
     * Tests the free-text search against file and activity names
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_search(): void {
        global $PAGE;
        $PAGE->set_url('/');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->generator()->create_course();
        $cm1 = $this->generator()->create_module('quiz', ['course' => $course->id, 'name' => 'Midterm Exam']);
        $cm2 = $this->generator()->create_module('quiz', ['course' => $course->id, 'name' => 'Final Quiz']);

        $jobcm1 = $this->generator()->create_archive_job([], $course, $cm1);
        $jobfile = $this->generator()->create_archive_job([], $course, $cm2);
        $this->generator()->create_file_handle(['jobid' => $jobfile->get_id(), 'filename' => 'archive-abc.tar.gz']);
        $jobdeletedfile = $this->generator()->create_archive_job([], $course, $cm2);
        $this->generator()->create_file_handle(['jobid' => $jobdeletedfile->get_id(), 'filename' => 'secret-xyz.tar.gz'])
            ->mark_as_deleted();
        $ctx = \context_course::instance($course->id);

        $this->assertEquals([$jobcm1->get_id()], $this->query_job_ids($ctx, ['search' => 'midterm']), 'Activity name');
        $this->assertEquals([$jobfile->get_id()], $this->query_job_ids($ctx, ['search' => 'ARCHIVE-ABC']), 'File name');
        $this->assertEmpty($this->query_job_ids($ctx, ['search' => 'secret-xyz']), 'Deleted files must not match');
        $this->assertEmpty($this->query_job_ids($ctx, ['search' => '%']), 'Wildcards must be escaped');
        $this->assertEmpty($this->query_job_ids($ctx, ['search' => '_']), 'Wildcards must be escaped');
        $this->assertEmpty($this->query_job_ids($ctx, ['search' => 'nothingmatches']), 'No match');
    }

    /**
     * Tests that multiple filters are combined
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_combined_filters(): void {
        global $PAGE;
        $PAGE->set_url('/');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $jobinprogress = $this->generator()->create_archive_job([], $course, $cm);
        $this->generator()->create_file_handle(['jobid' => $jobinprogress->get_id(), 'filename' => 'archive-abc.tar.gz']);
        $jobcompleted = $this->generator()->create_archive_job([], $course, $cm);
        $jobcompleted->set_status(archive_job_status::COMPLETED);
        $this->generator()->create_file_handle(['jobid' => $jobcompleted->get_id(), 'filename' => 'archive-abc.tar.gz']);
        $jobothercompleted = $this->generator()->create_archive_job([], $course, $cm);
        $jobothercompleted->set_status(archive_job_status::COMPLETED);
        $ctx = \context_course::instance($course->id);

        $this->assertEquals(
            [$jobcompleted->get_id()],
            $this->query_job_ids($ctx, ['search' => 'archive-abc', 'status' => archive_job_status_filter::COMPLETED->value])
        );
    }

    /**
     * Tests that the number of archived objects is counted across all tasks of a job
     *
     * @covers \local_archiving\output\job_overview_table
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_contentcount(): void {
        global $PAGE;
        $PAGE->set_url('/');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $user = $this->generator()->create_user();

        $job = $this->generator()->create_archive_job([], $course, $cm);
        $task1 = $this->generator()->create_activity_archiving_task([], $job);
        $task1->store_task_content_metadata([
            new task_content_metadata($task1->get_id(), $user->id, null, null, 'Content 1'),
            new task_content_metadata($task1->get_id(), $user->id, null, null, 'Content 2'),
        ]);
        $task2 = $this->generator()->create_activity_archiving_task([], $job);
        $task2->store_task_content_metadata([
            new task_content_metadata($task2->get_id(), $user->id, null, null, 'Content 3'),
        ]);
        $jobempty = $this->generator()->create_archive_job([], $course, $cm);

        $table = $this->create_table(\context_course::instance($course->id));
        $table->setup();
        $table->query_db(25, false);

        $this->assertEquals(3, $table->rawdata[$job->get_id()]->contentcount);
        $this->assertEquals(0, $table->rawdata[$jobempty->get_id()]->contentcount);
    }
}
