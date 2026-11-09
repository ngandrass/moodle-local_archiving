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
use local_archiving\local\type\task_content_metadata;

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
    private function call_webservice(
        int $jobid,
        bool $includelogs = true,
        bool $includemetadata = true,
        bool $includefiles = true,
    ): array {
        return external_api::clean_returnvalue(
            get_job_metadata::execute_returns(),
            get_job_metadata::execute($jobid, $includelogs, $includemetadata, $includefiles)
        );
    }

    /**
     * Creates a completed archive job that has logs, metadata, archived
     * content and an artifact file attached
     *
     * @return archive_job
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function make_job_with_optional_data(): archive_job {
        $job = $this->make_job(archive_job_status::COMPLETED);
        $job->get_logger()->info('Some log entry');
        $job->set_metadata_entry('trigger', 'manual');

        $task = $this->generator()->create_activity_archiving_task([], $job);
        $task->store_task_content_metadata([
            new task_content_metadata($task->get_id(), get_admin()->id, null, null, 'Content 1'),
        ]);
        $this->generator()->create_file_handle([
            'jobid' => $job->get_id(),
            'filename' => 'archive-42.zip',
        ]);

        return $job;
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
        $this->assertArrayNotHasKey(
            'download',
            $res['job']['action_urls'],
            'A queued job must not expose a download URL.'
        );
        $this->assertNotSame('', $jobdata['cm_name']);
        $this->assertNotSame('', $jobdata['action_urls']['cm']);
        $this->assertNotSame('', $jobdata['action_urls']['job']);
    }

    /**
     * Tests that the activity name is returned unescaped, since it is escaped
     * by the Mustache template that renders it on the client side
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
    public function test_execute_returns_unescaped_cm_name(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'name' => 'Q & A "Session"']);
        $job = $this->generator()->create_archive_job(['userid' => get_admin()->id], $course, $cm);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $this->assertSame('Q & A "Session"', $res['job']['cm_name'], 'Activity name must not be HTML-escaped by the webservice.');
    }

    /**
     * Tests that the returned modification time takes the job's activity
     * archiving tasks into account
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
    public function test_execute_returns_timemodified_of_newest_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_config();
        $job = $this->make_job(archive_job_status::ACTIVITY_ARCHIVING);
        $task = $this->generator()->create_activity_archiving_task([], $job);

        $jobtime = time() - HOURSECS;
        $tasktime = time() - MINSECS;
        $DB->set_field(db_table::JOB->value, 'timemodified', $jobtime, ['id' => $job->get_id()]);
        $DB->set_field(db_table::ACTIVITY_TASK->value, 'timemodified', $tasktime, ['id' => $task->get_id()]);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $this->assertSame($tasktime, (int) $res['job']['timemodified'], 'The newest task modification time should be returned.');
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
        $this->assertArrayNotHasKey(
            'download',
            $res['job']['action_urls'],
            'A running job must not expose a download URL.'
        );
        $this->assertNotSame('', $jobdata['cm_name']);
        $this->assertNotSame('', $jobdata['action_urls']['cm']);
        $this->assertNotSame('', $jobdata['action_urls']['job']);
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
        $this->assertArrayHasKey('action_urls', $jobdata);
        $this->assertArrayNotHasKey(
            'download',
            $jobdata['action_urls'],
            'A completed job without artifacts must not expose a download URL.'
        );
        $this->assertNotSame('', $jobdata['cm_name']);
        $this->assertNotSame('', $jobdata['action_urls']['cm']);
        $this->assertNotSame('', $jobdata['action_urls']['job']);
        $this->assertSame([], $jobdata['files'], 'A job without files must have an empty files list.');
        $this->assertSame(0, $jobdata['content_count'], 'A job without archived content must have a content count of zero.');
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
        $this->assertArrayNotHasKey(
            'download',
            $res['job']['action_urls'],
            'A timed-out job must not expose a download URL.'
        );
        $this->assertNotSame('', $jobdata['cm_name']);
        $this->assertNotSame('', $jobdata['action_urls']['cm']);
        $this->assertNotSame('', $jobdata['action_urls']['job']);
    }

    /**
     * Tests that a job with no log entries does not return a logs field
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
    public function test_execute_returns_job_without_logs(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        // Create a job and leave it in its initial UNINITIALIZED state (no logs).
        $job = $this->generator()->create_archive_job(['userid' => get_admin()->id]);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $this->assertArrayNotHasKey(
            'logs',
            $res['job'],
            'A job with no log entries must not include a logs field.'
        );
    }

    /**
     * Tests that job log entries are returned intact, including angle brackets
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
    public function test_execute_returns_job_with_logs(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $job = $this->make_job(archive_job_status::ACTIVITY_ARCHIVING);

        // Add a controlled log entry that contains angle brackets.
        $job->get_logger()->info('Processing <file.xml> for user [id=42]');
        $job->get_logger()->info('Job status: Completed (100)');

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $this->assertArrayHasKey(
            'logs',
            $res['job'],
            'A job with log entries must include a logs field.'
        );
        $logstext = $res['job']['logs'];

        // The log message must be present and its angle brackets must survive
        // PARAM_RAW cleaning (PARAM_TEXT would strip them via strip_tags).
        $this->assertStringContainsString(
            'Processing <file.xml> for user [id=42]',
            $logstext,
            'Log entries must be returned intact, including angle brackets.'
        );
        // Multiple entries are separated by \r\n.
        $this->assertStringContainsString(
            "\r\n",
            $logstext,
            'Log entries must be newline-separated.'
        );
    }

    /**
     * Tests that a completed job with content and artifacts reports the
     * activity column data.
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
    public function test_execute_returns_completed_job_with_content_and_files(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $job = $this->make_job(archive_job_status::COMPLETED);
        $userid = get_admin()->id;

        // Archive two objects across two tasks and one artifact file.
        $task1 = $this->generator()->create_activity_archiving_task([], $job);
        $task1->store_task_content_metadata([
            new task_content_metadata($task1->get_id(), $userid, null, null, 'Content 1'),
            new task_content_metadata($task1->get_id(), $userid, null, null, 'Content 2'),
        ]);
        $task2 = $this->generator()->create_activity_archiving_task([], $job);
        $task2->store_task_content_metadata([
            new task_content_metadata($task2->get_id(), $userid, null, null, 'Content 3'),
        ]);
        $this->generator()->create_file_handle([
            'jobid' => $job->get_id(),
            'filename' => 'archive-42.zip',
        ]);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $jobdata = $res['job'];
        $this->assertNotSame('', $jobdata['cm_name']);
        $this->assertNotSame('', $jobdata['action_urls']['cm']);
        $this->assertNotSame('', $jobdata['action_urls']['job']);
        $this->assertSame(
            3,
            (int) $jobdata['content_count'],
            'The content count must span all tasks of the job.'
        );
        $this->assertSame(['archive-42.zip'], $jobdata['files']);

        // With a final status and an artifact, the download action is available.
        $this->assertArrayHasKey('download', $jobdata['action_urls']);
    }

    /**
     * Tests that deleted artifact files are excluded from the reported file
     * list.
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
    public function test_execute_excludes_deleted_files(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $job = $this->make_job(archive_job_status::COMPLETED);
        $this->generator()->create_file_handle([
            'jobid' => $job->get_id(),
            'filename' => 'kept.zip',
        ]);
        $deleted = $this->generator()->create_file_handle([
            'jobid' => $job->get_id(),
            'filename' => 'removed.zip',
        ]);
        $deleted->mark_as_deleted();

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $this->assertSame(
            ['kept.zip'],
            $res['job']['files'],
            'Deleted files must be excluded from the reported file list.'
        );
    }

    /**
     * Tests that a job without metadata entries returns an empty metadata array
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
    public function test_execute_returns_job_without_metadata(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $job = $this->make_job(archive_job_status::COMPLETED);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $this->assertIsArray($res['job']['metadata'], 'The metadata field must always be present as an array.');
        $this->assertSame([], $res['job']['metadata'], 'A job without metadata entries must return an empty array.');
    }

    /**
     * Tests that stored metadata entries are returned with correct key, humankey,
     * and value.
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
    public function test_execute_returns_job_with_metadata(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $job = $this->make_job(archive_job_status::COMPLETED);
        $job->set_metadata_entry('trigger', 'manual');
        $job->set_metadata_entry('num_attempts', 3);

        $res = $this->call_webservice($job->get_id());

        $this->assertSame('OK', $res['status']);
        $metadata = $res['job']['metadata'];
        $this->assertCount(2, $metadata, 'The metadata array must contain one entry per stored key.');

        // Build a key-indexed map for easier assertions.
        $bymap = array_column($metadata, null, 'key');

        // Trigger entry.
        $this->assertArrayHasKey('trigger', $bymap, 'The trigger metadata entry must be present.');
        $this->assertSame('Trigger', $bymap['trigger']['humankey'], 'Human-readable key must come from the lang string.');
        $this->assertSame('manual', $bymap['trigger']['value'], 'The stored value must be returned as-is.');

        // Num attempts entry (integer value cast to string by PARAM_TEXT).
        $this->assertArrayHasKey('num_attempts', $bymap, 'The num_attempts metadata entry must be present.');
        $this->assertSame(
            'Number of attempts',
            $bymap['num_attempts']['humankey'],
            'Human-readable key must match the lang string.',
        );
        $this->assertSame('3', $bymap['num_attempts']['value'], 'Integer values must be returned as strings.');
    }

    /**
     * Tests that optional data is omitted if not explicitly requested, while
     * all always-present fields are still returned
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
    public function test_execute_omits_optional_data_by_default(): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $job = $this->make_job_with_optional_data();

        // Call without any include flags to test the parameter defaults.
        $res = external_api::clean_returnvalue(
            get_job_metadata::execute_returns(),
            get_job_metadata::execute($job->get_id())
        );

        $this->assertSame('OK', $res['status']);
        $jobdata = $res['job'];

        // Optional data must be absent.
        foreach (['logs', 'metadata', 'files', 'content_count'] as $key) {
            $this->assertArrayNotHasKey($key, $jobdata, "Field '{$key}' must not be present unless requested.");
        }

        // Always-present data must be returned.
        $this->assertTrue($jobdata['complete']);
        $this->assertSame(100, (int) $jobdata['progress']);
        $this->assertGreaterThan(0, (int) $jobdata['timemodified']);
        $this->assertNotSame('', $jobdata['cm_name']);
        $this->assertNotSame('', $jobdata['action_urls']['cm']);
        $this->assertNotSame('', $jobdata['action_urls']['job']);
        $this->assertArrayHasKey(
            'download',
            $jobdata['action_urls'],
            'The download URL must be present for final jobs with artifacts, even if files are not requested.'
        );
    }

    /**
     * Tests that each include flag only adds its own group of optional data
     *
     * @covers \local_archiving\external\get_job_metadata::execute
     * @dataProvider optional_data_flags_provider
     *
     * @param bool $includelogs Whether to request logs
     * @param bool $includemetadata Whether to request metadata
     * @param bool $includefiles Whether to request files and content count
     * @param array $expectedkeys Optional keys that are expected to be present
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_execute_includes_only_requested_optional_data(
        bool $includelogs,
        bool $includemetadata,
        bool $includefiles,
        array $expectedkeys,
    ): void {
        $this->resetAfterTest();
        $this->ensure_config();
        $this->setAdminUser();

        $job = $this->make_job_with_optional_data();

        $res = $this->call_webservice($job->get_id(), $includelogs, $includemetadata, $includefiles);

        $this->assertSame('OK', $res['status']);
        foreach (['logs', 'metadata', 'files', 'content_count'] as $key) {
            if (in_array($key, $expectedkeys)) {
                $this->assertArrayHasKey($key, $res['job'], "Requested field '{$key}' must be present.");
            } else {
                $this->assertArrayNotHasKey($key, $res['job'], "Unrequested field '{$key}' must not be present.");
            }
        }
    }

    /**
     * Data provider for test_execute_includes_only_requested_optional_data
     *
     * @return array[] Test cases
     */
    public static function optional_data_flags_provider(): array {
        return [
            'Logs only' => [true, false, false, ['logs']],
            'Metadata only' => [false, true, false, ['metadata']],
            'Files only' => [false, false, true, ['files', 'content_count']],
            'Everything' => [true, true, true, ['logs', 'metadata', 'files', 'content_count']],
        ];
    }
}
