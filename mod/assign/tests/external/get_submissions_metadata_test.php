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
 * Tests for the get_submissions_metadata external service
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\external;

use archivingmod_assign\local\type\webservice_status;

/**
 * Tests for the get_submissions_metadata external service
 */
final class get_submissions_metadata_test extends \advanced_testcase {
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
     * Generates a set of valid parameters
     *
     * @param string $uuid Job UUID
     * @param int $taskid ID of the activity archiving task
     * @param array $submissionids Submission IDs
     * @return array Valid request parameters
     */
    protected function generate_valid_request(string $uuid, int $taskid, array $submissionids): array {
        return [
            'uuid' => $uuid,
            'taskid' => $taskid,
            'submissionids' => $submissionids,
        ];
    }

    /**
     * Tests that the parameter spec is specified correctly and produces no exception.
     *
     * @covers \archivingmod_assign\external\get_submissions_metadata::execute_parameters
     *
     * @return void
     */
    public function test_assure_execute_parameter_spec(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            get_submissions_metadata::execute_parameters(),
            'The execute_parameters() method should return an external_function_parameters.'
        );
    }

    /**
     * Tests that the return parameters are specified correctly and produce no exception.
     *
     * @covers \archivingmod_assign\external\get_submissions_metadata::execute_returns
     *
     * @return void
     */
    public function test_assure_return_parameter_spec(): void {
        $this->assertInstanceOf(
            \core_external\external_description::class,
            get_submissions_metadata::execute_returns(),
            'The execute_returns() method should return an external_description.'
        );
    }

    /**
     * Test wstoken validation
     *
     * @covers \archivingmod_assign\external\get_submissions_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_wstoken_access_check(): void {
        // Gain webservice permission and create mocks.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-VALID';
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);
        $uuid = '30000000-0000-0000-0000-0123456789ab';
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), [1, 2, 3, 4, 5]);

        // Check that correct wstoken allows access.
        $_GET['wstoken'] = $wstoken;
        $res = get_submissions_metadata::execute($r['uuid'], $r['taskid'], $r['submissionids']);
        $this->assertNotSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Valid wstoken was falsely rejected'
        );

        // Check that incorrect wstoken is rejected.
        $_GET['wstoken'] = 'TEST-WS-TOKEN-INVALID';
        $res = get_submissions_metadata::execute($r['uuid'], $r['taskid'], $r['submissionids']);
        $this->assertSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Invalid wstoken was falsely accepted'
        );
    }

    /**
     * Verifies webservice parameter validation
     *
     * @dataProvider parameter_validation_data_provider
     * @covers \archivingmod_assign\external\get_submissions_metadata::execute
     * @covers \archivingmod_assign\external\get_submissions_metadata::validate_parameters
     *
     * @param string $invalidparameterkey Key of the parameter to invalidate
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public function test_parameter_validation(string $invalidparameterkey): void {
        // Create mock assignment and activity archiving task.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-2';
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);

        // Create a request.
        $uuid = '20000000-0000-0000-0000-0123456789ab';
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), [1, 2, 3, 4, 5]);
        $_GET['wstoken'] = $wstoken;

        // Execute the request.
        if ($invalidparameterkey === 'uuid') {
            // A UUID containing HTML tags is detected by Moodle parameter validation so we expect an exception here.
            $this->expectException(\invalid_parameter_exception::class);
            $this->expectExceptionMessageMatches('/.*uuid.*/');
        }
        $res = get_submissions_metadata::execute(
            $invalidparameterkey === 'uuid' ? '<a href="localhost">not-a-uuid</a>' : $r['uuid'],
            $invalidparameterkey === 'taskid' ? 0 : $r['taskid'],
            $invalidparameterkey === 'submissionids' ? [] : $r['submissionids'],
        );
        $this->assertNotSame(
            webservice_status::OK->name,
            $res['status'],
            'Invalid parameter should not be accepted'
        );
    }

    /**
     * Data provider for test_parameter_validation
     *
     * @return array[] Test data
     */
    public static function parameter_validation_data_provider(): array {
        return [
            'Invalid uuid' => ['uuid'],
            'Invalid taskid' => ['taskid'],
            'Invalid submissionids' => ['submissionids'],
        ];
    }

    /**
     * Test web service part of processing of a valid request
     *
     * @covers \archivingmod_assign\external\get_submissions_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public function test_execute(): void {
        // Create mock assignment with a real submission and activity archiving task.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-1';
        $mocks = $this->getDataGenerator()->create_mock_task(populateassignment: true, wstoken: $wstoken);

        // Create a valid request.
        $uuid = '10000000-0000-0000-0000-0123456789ab';
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), [$mocks->submission->id]);
        $_GET['wstoken'] = $wstoken;

        // Execute the request.
        $res = get_submissions_metadata::execute($r['uuid'], $r['taskid'], $r['submissionids']);
        $this->assertSame(webservice_status::OK->name, $res['status'], 'The status should be OK.');
        $this->assertArrayHasKey('submissions', $res, 'The response should contain a submissions key.');
        $this->assertCount(1, $res['submissions'], 'The response should contain metadata for exactly one submission.');
        $this->assertSame(
            $mocks->submission->id,
            reset($res['submissions'])->submissionid,
            'The returned submission ID should match the requested submission.'
        );
    }
}
