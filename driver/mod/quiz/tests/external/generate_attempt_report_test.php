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
 * Tests for the generate_attempt_report external service
 *
 * @package   archivingmod_quiz
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\external;

use archivingmod_quiz\type\attempt_report_section;
use archivingmod_quiz\type\webservice_status;
use function DI\create;

/**
 * Tests for the generate_attempt_report external service
 */
final class generate_attempt_report_test extends \advanced_testcase {
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
     * @param int $attemptid Attempt ID
     * @return array Valid request parameters
     */
    protected function generate_valid_request(string $uuid, int $taskid, int $attemptid): array {
        return [
            'uuid' => $uuid,
            'taskid' => $taskid,
            'attemptid' => $attemptid,
            'foldernamepattern' => '${username}/${attemptid}-${date}_${time}',
            'filenamepattern' => 'attempt-${username}-${attemptid}-${date}_${time}',
            'sections' => array_fill_keys(attempt_report_section::values(), true),
            'attachments' => true,
        ];
    }

    /**
     * Tests that the parameter spec is specified correctly and produces no exception.
     *
     * @covers \archivingmod_quiz\external\generate_attempt_report::execute_parameters
     *
     * @return void
     */
    public function test_assure_execute_parameter_spec(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            generate_attempt_report::execute_parameters(),
            'The execute_parameters() method should return an external_function_parameters.'
        );
    }

    /**
     * Tests that the return parameters are specified correctly and produce no exception.
     *
     * @covers \archivingmod_quiz\external\generate_attempt_report::execute_returns
     *
     * @return void
     */
    public function test_assure_return_parameter_spec(): void {
        $this->assertInstanceOf(
            \core_external\external_description::class,
            generate_attempt_report::execute_returns(),
            'The execute_returns() method should return an external_description.'
        );
    }

    /**
     * Test wstoken validation
     *
     * @covers \archivingmod_quiz\external\generate_attempt_report::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \DOMException
     */
    public function test_wstoken_access_check(): void {
        // Gain webservice permission and create mocks.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-VALID';

        // Create a mock task for a quiz without an attempt since PHPUnit uses the CLI-renderer which skips output headers / footers
        // what causes attempt_report::generate_full_page() to fail. This way it is skipped and tested in other test cases instead.
        $mocks = $this->getDataGenerator()->create_mock_task($wstoken, createattempt: false);

        // Generate a valid request.
        $uuid = '30000000-0000-0000-0000-0123456789ab';
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), $mocks->attempts[0]->attemptid);

        // Check that correct wstoken allows access.
        $_GET['wstoken'] = $wstoken;
        $res = generate_attempt_report::execute(
            $r['uuid'],
            $r['taskid'],
            $r['attemptid'],
            $r['foldernamepattern'],
            $r['filenamepattern'],
            $r['sections'],
            $r['attachments']
        );
        $this->assertNotSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Valid wstoken was falsely rejected'
        );

        // Check that incorrect wstoken is rejected.
        $_GET['wstoken'] = 'TEST-WS-TOKEN-INVALID';
        $res = generate_attempt_report::execute(
            $r['uuid'],
            $r['taskid'],
            $r['attemptid'],
            $r['foldernamepattern'],
            $r['filenamepattern'],
            $r['sections'],
            $r['attachments']
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
     * @covers \archivingmod_quiz\external\generate_attempt_report::execute
     * @covers \archivingmod_quiz\external\generate_attempt_report::validate_parameters
     *
     * @param string $invalidparameterkey Key of the parameter to invalidate
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public function test_parameter_validation(string $invalidparameterkey): void {
        // Create mock quiz and activity archiving task.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-2';
        $mocks = $this->getDataGenerator()->create_mock_task($wstoken);

        // Create a request.
        $uuid = '20000000-0000-0000-0000-0123456789ab';
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), $mocks->attempts[0]->attemptid);
        $_GET['wstoken'] = $wstoken;

        // Execute the request.
        if (in_array($invalidparameterkey, ['sections'])) {
            // Empty array is actually already detected by Moodle parameter validation so we expect an exception here.
            $this->expectException(\invalid_parameter_exception::class);
            $this->expectExceptionMessageMatches('/.*' . $invalidparameterkey . '.*/');
        }
        $res = generate_attempt_report::execute(
            $uuid,
            $invalidparameterkey == 'taskid' ? 0 : $r['taskid'],
            $invalidparameterkey == 'attemptid' ? 0 : $r['attemptid'],
            $invalidparameterkey == 'foldername pattern' ? 'invalid-${pattern' : $r['foldernamepattern'],
            $invalidparameterkey == 'filename pattern' ? 'invalid-${pattern' : $r['filenamepattern'],
            $invalidparameterkey == 'sections' ? [] : $r['sections'],
            $r['attachments']
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
            'Invalid taskid' => ['taskid'],
            'Invalid attemptid' => ['attemptid'],
            'Invalid foldernamepattern' => ['foldername pattern'],
            'Invalid filenamepattern' => ['filename pattern'],
            'Invalid sections' => ['sections'],
        ];
    }

    /**
     * Test web service part of processing of a valid request
     *
     * @covers \archivingmod_quiz\external\generate_attempt_report::execute
     *
     * @return void
     * @throws \DOMException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public function test_execute(): void {
        // Create mock quiz and activity archiving task.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-1';

        // Create a mock task for a quiz without an attempt since PHPUnit uses the CLI-renderer which skips output headers / footers
        // what causes attempt_report::generate_full_page() to fail. This way it is skipped and tested in other test cases instead.
        $mocks = $this->getDataGenerator()->create_mock_task($wstoken, createattempt: false);

        // Create a valid request.
        $uuid = '10000000-0000-0000-0000-0123456789ab';
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), $mocks->attempts[0]->attemptid);
        $_GET['wstoken'] = $wstoken;

        // Execute the request.
        $res = generate_attempt_report::execute(
            $r['uuid'],
            $r['taskid'],
            $r['attemptid'],
            $r['foldernamepattern'],
            $r['filenamepattern'],
            $r['sections'],
            $r['attachments']
        );
        $this->assertSame(
            webservice_status::E_ATTEMPT_NOT_FOUND->name,
            $res['status'],
            'Mock quiz does not contain the actual attempt so E_ATTEMPT_NOT_FOUND is expected...'
        );
    }
}
