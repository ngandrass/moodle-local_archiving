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

namespace local_archiving\form;


/**
 * Tests for the file_delete_form class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for the file_delete_form class
 */
final class file_delete_form_test extends \advanced_testcase {
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
     * Tests instantiating the form with valid parameters and checks that the definition works as expected.
     *
     * @covers \local_archiving\form\file_delete_form
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_valid_definition(): void {
        global $PAGE;
        $PAGE->set_url('/');

        // Prepare a job with a linked file.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $filehandle = $this->generator()->create_file_handle(['jobid' => $job->get_id()]);
        $wantsurl = 'http://localhost/foo';

        // Create the form and check that the definition works as expected.
        $form = new file_delete_form(
            contextid: $job->get_context()->id,
            filehandleid: $filehandle->id,
            wantsurl: $wantsurl
        );

        $html = $form->render();
        $this->assertStringContainsString(
            $filehandle->filename,
            $html,
            'The form must contain the filename of the file to be deleted.'
        );
        $this->assertStringContainsString(
            'filedelete',
            $html,
            'The form must contain a hidden input with the value "filedelete".'
        );
        $this->assertStringContainsString(
            $wantsurl,
            $html,
            'The form must contain a hidden input with the return URL.'
        );
    }

    /**
     * Tests that instantiating the form with a context that does not match the job of the filehandle
     * results in an exception.
     *
     * @covers \local_archiving\form\file_delete_form
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_job_context_mismatch(): void {
        global $PAGE;
        $PAGE->set_url('/');

        // Create two jobs of only one has a linked file.
        $this->resetAfterTest();
        $job1 = $this->generator()->create_archive_job();
        $job2 = $this->generator()->create_archive_job();
        $filehandle = $this->generator()->create_file_handle(['jobid' => $job1->get_id()]);

        // Try to display the deletion form inside the context of the other job.
        $this->expectException(\moodle_exception::class);
        new file_delete_form(
            contextid: $job2->get_context()->id,
            filehandleid: $filehandle->id,
            wantsurl: 'http://localhost/foo'
        );
    }
}
