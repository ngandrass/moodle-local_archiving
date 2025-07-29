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

namespace local_archiving;

use local_archiving\type\db_table;
use local_archiving\type\filearea;

/**
 * Tests for the tsp_manager class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the tsp_manager class.
 */
final class tsp_manager_test extends \advanced_testcase {

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
     * Creates a mocked tsp_manager instance that uses a mocked tsp_client.
     *
     * The sign() method of the tsp_client will return the given dummy query and reply.
     *
     * @param file_handle $filehandle File handle to link the tsp_manager to.
     * @param string $serverurl Dummy URL of the TSP server to use in the tsp_client mock.
     * @param string $dummyquery Query to be returned by the tsp_client mock.
     * @param string $dummyreply Reply to be returned by the tsp_client mock.
     * @return tsp_manager Mocked tsp_manager instance for the given file handle.
     */
    protected function create_tsp_manager_mock(
        file_handle $filehandle,
        string $serverurl = 'localhost',
        string $dummyquery = 'tsp-dummy-query',
        string $dummyreply = 'tsp-dummy-reply'
    ): tsp_manager {
        // Prepare a mocked tsp_client to be used within the tsp_manager.
        $tspclientmock = $this->getMockBuilder(tsp_client::class)
            ->onlyMethods(['sign'])
            ->setConstructorArgs([$serverurl])
            ->getMock();
        $tspclientmock->expects($this->any())
            ->method('sign')
            ->willReturn([
                'query' => $dummyquery,
                'reply' => $dummyreply,
            ]);

        // Create a tsp_manager instance that uses the mocked tsp_client.
        $tspmanager = $this->getMockBuilder(tsp_manager::class)
            ->setConstructorArgs([$filehandle])
            ->onlyMethods(['get_tsp_client'])
            ->getMock();
        $tspmanager->expects($this->any())
            ->method('get_tsp_client')
            ->willReturn($tspclientmock);

        return $tspmanager;
    }

    /**
     * Tests detection for automatic TSP signing global enable / disable.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_is_automatic_tsp_signing_enabled(): void {
        $this->resetAfterTest();

        // Enable automatic TSP signing.
        set_config('tsp_enable', true, 'local_archiving');
        set_config('tsp_automatic_signing', true, 'local_archiving');
        $this->assertTrue(tsp_manager::is_automatic_tsp_signing_enabled(), 'Automatic TSP signing should be enabled.');

        // Disable automatic TSP signing.
        set_config('tsp_automatic_signing', false, 'local_archiving');
        $this->assertFalse(tsp_manager::is_automatic_tsp_signing_enabled(), 'Automatic TSP signing should be disabled.');

        // Re-enable automatic TSP signing but disable TSP signing globally.
        set_config('tsp_automatic_signing', true, 'local_archiving');
        set_config('tsp_enable', false, 'local_archiving');
        $this->assertFalse(
            tsp_manager::is_automatic_tsp_signing_enabled(),
            'Automatic TSP signing should be disabled when TSP is globally disabled.'
        );
    }

    /**
     * Tests detection if a file wants a TSP timestamp.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_wants_tsp_timestamp(): void {
        // Enable automatic TSP signing and create a new file in need.
        $this->resetAfterTest();
        set_config('tsp_enable', true, 'local_archiving');
        set_config('tsp_automatic_signing', true, 'local_archiving');
        $filehandle = $this->generator()->create_file_handle();

        // Fresh files should have no TSP data.
        $tspmanager = new tsp_manager($filehandle);
        $this->assertFalse($tspmanager->has_tsp_timestamp(), 'New file should not have a TSP timestamp.');

        // Ensure that files without a TSP timestamp are marked as needing one.
        $this->assertTrue($tspmanager->wants_tsp_timestamp(), 'File should want a TSP timestamp.');

        // Disable automatic TSP signing and check again.
        set_config('tsp_automatic_signing', false, 'local_archiving');
        $tspmanager = new tsp_manager($filehandle);
        $this->assertFalse(
            $tspmanager->wants_tsp_timestamp(),
            'File should not want a TSP timestamp when automatic signing is disabled.'
        );
    }

    /**
     * Tests issuing a TSP timestamp for a file.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_timestamp(): void {
        // Prepare TSP manager with a mocked tsp_client.
        $this->resetAfterTest();
        set_config('tsp_enable', true, 'local_archiving');
        $filehandle = $this->generator()->create_file_handle();
        $tspmanager = $this->create_tsp_manager_mock(
            $filehandle,
            'localhost',
            'tsp-dummy-query-'.$filehandle->id,
            'tsp-dummy-reply-'.$filehandle->id
        );

        $this->assertFalse($tspmanager->has_tsp_timestamp(), 'New file should not have a TSP timestamp.');

        // Try to issue a TSP signature for the file.
        $tspmanager->timestamp();
        $tspdata = $tspmanager->get_tsp_data();
        $this->assertNotNull($tspdata, 'TSP data should not be null after timestamping.');
        $this->assertEquals('localhost', $tspdata->server, 'TSP server URL should match the mocked one.');
        $this->assertEquals('tsp-dummy-query-'.$filehandle->id, $tspdata->query, 'TSP query should match the mocked one.');
        $this->assertEquals('tsp-dummy-reply-'.$filehandle->id, $tspdata->reply, 'TSP reply should match the mocked one.');
        $this->assertGreaterThan(time() - MINSECS, $tspdata->timecreated, 'TSP timestamp should be recent.');
    }

    /**
     * Tests that issuing of a TSP timestamp fails when TSP is disabled globally.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_timestamp_when_disabled(): void {
        // Disable TSP globally and prepare a file to sign.
        $this->resetAfterTest();
        set_config('tsp_enable', false, 'local_archiving');
        $filehandle = $this->generator()->create_file_handle();

        // Try to issue a signature while TSP is disabled globally.
        $tspmanager = new tsp_manager($filehandle);
        $this->expectException(\moodle_exception::class, 'Expected exception when trying to timestamp a file without TSP enabled.');
        $tspmanager->timestamp();
    }

    /**
     * Teste retrieval and deletion of TSP data for a file.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_and_delete_tsp_data(): void {
        global $DB;

        // Prepare a file handle with TSP data.
        $this->resetAfterTest();
        $filehandle = $this->generator()->create_file_handle();
        $tspmanager = new tsp_manager($filehandle);

        $DB->insert_record(db_table::TSP->value, [
            'filehandleid' => $filehandle->id,
            'timecreated' => time(),
            'server' => 'localhost',
            'timestampquery' => 'sample-query',
            'timestampreply' => 'sample-reply',
        ]);

        // Assure that TSP data is present.
        $this->assertTrue($tspmanager->has_tsp_timestamp(), 'File should have a TSP timestamp after timestamping.');
        $this->assertNotNull($tspmanager->get_tsp_data(), 'TSP data should be present after timestamping.');

        // Delete TSP data.
        $tspmanager->delete_tsp_data();
        $this->assertFalse($tspmanager->has_tsp_timestamp(), 'File should not have a TSP timestamp after deletion.');
        $this->assertNull($tspmanager->get_tsp_data(), 'TSP data should be null after deletion.');

        // Try to delete TSP data again, should not throw an error.
        $tspmanager->delete_tsp_data();
        $this->assertFalse($tspmanager->has_tsp_timestamp(), 'File should not have a TSP timestamp after deletion.');
        $this->assertNull($tspmanager->get_tsp_data(), 'TSP data should be null after deletion.');
    }

    /**
     * Tests generation of download URLs for TSP queries.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_get_query_download_url(): void {
        global $DB;

        // Prepare a file handle and tsp_manager instance.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $filehandle = $this->generator()->create_file_handle(['jobid' => $job->get_id()]);
        $tspmanager = new tsp_manager($filehandle);

        // Ensure that we get nothing when no TSP data is present.
        $this->assertFalse($tspmanager->has_tsp_timestamp(), 'File should not have a TSP timestamp.');
        $this->assertNull($tspmanager->get_query_download_url(), 'Should not get a download URL for the query without TSP data.');

        // Add timestamp data and check the download URL.
        $DB->insert_record(db_table::TSP->value, [
            'filehandleid' => $filehandle->id,
            'timecreated' => time(),
            'server' => 'localhost',
            'timestampquery' => 'sample-query',
            'timestampreply' => 'sample-reply',
        ]);
        $this->assertTrue($tspmanager->has_tsp_timestamp(), 'File should have a TSP timestamp after inserting data.');

        $url = $tspmanager->get_query_download_url();
        $expectedfilename = $filehandle->sha256sum . '.' . $tspmanager::TSP_QUERY_FILE_EXTENSION;
        $this->assertNotEmpty($url, 'Should get a download URL for the query after TSP data is present.');
        $this->assertStringContainsString($expectedfilename, $url, 'Download URL should contain the expected filename.');
        $this->assertStringContainsString(filearea::TSP->value, $url, 'Download URL should contain the TSP file area.');
        $this->assertStringContainsString(filearea::TSP->get_component(), $url, 'Download URL should contain the TSP component.');
    }

    /**
     * Tests generation of download URLs for TSP replies.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_get_reply_download_url(): void {
        global $DB;

        // Prepare a file handle and tsp_manager instance.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $filehandle = $this->generator()->create_file_handle(['jobid' => $job->get_id()]);
        $tspmanager = new tsp_manager($filehandle);

        // Ensure that we get nothing when no TSP data is present.
        $this->assertFalse($tspmanager->has_tsp_timestamp(), 'File should not have a TSP timestamp.');
        $this->assertNull($tspmanager->get_reply_download_url(), 'Should not get a download URL for the reply without TSP data.');

        // Add timestamp data and check the download URL.
        $DB->insert_record(db_table::TSP->value, [
            'filehandleid' => $filehandle->id,
            'timecreated' => time(),
            'server' => 'localhost',
            'timestampquery' => 'sample-query',
            'timestampreply' => 'sample-reply',
        ]);
        $this->assertTrue($tspmanager->has_tsp_timestamp(), 'File should have a TSP timestamp after inserting data.');

        $url = $tspmanager->get_reply_download_url();
        $expectedfilename = $filehandle->sha256sum . '.' . $tspmanager::TSP_REPLY_FILE_EXTENSION;
        $this->assertNotEmpty($url, 'Should get a download URL for the reply after TSP data is present.');
        $this->assertStringContainsString($expectedfilename, $url, 'Download URL should contain the expected filename.');
        $this->assertStringContainsString(filearea::TSP->value, $url, 'Download URL should contain the TSP file area.');
        $this->assertStringContainsString(filearea::TSP->get_component(), $url, 'Download URL should contain the TSP component.');
    }

    /**
     * Tests sending TSP data as a virtual file.
     *
     * @runInSeparateProcess
     * @covers       \local_archiving\tsp_manager
     * @dataProvider send_virtual_tsp_file_data_provider
     *
     * @param string $type
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_send_virtual_tsp_file(string $type): void {
        global $CFG, $DB;

        // Ensure that this test is run in a separate process.
        if ($CFG->branch < 404) {
            $this->markTestSkipped('This test requires Moodle 4.4 or higher. PHPUnit process isolation is required.');
        }

        // Prepare a file handle with TSP data.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $filehandle = $this->generator()->create_file_handle(['jobid' => $job->get_id()]);
        $tspmanager = new tsp_manager($filehandle);

        $DB->insert_record(db_table::TSP->value, [
            'filehandleid' => $filehandle->id,
            'timecreated' => time(),
            'server' => 'localhost',
            'timestampquery' => 'sample-'.tsp_manager::TSP_QUERY_FILE_EXTENSION,
            'timestampreply' => 'sample-'.tsp_manager::TSP_REPLY_FILE_EXTENSION,
        ]);
        $this->assertTrue($tspmanager->has_tsp_timestamp(), 'File should have a TSP timestamp after inserting data.');

        // Send the virtual TSP file and capture the output.
        ob_start();
        $tspmanager::send_virtual_tsp_file(
            path: "/{$filehandle->id}/",
            filename: $filehandle->sha256sum . '.' . $type,
        );
        $sentdata = ob_get_contents();
        ob_end_clean();

        // Validate the output.
        $this->assertEquals('sample-'.$type, $sentdata, 'Sent data should match the expected sample data.');
    }

    /**
     * Test data provider for send_virtual_tsp_file.
     *
     * @return array[] File types to test.
     */
    public static function send_virtual_tsp_file_data_provider(): array {
        return [
            'TSP query' => [tsp_manager::TSP_QUERY_FILE_EXTENSION],
            'TSP reply' => [tsp_manager::TSP_REPLY_FILE_EXTENSION],
        ];
    }

    /**
     * Tests sending a virtual TSP file with an invalid path.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \moodle_exception
     */
    public function test_send_virtual_tsp_file_invalid_path(): void {
        $this->expectException(
            \moodle_exception::class,
            'Expected exception when trying to send a virtual TSP file with an invalid path.'
        );
        tsp_manager::send_virtual_tsp_file(
            '/../../../secret/',
            '64ec88ca00b268e5ba1a35678a1b5316d212f4f366b2477232534a8aeca37f3c.'.tsp_manager::TSP_QUERY_FILE_EXTENSION
        );
    }

    /**
     * Tests sending a virtual TSP file with an invalid filename.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \moodle_exception
     */
    public function test_send_virtual_tsp_file_invalid_filename(): void {
        $this->expectException(
            \moodle_exception::class,
            'Expected exception when trying to send a virtual TSP file with an invalid filename.'
        );
        tsp_manager::send_virtual_tsp_file('/1/', 'mypasswords.txt');
    }

    /**
     * Tests sending a virtual TSP file without TSP data.
     *
     * @covers \local_archiving\tsp_manager
     *
     * @return void
     * @throws \moodle_exception
     */
    public function test_send_virtual_tsp_file_missing_data(): void {
        // Create file handle without TSP data.
        $this->resetAfterTest();
        $filehandle = $this->generator()->create_file_handle();

        $this->expectException(
            \moodle_exception::class,
            'Expected exception when trying to send a virtual TSP file without TSP data.'
        );
        tsp_manager::send_virtual_tsp_file(
            path: "/{$filehandle->id}/",
            filename: $filehandle->sha256sum . '.' . tsp_manager::TSP_QUERY_FILE_EXTENSION
        );
    }

}
