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
 * Tests for the get_attempts_metadata external service
 *
 * @package   archivingmod_quiz
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\external;

use archivingmod_quiz\type\webservice_status;


/**
 * Tests for the get_attempts_metadata external service
 */
final class get_attempts_metadata_test extends \advanced_testcase {
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
     * @param string $uuid Job UUID
     * @param int $taskid ID of the activity archiving task
     * @return array Valid request parameters
     */
    protected function generate_valid_request(string $uuid, int $taskid): array {
        return [
            'uuid' => $uuid,
            'taskid' => $taskid,
            'attemptids' => [1, 2, 3, 4, 5],
        ];
    }

    /**
     * Tests that the parameter spec is specified correctly and produces no exception.
     *
     * @covers \archivingmod_quiz\external\get_attempts_metadata::execute_parameters
     *
     * @return void
     */
    public function test_assure_execute_parameter_spec(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            get_attempts_metadata::execute_parameters(),
            'The execute_parameters() method should return an external_function_parameters.'
        );
    }

    /**
     * Tests that the return parameters are specified correctly and produce no exception.
     *
     * @covers \archivingmod_quiz\external\get_attempts_metadata::execute_returns
     *
     * @return void
     */
    public function test_assure_return_parameter_spec(): void {
        $this->assertInstanceOf(
            \core_external\external_description::class,
            get_attempts_metadata::execute_returns(),
            'The execute_returns() method should return an external_description.'
        );
    }

    /**
     * Tests that only web service tokens with access to a task can request
     * attempt metadata
     *
     * @covers \archivingmod_quiz\external\get_attempts_metadata::execute
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
        $r = $this->generate_valid_request('10000000-0000-0000-0000-000000000000', $mocks->task->get_id());

        // Check that correct wstoken allows access.
        $_GET['wstoken'] = $wstoken;
        $res = get_attempts_metadata::execute(
            $r['uuid'],
            $r['taskid'],
            $r['attemptids'],
        );
        $this->assertNotSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Valid wstoken was falsely rejected'
        );

        // Check that incorrect wstoken is rejected.
        $_GET['wstoken'] = 'TEST-WS-TOKEN-INVALID';
        $res = get_attempts_metadata::execute(
            $r['uuid'],
            $r['taskid'],
            $r['attemptids'],
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
     * @dataProvider parameter_validation_data_provider
     * @covers \archivingmod_quiz\external\get_attempts_metadata::execute
     * @covers \archivingmod_quiz\external\get_attempts_metadata::validate_parameters
     *
     * @param string $invalidparameterkey Key of the parameter to invalidate
     * @return void
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public function test_parameter_validation(string $invalidparameterkey): void {
        // Create mock quiz and archive job.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-2';
        $mocks = $this->getDataGenerator()->create_mock_task($wstoken);

        // Create a request.
        $r = $this->generate_valid_request('20000000-0000-0000-0000-0123456789ab', $mocks->task->get_id());
        $_GET['wstoken'] = $wstoken;

        // Execute the request.
        $res = get_attempts_metadata::execute(
            $r['uuid'],
            $invalidparameterkey == 'taskid' ? 0 : $r['taskid'],
            $invalidparameterkey == 'attemptids' ? [] : $r['attemptids'],
        );
        $this->assertNotSame(
            webservice_status::OK->name,
            $res['status'],
            'Invalid parameter was falsely accepted'
        );
    }

    /**
     * Data provider for test_parameter_validation
     *
     * @return array[] Test data
     */
    public static function parameter_validation_data_provider(): array {
        return [
            'Invalid taskid' => ['taskid'],
            'Invalid attemptids' => ['attemptids'],
        ];
    }

    /**
     * Test web service part of processing of a valid request
     *
     * @covers \archivingmod_quiz\external\get_attempts_metadata::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public function test_execute(): void {
        // Create mock quiz and archive job.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-1';
        $mocks = $this->getDataGenerator()->create_mock_task($wstoken);

        // Create a valid request.
        $r = $this->generate_valid_request('20000000-0000-0000-0000-000000000000', $mocks->task->get_id());
        $_GET['wstoken'] = $wstoken;

        // Execute the request.
        $res = get_attempts_metadata::execute(
            $r['uuid'],
            $r['taskid'],
            $r['attemptids'],
        );
        $this->assertSame(webservice_status::OK->name, $res['status'], 'The status should be OK.');
        $this->assertArrayHasKey('attempts', $res, 'The response should contain an attempts key.');
    }
}
