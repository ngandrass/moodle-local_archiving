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
 * Tests for the process_uploaded_artifact external service
 *
 * @package   archivingmod_quiz
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\external;

use archivingmod_quiz\type\webservice_status;
use local_archiving\activity_archiving_task;
use local_archiving\type\activity_archiving_task_status;
use local_archiving\type\filearea;

/**
 * Tests for the process_uploaded_artifact external service
 */
final class process_uploaded_artifact_test extends \advanced_testcase {
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
     * Generates a set of valid parameters
     *
     * @param string $uuid UUID assigned to this task by the worker service
     * @param activity_archiving_task $task The task to generate the request for
     * @return array Valid request parameters
     */
    protected function generate_valid_request(string $uuid, activity_archiving_task $task): array {
        return [
            'uuid' => $uuid,
            'taskid' => $task->get_id(),
            'artifact_component' => 'user',
            'artifact_contextid' => $task->get_context()->id,
            'artifact_userid' => get_admin()->id,
            'artifact_filearea' => filearea::DRAFT->value,
            'artifact_filename' => 'artifact.zip',
            'artifact_filepath' => '/',
            'artifact_itemid' => 1,
            'artifact_sha256sum' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
        ];
    }

    /**
     * Tests that the parameter spec is specified correctly and produces no exception.
     *
     * @covers \archivingmod_quiz\external\process_uploaded_artifact::execute_parameters
     *
     * @return void
     */
    public function test_assure_execute_parameter_spec(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            process_uploaded_artifact::execute_parameters(),
            'The execute_parameters() method should return an external_function_parameters.'
        );
    }

    /**
     * Tests that the return parameters are specified correctly and produce no exception.
     *
     * @covers \archivingmod_quiz\external\process_uploaded_artifact::execute_returns
     *
     * @return void
     */
    public function test_assure_return_parameter_spec(): void {
        $this->assertInstanceOf(
            \core_external\external_description::class,
            process_uploaded_artifact::execute_returns(),
            'The execute_returns() method should return an external_description.'
        );
    }

    /**
     * Tests that only web service tokens with access to a task can request
     * attempt metadata
     *
     * @covers \archivingmod_quiz\external\process_uploaded_artifact::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_wstoken_access_check(): void {
        // Create job.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-VALID';
        $mocks = $this->getDataGenerator()->create_mock_task($wstoken);
        $r = $this->generate_valid_request('10000000-0000-0000-0000-000000000000', $mocks->task);

        // Check that correct wstoken allows access.
        $_GET['wstoken'] = $wstoken;
        $res = process_uploaded_artifact::execute(
            $r['uuid'],
            $r['taskid'],
            $r['artifact_component'],
            $r['artifact_contextid'],
            $r['artifact_userid'],
            $r['artifact_filearea'],
            $r['artifact_filename'],
            $r['artifact_filepath'],
            $r['artifact_itemid'],
            $r['artifact_sha256sum']
        );
        $this->assertNotSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Valid wstoken was falsely rejected'
        );

        // Check that incorrect wstoken is rejected.
        $_GET['wstoken'] = 'TEST-WS-TOKEN-INVALID';
        $res = process_uploaded_artifact::execute(
            $r['uuid'],
            $r['taskid'],
            $r['artifact_component'],
            $r['artifact_contextid'],
            $r['artifact_userid'],
            $r['artifact_filearea'],
            $r['artifact_filename'],
            $r['artifact_filepath'],
            $r['artifact_itemid'],
            $r['artifact_sha256sum']
        );
        $this->assertSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Invalid wstoken was falsely accepted'
        );
    }

    /**
     * Verifies webservice parameter validation
     *
     * @dataProvider parameter_data_provider
     * @covers       \archivingmod_quiz\external\process_uploaded_artifact::execute
     * @covers       \archivingmod_quiz\external\process_uploaded_artifact::validate_parameters
     *
     * @param string|null $uuid Job UUID
     * @param int|null $taskid Task ID
     * @param string|null $artifactcomponent Component name
     * @param int|null $artifactcontextid Context ID
     * @param int|null $artifactuserid User ID
     * @param string|null $artifactfilearea File area name
     * @param string|null $artifactfilename File name
     * @param string|null $artifactfilepath File path
     * @param int|null $artifactitemid Item ID
     * @param string|null $artifactsha256sum SHA256 checksum
     * @param bool $shouldfail Whether a failure is expected
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     */
    public function test_parameter_validation(
        ?string $uuid,
        ?int $taskid,
        ?string $artifactcomponent,
        ?int $artifactcontextid,
        ?int $artifactuserid,
        ?string $artifactfilearea,
        ?string $artifactfilename,
        ?string $artifactfilepath,
        ?int $artifactitemid,
        ?string $artifactsha256sum,
        bool $shouldfail
    ): void {
        // Create mock quiz.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task();
        $base = $this->generate_valid_request('20000000-0000-0000-0000-000000000000', $mocks->task);

        if ($shouldfail) {
            $this->expectException(\invalid_parameter_exception::class);
        }

        process_uploaded_artifact::execute(
            $uuid === null ? $base['uuid'] : $uuid,
            $taskid === null ? $base['taskid'] : $uuid,
            $artifactcomponent === null ? $base['artifact_component'] : $artifactcomponent,
            $artifactcontextid === null ? $base['artifact_contextid'] : $artifactcontextid,
            $artifactuserid === null ? $base['artifact_userid'] : $artifactuserid,
            $artifactfilearea === null ? $base['artifact_filearea'] : $artifactfilearea,
            $artifactfilename === null ? $base['artifact_filename'] : $artifactfilename,
            $artifactfilepath === null ? $base['artifact_filepath'] : $artifactfilepath,
            $artifactitemid === null ? $base['artifact_itemid'] : $artifactitemid,
            $artifactsha256sum === null ? $base['artifact_sha256sum'] : $artifactsha256sum
        );
    }

    /**
     * Data provider for test_parameter_validation
     *
     * @return array[] Test data
     */
    public static function parameter_data_provider(): array {
        // Create base data (no modification).
        $base = [
            "uuid" => null,
            "taskid" => null,
            "artifactcomponent" => null,
            "artifactcontextid" => null,
            "artifactuserid" => null,
            "artifactfilearea" => null,
            "artifactfilename" => null,
            "artifactfilepath" => null,
            "artifactitemid" => null,
            "artifactsha256sum" => null,
        ];

        // Define test datasets.
        return [
            'Valid' => array_merge($base, [
                'shouldfail' => false,
            ]),
            'Invalid uuid' => array_merge($base, [
                'uuid' => '<a href="localhost">Foo</a>',
                'shouldfail' => true,
            ]),
            'Invalid artifact_component' => array_merge($base, [
                'artifactcomponent' => '<a href="localhost">Foo</a>',
                'shouldfail' => true,
            ]),
            'Invalid artifactfilearea' => array_merge($base, [
                'artifactfilearea' => '<a href="localhost">Foo</a>',
                'shouldfail' => true,
            ]),
            'Invalid artifactfilename' => array_merge($base, [
                'artifactfilename' => '<a href="localhost">Foo</a>',
                'shouldfail' => true,
            ]),
            'Invalid artifactfilepath' => array_merge($base, [
                'artifactfilepath' => '<a href="localhost">Foo</a>',
                'shouldfail' => true,
            ]),
            'Invalid artifactsha256sum' => array_merge($base, [
                'artifactsha256sum' => '<a href="localhost">Foo</a>',
                'shouldfail' => true,
            ]),
        ];
    }

    /**
     * Tests that requests for non-existing tasks are rejected
     *
     * @covers \archivingmod_quiz\external\process_uploaded_artifact::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_rejection_of_artifacts_for_invalid_task(): void {
        // Create task.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task('TEST-WS-TOKEN');

        // Execute test call.
        $r = $this->generate_valid_request('30000000-0000-0000-0000-000000000000', $mocks->task);
        $_GET['wstoken'] = 'TEST-WS-TOKEN';
        $res = process_uploaded_artifact::execute(
            $r['uuid'],
            0,
            $r['artifact_component'],
            $r['artifact_contextid'],
            $r['artifact_userid'],
            $r['artifact_filearea'],
            $r['artifact_filename'],
            $r['artifact_filepath'],
            $r['artifact_itemid'],
            $r['artifact_sha256sum']
        );
        $this->assertSame(
            webservice_status::E_TASK_NOT_FOUND->name,
            $res['status'],
            'Invalid task ID was falsely accepted'
        );
    }

    /**
     * Test that completed tasks reject further artifact uploads
     *
     * @covers \archivingmod_quiz\external\process_uploaded_artifact::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_rejection_of_artifacts_for_complete_tasks(): void {
        // Create task.
        $this->resetAfterTest();
        $wstoken = 'TEST-WS-TOKEN-42';
        $mocks = $this->getDataGenerator()->create_mock_task($wstoken);
        $mocks->task->set_status(activity_archiving_task_status::FINISHED);

        // Execute test call.
        $_GET['wstoken'] = $wstoken;
        $r = $this->generate_valid_request('30000000-0000-0000-0000-000000000000', $mocks->task);
        $res = process_uploaded_artifact::execute(
            $r['uuid'],
            $r['taskid'],
            $r['artifact_component'],
            $r['artifact_contextid'],
            $r['artifact_userid'],
            $r['artifact_filearea'],
            $r['artifact_filename'],
            $r['artifact_filepath'],
            $r['artifact_itemid'],
            $r['artifact_sha256sum']
        );
        $this->assertSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Completed task accepted another artifact upload'
        );
    }

    /**
     * Test that missing files are reported correctly
     *
     * @covers \archivingmod_quiz\external\process_uploaded_artifact::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_invalid_file_metadata(): void {
        // Create task.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task('TEST-WS-TOKEN');

        // Execute test call.
        $r = $this->generate_valid_request('42000000-0000-0000-0000-000000000000', $mocks->task);
        $_GET['wstoken'] = 'TEST-WS-TOKEN';
        $res = process_uploaded_artifact::execute(
            $r['uuid'],
            $r['taskid'],
            $r['artifact_component'],
            $r['artifact_contextid'],
            $r['artifact_userid'],
            $r['artifact_filearea'],
            $r['artifact_filename'],
            $r['artifact_filepath'],
            $r['artifact_itemid'],
            $r['artifact_sha256sum']
        );
        $this->assertSame(
            webservice_status::E_FILE_NOT_FOUND->name,
            $res['status'],
            'Invalid file was falesly found'
        );
    }

    /**
     * Tests rejection of artifacts with mismatching checksums
     *
     * @covers \archivingmod_quiz\external\process_uploaded_artifact::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \stored_file_creation_exception
     */
    public function test_rejection_of_artifacts_with_checksum_mismatch(): void {
        // Create job and draft artifact.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task('TEST-WS-TOKEN');
        $artifact = $this->getDataGenerator()->create_draft_file('testartifact.tar.gz');

        // Execute test call.
        $r = $this->generate_valid_request('10000000-1337-0000-0000-000000000000', $mocks->task);
        $_GET['wstoken'] = 'TEST-WS-TOKEN';
        $res = process_uploaded_artifact::execute(
            $r['uuid'],
            $r['taskid'],
            $artifact->get_component(),
            $artifact->get_contextid(),
            (int) $artifact->get_userid(), // Int cast is required since Moodle likes to return strings here...
            $artifact->get_filearea(),
            $artifact->get_filename(),
            $artifact->get_filepath(),
            $artifact->get_itemid(),
            '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'
        );
        $this->assertSame(
            webservice_status::E_CHECKSUM_MISMATCH->name,
            $res['status'],
            'Artifact with mismatching checksum was falsely accepted'
        );
    }
}
