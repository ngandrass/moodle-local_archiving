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
}
