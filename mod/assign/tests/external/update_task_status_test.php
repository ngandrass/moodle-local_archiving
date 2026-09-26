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
 * Tests for the update_task_status external service
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\external;

use archivingmod_assign\local\type\webservice_status;
use local_archiving\local\type\activity_archiving_task_status;
use local_archiving\local\type\db_table;


/**
 * Tests for the update_task_status external service
 */
final class update_task_status_test extends \advanced_testcase {
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
     * @param activity_archiving_task_status $status Desired status to set for the task
     * @param int $progress Desired progress to set for the task
     * @return array Valid request parameters
     */
    protected function generate_valid_request(
        string $uuid,
        int $taskid,
        activity_archiving_task_status $status = activity_archiving_task_status::UNINITIALIZED,
        int $progress = 0
    ): array {
        return [
            'uuid' => $uuid,
            'taskid' => $taskid,
            'status' => $status->value,
            'progress' => $progress,
        ];
    }

    /**
     * Tests that the parameter spec is specified correctly and produces no exception.
     *
     * @covers \archivingmod_assign\external\update_task_status::execute_parameters
     *
     * @return void
     */
    public function test_assure_execute_parameter_spec(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            update_task_status::execute_parameters(),
            'The execute_parameters() method should return an external_function_parameters.'
        );
    }

    /**
     * Tests that the return parameters are specified correctly and produce no exception.
     *
     * @covers \archivingmod_assign\external\update_task_status::execute_returns
     *
     * @return void
     */
    public function test_assure_return_parameter_spec(): void {
        $this->assertInstanceOf(
            \core_external\external_description::class,
            update_task_status::execute_returns(),
            'The execute_returns() method should return an external_description.'
        );
    }

    /**
     * Tests that only web service tokens with access to a task can update its status
     *
     * @covers \archivingmod_assign\external\update_task_status::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_wstoken_access_check(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-VALID';
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);
        $r = $this->generate_valid_request('10000000-0000-0000-0000-affeaffe0000', $mocks->task->get_id());

        // Check that correct wstoken allows access.
        $_GET['wstoken'] = $wstoken;
        $res = update_task_status::execute(
            $r['uuid'],
            $r['taskid'],
            $r['status'],
            $r['progress'],
        );
        $this->assertNotSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Valid wstoken was falsely rejected'
        );

        // Check that incorrect wstoken is rejected.
        $_GET['wstoken'] = 'TEST-WS-TOKEN-INVALID';
        $res = update_task_status::execute(
            $r['uuid'],
            $r['taskid'],
            $r['status'],
            $r['progress'],
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
     * @covers \archivingmod_assign\external\update_task_status::execute
     * @covers \archivingmod_assign\external\update_task_status::validate_parameters
     *
     * @param string $invalidparameterkey Key of the parameter to invalidate
     * @return void
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public function test_parameter_validation(string $invalidparameterkey): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-2';
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);

        $r = $this->generate_valid_request('20000000-0000-0000-0000-0123456789ab', $mocks->task->get_id());
        $_GET['wstoken'] = $wstoken;

        $res = update_task_status::execute(
            $r['uuid'],
            $invalidparameterkey == 'taskid' ? 0 : $r['taskid'],
            $invalidparameterkey == 'status' ? 9999 : $r['status'],
            $invalidparameterkey == 'progress' ? 101 : $r['progress']
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
            'Invalid status' => ['status'],
            'Invalid progress' => ['progress'],
        ];
    }

    /**
     * Tests update of task status.
     *
     * @dataProvider update_task_status_data_provider
     * @covers \archivingmod_assign\external\update_task_status::execute
     *
     * @param activity_archiving_task_status $origin Original task status to update from
     * @param activity_archiving_task_status $target Target task status to update to
     * @param webservice_status $expected Expected webservice response
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \invalid_parameter_exception
     */
    public function test_update_task_status(
        activity_archiving_task_status $origin,
        activity_archiving_task_status $target,
        webservice_status $expected
    ): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-3';
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);
        $mocks->task->set_status($origin);

        $r = $this->generate_valid_request('30000000-0000-0000-0000-0123456789ab', $mocks->task->get_id(), $target);
        $_GET['wstoken'] = $wstoken;

        $this->expectOutputRegex('/.*/');
        $res = update_task_status::execute(
            $r['uuid'],
            $r['taskid'],
            $r['status'],
            $r['progress'],
        );

        $this->assertSame(
            $expected->name,
            $res['status'],
            "Task status update from {$origin->name} to {$target->name} returned unexpected status"
        );
    }

    /**
     * Data provider for test_update_task_status
     *
     * @return array[] Test data
     */
    public static function update_task_status_data_provider(): array {
        return [
            'Task Status: UNINITIALIZED -> CREATED' => [
                activity_archiving_task_status::UNINITIALIZED,
                activity_archiving_task_status::CREATED,
                webservice_status::OK,
            ],
            'Task Status: CREATED -> AWAITING_PROCESSING' => [
                activity_archiving_task_status::CREATED,
                activity_archiving_task_status::AWAITING_PROCESSING,
                webservice_status::OK,
            ],
            'Task Status: AWAITING_PROCESSING -> RUNNING' => [
                activity_archiving_task_status::AWAITING_PROCESSING,
                activity_archiving_task_status::RUNNING,
                webservice_status::OK,
            ],
            'Task Status: RUNNING -> FINALIZING' => [
                activity_archiving_task_status::RUNNING,
                activity_archiving_task_status::FINALIZING,
                webservice_status::OK,
            ],
            'Task Status: FINALIZING -> FINISHED' => [
                activity_archiving_task_status::FINALIZING,
                activity_archiving_task_status::FINISHED,
                webservice_status::OK,
            ],
            'Task Status: AWAITING_PROCESSING -> CANCELED' => [
                activity_archiving_task_status::AWAITING_PROCESSING,
                activity_archiving_task_status::CANCELED,
                webservice_status::OK,
            ],
            'Task Status: RUNNING -> TIMEOUT' => [
                activity_archiving_task_status::RUNNING,
                activity_archiving_task_status::TIMEOUT,
                webservice_status::OK,
            ],
            'Task Status: UNINITIALIZED -> UNKNOWN' => [
                activity_archiving_task_status::UNINITIALIZED,
                activity_archiving_task_status::UNKNOWN,
                webservice_status::OK,
            ],
            'Task Status: FINISHED -> TIMEOUT' => [
                activity_archiving_task_status::FINISHED,
                activity_archiving_task_status::TIMEOUT,
                webservice_status::E_ACCESS_DENIED,
            ],
            'Task Status: CANCELED -> RUNNING' => [
                activity_archiving_task_status::CANCELED,
                activity_archiving_task_status::RUNNING,
                webservice_status::E_ACCESS_DENIED,
            ],
            'Task Status: TIMEOUT -> RUNNING' => [
                activity_archiving_task_status::TIMEOUT,
                activity_archiving_task_status::RUNNING,
                webservice_status::E_ACCESS_DENIED,
            ],
        ];
    }

    /**
     * Tests updating the task progress.
     *
     * @dataProvider update_task_status_progress_data_provider
     * @covers       \archivingmod_assign\external\update_task_status::execute
     *
     * @param int $progress Progress value to set
     * @param bool $isvalid True if the progress value is valid
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_update_task_status_progress(int $progress, bool $isvalid): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-4';
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);
        $mocks->task->set_status(activity_archiving_task_status::RUNNING);

        $r = $this->generate_valid_request(
            '40000000-0000-0000-0000-0123456789ab',
            $mocks->task->get_id(),
            activity_archiving_task_status::RUNNING,
            $progress
        );
        $_GET['wstoken'] = $wstoken;
        $this->expectOutputRegex('/.*/');

        $res = update_task_status::execute(
            $r['uuid'],
            $r['taskid'],
            $r['status'],
            $r['progress'],
        );
        $this->assertSame(
            $isvalid ? webservice_status::OK->name : webservice_status::E_INVALID_PROGRESS->name,
            $res['status'],
            'Invalid progress handling detected'
        );
    }

    /**
     * Data provider for test_update_task_status_progress
     *
     * @return array[] Test data
     */
    public static function update_task_status_progress_data_provider(): array {
        return [
            '0% (valid)' => [0, true],
            '50% (valid)' => [50, true],
            '99% (valid)' => [99, true],
            '100% (valid)' => [100, true],
            '120% (invalid)' => [120, false],
            '-10% (invalid)' => [-10, false],
        ];
    }

    /**
     * Tests that changes to an activity archiving task that belong to another
     * activity type are rejected.
     *
     * @covers \archivingmod_assign\external\update_task_status::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_invalid_task_type_is_rejected(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Prepare a new assign archiving task.
        $wstoken = 'TEST-WS-TOKEN-10';
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);
        $r = $this->generate_valid_request(
            '50000000-0000-0000-0000-0123456789ab',
            $mocks->task->get_id(),
            activity_archiving_task_status::RUNNING
        );

        // Force task to another assignment type.
        $DB->update_record(db_table::ACTIVITY_TASK->value, ['id' => $mocks->task->get_id(), 'archivingmod' => 'bar']);

        // Try to update the status of a task that belongs to another archivingmod.
        $_GET['wstoken'] = $wstoken;
        $this->expectOutputRegex('/.*/');
        $res = update_task_status::execute(
            $r['uuid'],
            $r['taskid'],
            $r['status'],
            $r['progress']
        );

        $this->assertSame(
            webservice_status::E_TASK_TYPE_INVALID->name,
            $res['status'],
            'Task type mismatch was not detected'
        );
    }
}
