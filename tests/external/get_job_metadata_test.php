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

namespace local_archiving\external;

use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;
use local_archiving\archive_job;
use local_archiving\local\type\archive_job_status;
use local_archiving\local\type\db_table;

/**
 * Tests for the get_job_metadata external function.
 *
 * @package   local_archiving
 * @author    Björn Dittmann <bjoern.dittmann@haw-hamburg.de>
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_job_metadata_test extends \advanced_testcase {
    /**
     * Returns the data generator for the local_archiving plugin
     *
     * @return \local_archiving_generator
     */
    private function generator(): \local_archiving_generator {
        return parent::getDataGenerator()->get_plugin_generator('local_archiving');
    }

    /**
     * Ensures plugin configuration required by archive_job::create() is present
     * so that jobs can be created in the tests
     *
     * @return void
     */
    protected function ensure_config(): void {
        // Give the job timeout a sane, non-default value.
        if (get_config('local_archiving', 'job_timeout_min') === false) {
            set_config('job_timeout_min', 60, 'local_archiving');
        }
    }

    /**
     * Creates an archive job in the requested status, owned by the admin user
     *
     * @param archive_job_status $status Initial job status to set
     * @return archive_job
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function make_job(archive_job_status $status): archive_job {
        $this->setAdminUser();
        $job = $this->generator()->create_archive_job(['userid' => get_admin()->id]);
        $job->set_status($status);
        return $job;
    }

    /**
     * Calls execute() and cleans the result against the declared return structure
     *
     * @param int $jobid Job ID to request
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \required_capability_exception
     */
    private function call_webservice(int $jobid): array {
        return external_api::clean_returnvalue(
            get_job_metadata::execute_returns(),
            get_job_metadata::execute($jobid)
        );
    }

    /**
     * Tests that the parameter spec is specified correctly and produces no exception
     *
     * @covers \local_archiving\external\get_job_metadata::execute_parameters
     *
     * @return void
     */
    public function test_assure_execute_parameter_spec(): void {
        $this->assertInstanceOf(
            external_function_parameters::class,
            get_job_metadata::execute_parameters(),
            'The execute_parameters() method should return an external_function_parameters.'
        );
    }

    /**
     * Tests that the return spec is specified correctly and produces no exception
     *
     * @covers \local_archiving\external\get_job_metadata::execute_returns
     *
     * @return void
     */
    public function test_assure_return_parameter_spec(): void {
        $this->assertInstanceOf(
            external_description::class,
            get_job_metadata::execute_returns(),
            'The execute_returns() method should return an external_description.'
        );
    }

    /**
     * Tests that a non-existent (negative) job ID is handled gracefully as not found
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     */
    public function test_rejects_invalid_jobid(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $res = get_job_metadata::execute(-1);

        $this->assertSame(
            'E_JOB_NOT_FOUND',
            $res['status'],
            'A negative job id must return E_JOB_NOT_FOUND.'
        );
        $this->assertArrayNotHasKey('job', $res);
    }

    /**
     * Tests that a request for a non-existent job returns the not-found status
     * without a job object
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     */
    public function test_job_not_found(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $res = get_job_metadata::execute(123456789);

        $this->assertSame('E_JOB_NOT_FOUND', $res['status']);
        $this->assertArrayNotHasKey('job', $res, 'A not-found response must not contain a job object.');
    }

    /**
     * Tests that users enrolled in the course but without the view capability are rejected
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_capability_requirement(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $job = $this->make_job(archive_job_status::QUEUED);
        $courseid = $job->get_context()->get_course_context()->instanceid;

        // Students have course access but not the local/archiving:view capability.
        $course = get_course($courseid);
        $student = self::getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_job_metadata::execute($job->get_id());
    }

    /**
     * Tests that users who cannot access the course context are rejected
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_course_access_requirement(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $job = $this->make_job(archive_job_status::QUEUED);

        // A user who is not enrolled in the course must be rejected by require_login.
        $this->setUser(self::getDataGenerator()->create_user());

        $this->expectException(\core\exception\require_login_exception::class);
        get_job_metadata::execute($job->get_id());
    }

    /**
     * Tests that users holding the view capability can retrieve job metadata
     *
     * @dataProvider privileged_role_data_provider
     * @covers       \local_archiving\external\get_job_metadata::execute
     *
     * @param string $role Shortname of the role to enrol the user with
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_access_with_view_capability(string $role): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $job = $this->make_job(archive_job_status::QUEUED);
        $courseid = $job->get_context()->get_course_context()->instanceid;

        $course = get_course($courseid);
        $user = self::getDataGenerator()->create_and_enrol($course, $role);
        $this->setUser($user);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $this->assertSame((int) $job->get_id(), (int) $res['job']['id']);
    }

    /**
     * Data provider for test_access_with_view_capability
     *
     * @return array[]
     */
    public static function privileged_role_data_provider(): array {
        return [
            'Non-editing teacher' => ['role' => 'teacher'],
            'Editing teacher' => ['role' => 'editingteacher'],
            'Manager' => ['role' => 'manager'],
        ];
    }

    /**
     * Tests that the housekeeping step times out a still-active job that has
     * outlived its configured timeout while the client is polling it
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_execute_times_out_overdue_running_job(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        // Create a job that is still in an active state but was created long enough
        // ago to have exceeded the timeout window.
        $job = $this->make_job(archive_job_status::ACTIVITY_ARCHIVING);
        set_config('job_timeout_min', 1, 'local_archiving');
        $DB->set_field(db_table::JOB->value, 'timecreated', time() - (2 * MINSECS), ['id' => $job->get_id()]);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $jobdata = $res['job'];
        $this->assertTrue(
            $jobdata['complete'],
            'An overdue active job must be promoted to a final (timed out) state.'
        );
        $this->assertSame(
            (int) archive_job_status::TIMEOUT->value,
            (int) $jobdata['status'],
            'Housekeeping must set the job status to TIMEOUT.'
        );
        // The housekeeping change must also be persisted, not just reflected in this response.
        $this->assertSame(
            archive_job_status::TIMEOUT,
            archive_job::get_by_id($job->get_id())->get_status(),
            'The timeout status must be persisted to the database.'
        );
    }

    /**
     * Tests that the housekeeping step leaves a still-active job untouched when it
     * has not yet exceeded its timeout window
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_execute_leaves_fresh_running_job_untouched(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        // A freshly created job within the timeout window must keep its active status.
        $job = $this->make_job(archive_job_status::ACTIVITY_ARCHIVING);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $jobdata = $res['job'];
        $this->assertFalse($jobdata['complete'], 'A fresh running job must not be flagged as complete.');
        $this->assertSame(
            (int) archive_job_status::ACTIVITY_ARCHIVING->value,
            (int) $jobdata['status'],
            'A fresh running job must keep its active status and not be timed out.'
        );
    }

    /**
     * Tests that a queued job is reported as incomplete and without a progress value
     *
     * A queued job has not started running yet, so there is no meaningful progress
     * to report and the optional progress field must be absent.
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_execute_returns_queued_job(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();
        $job = $this->make_job(archive_job_status::QUEUED);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $jobdata = $res['job'];
        $this->assertFalse($jobdata['complete'], 'A queued job must not be flagged as complete.');
        $this->assertSame((int) archive_job_status::QUEUED->value, (int) $jobdata['status']);
        $this->assertArrayNotHasKey(
            'progress',
            $jobdata,
            'A queued job has not started running and must not report a progress value.'
        );
    }

    /**
     * Tests that a running job is reported as incomplete with partial progress
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_execute_returns_running_job(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();
        $job = $this->make_job(archive_job_status::ACTIVITY_ARCHIVING);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $jobdata = $res['job'];
        $this->assertFalse($jobdata['complete'], 'A running job must not be flagged as complete.');
        $this->assertSame(
            (int) archive_job_status::ACTIVITY_ARCHIVING->value,
            (int) $jobdata['status'],
            'A running job must report its active status.'
        );
        $this->assertArrayHasKey('progress', $jobdata, 'A running job must report a progress value.');
        $this->assertGreaterThan(
            0,
            (int) $jobdata['progress'],
            'A running job in progress must report more than 0% progress.'
        );
        $this->assertLessThan(
            100,
            (int) $jobdata['progress'],
            'A running job must not yet report 100% progress.'
        );
    }

    /**
     * Tests that a completed job is reported with the correct core fields and progress
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_execute_returns_completed_job(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();
        $job = $this->make_job(archive_job_status::COMPLETED);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $jobdata = $res['job'];
        $this->assertSame((int) $job->get_id(), (int) $jobdata['id']);
        $this->assertTrue($jobdata['complete'], 'A completed job must be flagged as complete.');
        $this->assertSame((int) archive_job_status::COMPLETED->value, (int) $jobdata['status']);
        $this->assertSame(100, (int) $jobdata['progress'], 'A completed job must report 100% progress.');
        $this->assertIsNumeric($jobdata['timecreated']);
        $this->assertIsNumeric($jobdata['timemodified']);
        $this->assertArrayHasKey('status_display_args', $jobdata);
        $this->assertNotSame('', $jobdata['status_display_args']['text']);
        $this->assertNotSame('', $jobdata['status_display_args']['color']);
        $this->assertNotSame('', $jobdata['status_display_args']['help']);
    }

    /**
     * Tests that a timed-out job is reported as complete without a progress value
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_execute_returns_timed_out_job(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();
        $job = $this->make_job(archive_job_status::TIMEOUT);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $jobdata = $res['job'];
        $this->assertTrue($jobdata['complete'], 'A timed-out job is in a final state and must be complete.');
        $this->assertSame((int) archive_job_status::TIMEOUT->value, (int) $jobdata['status']);
        // Timeouts have no meaningful progress, so the optional progress field is absent.
        $this->assertArrayNotHasKey('progress', $jobdata);
    }
}
