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
 * Tests for the generate_submission_report external service
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\external;

use archivingmod_assign\local\type\attachment_type;
use archivingmod_assign\local\type\submission_report_section;
use archivingmod_assign\local\type\webservice_status;

/**
 * Tests for the generate_submission_report external service
 */
final class generate_submission_report_test extends \advanced_testcase {
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
     * @param int $submissionid Submission ID
     * @return array Valid request parameters
     */
    protected function generate_valid_request(string $uuid, int $taskid, int $submissionid): array {
        return [
            'uuid' => $uuid,
            'taskid' => $taskid,
            'submissionid' => $submissionid,
            'foldernamepattern' => '${username}/${submissionid}-${date}_${time}',
            'filenamepattern' => 'submission-${username}-${submissionid}-${date}_${time}',
            'sections' => array_fill_keys(submission_report_section::values(), true),
        ];
    }

    /**
     * Tests that the parameter spec is specified correctly and produces no exception.
     *
     * @covers \archivingmod_assign\external\generate_submission_report::execute_parameters
     *
     * @return void
     */
    public function test_assure_execute_parameter_spec(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            generate_submission_report::execute_parameters(),
            'The execute_parameters() method should return an external_function_parameters.'
        );
    }

    /**
     * Tests that the return parameters are specified correctly and produce no exception.
     *
     * @covers \archivingmod_assign\external\generate_submission_report::execute_returns
     *
     * @return void
     */
    public function test_assure_return_parameter_spec(): void {
        $this->assertInstanceOf(
            \core_external\external_description::class,
            generate_submission_report::execute_returns(),
            'The execute_returns() method should return an external_description.'
        );
    }

    /**
     * Test wstoken validation
     *
     * @covers \archivingmod_assign\external\generate_submission_report::execute
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \DOMException
     */
    public function test_wstoken_access_check(): void {
        // Gain webservice permission and create mocks.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-VALID';

        // Create a mock task without a submission since PHPUnit uses the CLI-renderer which skips output headers / footers
        // what causes generate_full_page() to fail. This way it is skipped and tested in other test cases instead.
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);
        $uuid = '30000000-0000-0000-0000-0123456789ab';
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), 0);

        // Check that correct wstoken allows access.
        $_GET['wstoken'] = $wstoken;
        $res = generate_submission_report::execute(
            $r['uuid'],
            $r['taskid'],
            $r['submissionid'],
            $r['foldernamepattern'],
            $r['filenamepattern'],
            $r['sections'],
        );
        $this->assertNotSame(
            webservice_status::E_ACCESS_DENIED->name,
            $res['status'],
            'Valid wstoken was falsely rejected'
        );

        // Check that incorrect wstoken is rejected.
        $_GET['wstoken'] = 'TEST-WS-TOKEN-INVALID';
        $res = generate_submission_report::execute(
            $r['uuid'],
            $r['taskid'],
            $r['submissionid'],
            $r['foldernamepattern'],
            $r['filenamepattern'],
            $r['sections'],
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
     * @covers \archivingmod_assign\external\generate_submission_report::execute
     * @covers \archivingmod_assign\external\generate_submission_report::validate_parameters
     *
     * @param string $invalidparameterkey Key of the parameter to invalidate
     * @return void
     * @throws \DOMException
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
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), 0);
        $_GET['wstoken'] = $wstoken;

        // Execute the request.
        if ($invalidparameterkey === 'uuid') {
            // A UUID containing HTML tags is detected by Moodle parameter validation so we expect an exception here.
            $this->expectException(\invalid_parameter_exception::class);
            $this->expectExceptionMessageMatches('/.*uuid.*/');
        }
        if ($invalidparameterkey === 'sections') {
            // Empty array is already detected by Moodle parameter validation so we expect an exception here.
            $this->expectException(\invalid_parameter_exception::class);
            $this->expectExceptionMessageMatches('/.*sections.*/');
        }
        $res = generate_submission_report::execute(
            $invalidparameterkey === 'uuid' ? '<a href="localhost">not-a-uuid</a>' : $r['uuid'],
            $invalidparameterkey === 'taskid' ? 0 : $r['taskid'],
            $invalidparameterkey === 'submissionid' ? 0 : $r['submissionid'],
            $invalidparameterkey === 'foldernamepattern' ? 'invalid-${pattern' : $r['foldernamepattern'],
            $invalidparameterkey === 'filenamepattern' ? 'invalid-${pattern' : $r['filenamepattern'],
            $invalidparameterkey === 'sections' ? [] : $r['sections'],
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
            'Invalid submissionid' => ['submissionid'],
            'Invalid foldernamepattern' => ['foldernamepattern'],
            'Invalid filenamepattern' => ['filenamepattern'],
            'Invalid sections' => ['sections'],
        ];
    }

    /**
     * Test web service part of processing of a valid request
     *
     * @covers \archivingmod_assign\external\generate_submission_report::execute
     *
     * @return void
     * @throws \DOMException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public function test_execute(): void {
        // Create mock assignment and activity archiving task.
        $this->resetAfterTest();
        $this->setAdminUser();
        $wstoken = 'TEST-WS-TOKEN-1';

        // Create a mock task without a submission since PHPUnit uses the CLI-renderer which skips output headers / footers
        // what causes generate_full_page() to fail. This way it is skipped and tested in other test cases instead.
        $mocks = $this->getDataGenerator()->create_mock_task(wstoken: $wstoken);

        // Create a valid request.
        $uuid = '10000000-0000-0000-0000-0123456789ab';
        $r = $this->generate_valid_request($uuid, $mocks->task->get_id(), 0);
        $_GET['wstoken'] = $wstoken;

        // Execute the request.
        $res = generate_submission_report::execute(
            $r['uuid'],
            $r['taskid'],
            $r['submissionid'],
            $r['foldernamepattern'],
            $r['filenamepattern'],
            $r['sections'],
        );
        $this->assertSame(
            webservice_status::E_SUBMISSION_NOT_FOUND->name,
            $res['status'],
            'Mock assignment does not contain the actual submission so E_SUBMISSION_NOT_FOUND is expected...'
        );
    }

    /**
     * Tests that an empty file list produces an empty attachment list.
     *
     * @covers \archivingmod_assign\external\generate_submission_report::prepare_attachment_list_for_response
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_prepare_attachment_list_for_response_with_empty_input(): void {
        $this->resetAfterTest();
        $result = generate_submission_report::prepare_attachment_list_for_response(
            attachment_type::STUDENT_FILE_SUBMISSION,
            []
        );
        $this->assertSame([], $result);
    }

    /**
     * Tests that file metadata is correctly mapped to the response format for each attachment type.
     *
     * @dataProvider attachment_type_data_provider
     * @covers \archivingmod_assign\external\generate_submission_report::prepare_attachment_list_for_response
     *
     * @param attachment_type $type Attachment type to test
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_prepare_attachment_list_for_response(attachment_type $type): void {
        $this->resetAfterTest();

        // Create two stored_file objects to verify multi-file handling and all metadata fields.
        $fs = get_file_storage();
        $context = \context_system::instance();
        $file1 = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'core',
            'filearea'  => 'unittest',
            'itemid'    => 1,
            'filepath'  => '/',
            'filename'  => 'testfile1.pdf',
        ], 'content of test file 1');
        $file2 = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'core',
            'filearea'  => 'unittest',
            'itemid'    => 2,
            'filepath'  => '/',
            'filename'  => 'testfile2.txt',
        ], 'content of test file 2');

        $result = generate_submission_report::prepare_attachment_list_for_response($type, [$file1, $file2]);

        $this->assertCount(2, $result, 'Result should contain one entry per file');

        foreach ([$file1, $file2] as $idx => $file) {
            $this->assertArrayHasKey('type', $result[$idx]);
            $this->assertArrayHasKey('filename', $result[$idx]);
            $this->assertArrayHasKey('filesize', $result[$idx]);
            $this->assertArrayHasKey('mimetype', $result[$idx]);
            $this->assertArrayHasKey('contenthash', $result[$idx]);
            $this->assertArrayHasKey('downloadurl', $result[$idx]);

            $this->assertSame($type->value, $result[$idx]['type']);
            $this->assertSame($file->get_filename(), $result[$idx]['filename']);
            $this->assertSame($file->get_filesize(), $result[$idx]['filesize']);
            $this->assertSame($file->get_mimetype(), $result[$idx]['mimetype']);
            $this->assertSame($file->get_contenthash(), $result[$idx]['contenthash']);
            $this->assertStringContainsString($file->get_filename(), $result[$idx]['downloadurl']);
        }
    }

    /**
     * Data provider for test_prepare_attachment_list_for_response
     *
     * @return array[] Test data
     */
    public static function attachment_type_data_provider(): array {
        return [
            attachment_type::PROVIDED_ASSIGNMENT_FILE->name => [attachment_type::PROVIDED_ASSIGNMENT_FILE],
            attachment_type::STUDENT_FILE_SUBMISSION->name  => [attachment_type::STUDENT_FILE_SUBMISSION],
            attachment_type::GRADER_FEEDBACK_FILE->name     => [attachment_type::GRADER_FEEDBACK_FILE],
            attachment_type::GRADER_ANNOTATED_FILE->name    => [attachment_type::GRADER_ANNOTATED_FILE],
        ];
    }

    /**
     * Tests that passing a non-stored_file object in the files array throws a coding_exception.
     *
     * @covers \archivingmod_assign\external\generate_submission_report::prepare_attachment_list_for_response
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_prepare_attachment_list_for_response_rejects_non_stored_file(): void {
        $this->resetAfterTest();
        $this->expectException(\coding_exception::class);
        generate_submission_report::prepare_attachment_list_for_response(
            attachment_type::STUDENT_FILE_SUBMISSION,
            ['not-a-stored-file']
        );
    }
}
