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

use local_archiving\type\archive_job_status;

/**
 * Tests for the job_overview_table class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
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
}
