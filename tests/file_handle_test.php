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

use local_archiving\exception\storage_exception;
use local_archiving\type\filearea;
use local_archiving\util\plugin_util;

/**
 * Tests for the file_handle class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for file_handle
 */
final class file_handle_test extends \advanced_testcase {

    /**
     * Helper to get the test data generator for local_archiving
     *
     * @return \local_archiving_generator
     */
    private function generator(): \local_archiving_generator {
        /** @var \local_archiving_generator */
        return self::getDataGenerator()->get_plugin_generator('local_archiving');
    }

    /**
     * This method prepates archivingstore_local to be used within a unit test.
     *
     * @return void
     * @throws \moodle_exception
     */
    private function init_archivingstore(): void {
        global $CFG;
        $this->resetAfterTest();

        // Set localdir storage driver path to a temporary directory.
        $storagepath = rtrim($CFG->phpunit_dataroot, '/') . '/temp/local_archiving/archivingstore_localdir';
        if (!mkdir($storagepath, recursive: true)) {
            throw new \moodle_exception('failed_to_create_storage_path', 'local_archiving', a: $storagepath);
        }
        set_config('storage_path', $storagepath, 'archivingstore_localdir');
        set_config('enabled', true, 'archivingstore_localdir');
    }

    /**
     * Tests the creation of a file_handle
     *
     * @covers \local_archiving\file_handle::create
     * @covers \local_archiving\file_handle::__construct
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_create_file_handle(): void {
        $this->resetAfterTest();
        $generator = $this->generator();

        // Create new file_handle.
        $data = $generator->generate_file_handle_data();
        $filehandle = file_handle::create(
            jobid: $data->jobid,
            archivingstorename: $data->archivingstorename,
            filename: $data->filename,
            filepath: $data->filepath,
            filesize: $data->filesize,
            sha256sum: $data->sha256sum,
            mimetype: $data->mimetype,
            filekey: $data->filekey
        );

        // Assert that the test data object was created and properties are set.
        $this->assertInstanceOf(file_handle::class, $filehandle);
        $this->assertEquals($data->jobid, $filehandle->jobid);
        $this->assertEquals($data->archivingstorename, $filehandle->archivingstorename);
        $this->assertEquals($data->filename, $filehandle->filename);
        $this->assertEquals($data->filepath, $filehandle->filepath);
        $this->assertEquals($data->filesize, $filehandle->filesize);
        $this->assertEquals($data->sha256sum, $filehandle->sha256sum);
        $this->assertEquals($data->mimetype, $filehandle->mimetype);
        $this->assertEquals($data->filekey, $filehandle->filekey);
    }

    /**
     * Tests loading a file_handle test data object by ID
     *
     * @covers \local_archiving\file_handle::get_by_id
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_get_by_id(): void {
        $this->resetAfterTest();
        $generator = $this->generator();

        // Create a new file_handle and try to load it by its id.
        $expected = $generator->create_file_handle();
        $actual = file_handle::get_by_id($expected->id);

        // Check that file_handles are identical.
        $this->assertEquals($expected, $actual);
    }

    /**
     * Tests retrieving file_handles by their referenced job ID
     *
     * @covers \local_archiving\file_handle::get_by_jobid
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_get_by_jobid(): void {
        $this->resetAfterTest();
        $generator = $this->generator();

        // Create various file handles for different jobs. Array indexed by job id
        $expected = [
            42 => [
                $generator->create_file_handle(['jobid' => 42, 'filename' => 'file1.txt']),
                $generator->create_file_handle(['jobid' => 42, 'filename' => 'file2.txt']),
                $generator->create_file_handle(['jobid' => 42, 'filename' => 'file3.txt'])
            ],
            123 => [
                $generator->create_file_handle(['jobid' => 123, 'filename' => 'file1.txt']),
            ],
            1337 => [],
        ];

        // Retrieve file handles for all job ids.
        foreach ($expected as $jobid => $files) {
            $actual = file_handle::get_by_jobid($jobid);

            // Check that the number of file handles matches.
            $this->assertCount(count($files), $actual, "Job ID $jobid should have " . count($files) . " files");

            // Check that each file handle matches the expected data.
            foreach ($files as $index => $file) {
                $this->assertEquals($file, $actual[$file->id], "File handle mismatch for job ID $jobid");
            }
        }
    }

    /**
     * Tests destroying a file_handle without removing the referenced file
     *
     * @covers \local_archiving\file_handle::destroy
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_destroy_without_file_removal(): void {
        $this->resetAfterTest();
        $generator = $this->generator();

        // Create a file_handle test data object.
        $filehandle = $generator->create_file_handle();

        // Destroy the file_handle.
        $filehandle->destroy(removefile: false);

        // Assert that the file_handle is no longer retrievable.
        $this->expectException(\dml_exception::class);
        file_handle::get_by_id($filehandle->id);
    }

    /**
     * Tests destroying a file_handle and removing the referenced file
     *
     * @covers \local_archiving\file_handle::destroy
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_destroy_with_file_removal(): void {
        $this->resetAfterTest();
        $this->init_archivingstore();
        $generator = $this->generator();

        // Create a file_handle test data object.
        $filehandle = $generator->create_file_handle();

        // Destroy the file_handle.
        $filehandle->destroy(removefile: true);

        // Assert that the file_handle is no longer retrievable.
        $this->expectException(\dml_exception::class);
        file_handle::get_by_id($filehandle->id);
    }

    /**
     * Tests marking a referenced file within a file_handle as deleted
     *
     * @covers \local_archiving\file_handle::mark_as_deleted
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_mark_as_deleted(): void {
        $this->resetAfterTest();
        $generator = $this->generator();

        // Create a file_handle and mark the referenced file as deleted.
        $filehandle = $generator->create_file_handle();
        $filehandle->mark_as_deleted();

        // Assert that the file_handle was internally updated.
        $this->assertTrue($filehandle->deleted);

        // Test that the deletion was persistet to the database.
        $actual = file_handle::get_by_id($filehandle->id);
        $this->assertTrue($actual->deleted, 'File handle should be marked as deleted');
    }

    /**
     * Tests generating a retrieval fileinfo record from a file_handle
     *
     * @covers \local_archiving\file_handle::generate_retrieval_fileinfo_record
     *
     * @return void
     */
    public function test_generate_retrieval_fileinfo_record(): void {
        $this->resetAfterTest();
        $generator = $this->generator();

        // Create an archive job and a file_handle.
        $job = $generator->create_archive_job();
        $filehandle = $generator->create_file_handle(['jobid' => $job->get_id()]);

        // Generate the fileinfo record.
        $fileinfo = $filehandle->generate_retrieval_fileinfo_record();

        // Assert that the fileinfo record contains the expected data.
        $this->assertInstanceOf(\stdClass::class, $fileinfo);
        $this->assertSame($job->get_context()->id, $fileinfo->contextid, 'Context ID should match job context');
        $this->assertSame(filearea::FILESTORE_CACHE->get_component(), $fileinfo->component, 'Component should be filestore_cache');
        $this->assertSame(filearea::FILESTORE_CACHE->value, $fileinfo->filearea, 'File area should be filestore_cache');
        $this->assertSame($filehandle->id, $fileinfo->itemid, 'Item ID should match file handle ID');
        $this->assertNotEmpty($fileinfo->filepath, 'Filepath should not be empty');
        $this->assertNotEmpty($fileinfo->filename, 'Filename should not be empty');
    }

    /**
     * Tries to retrieve a file that is not locally cached right now.
     *
     * @covers \local_archiving\file_handle::get_local_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_get_local_file_non_existing(): void {
        // Prepare.
        $this->resetAfterTest();
        $filehandle = $this->generator()->create_file_handle();

        // Try to retrieve not yet cached local file.
        $localfile = $filehandle->get_local_file();
        $this->assertNull($localfile, 'Expected null since the file is not locally cached');
    }

    /**
     * Tries to retrieve a file that is already cached locally.
     *
     * @covers \local_archiving\file_handle::get_local_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws storage_exception
     */
    public function test_get_local_file_existing(): void {
        // Prepare.
        $this->resetAfterTest();
        $this->init_archivingstore();
        $generator = $this->generator();

        $job = $generator->create_archive_job();
        $file = $generator->create_temp_file();

        /** @var \local_archiving\driver\archivingstore $archivingstore */
        $archivingstore = new (plugin_util::get_subplugin_by_name('archivingstore', 'localdir'))();
        $filehandle = $archivingstore->store($job->get_id(), $file, '/');

        // Try to retrieve not yet cached local file.
        $actual = $filehandle->get_local_file();
        $this->assertNull($actual, 'Expected null since the file is not locally cached yet');

        // Load the file into local cache and try again.
        $expected = $filehandle->retrieve_file();
        $actual = $filehandle->get_local_file();
        $this->assertInstanceOf(\stored_file::class, $actual, 'Expected a stored_file instance');
        $this->assertSame($expected->get_filename(), $actual->get_filename(), 'Filename should match');
        $this->assertEquals($expected->get_timecreated(), $actual->get_timecreated(), 'Time created should match');
        $this->assertEquals($expected->get_timemodified(), $actual->get_timemodified(), 'Time modified should match');
        $this->assertSame($expected->get_contenthash(), $actual->get_contenthash(), 'Content hash should match');
    }

    /**
     * Tests retrieving a local cache copy of a referenced file.
     *
     * @covers \local_archiving\file_handle::retrieve_file
     *
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     * @throws storage_exception
     */
    public function test_retrieve_file(): void {
        // Prepare.
        $this->resetAfterTest();
        $this->init_archivingstore();
        $generator = $this->generator();

        $job = $generator->create_archive_job();
        $file = $generator->create_temp_file();

        /** @var \local_archiving\driver\archivingstore $archivingstore */
        $archivingstore = new (plugin_util::get_subplugin_by_name('archivingstore', 'localdir'))();
        $filehandle = $archivingstore->store($job->get_id(), $file, '/');

        // Retrieve the file initially.
        $file = $filehandle->retrieve_file();
        $this->assertInstanceOf(\stored_file::class, $file, 'Expected a stored_file instance');

        // Retrieve the file again and ensure its timemodified was updated.
        $file->set_timemodified(0);
        $file = $filehandle->retrieve_file();
        $this->assertNotEquals(0, $file->get_timemodified(), 'Time modified should be updated on retrieval');
    }

    /**
     * Tests that a file that was marked as deleted can not be retrieved.
     *
     * @covers \local_archiving\file_handle::retrieve_file
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws storage_exception
     */
    public function test_retrieve_file_deleted(): void {
        // Prepare.
        $this->resetAfterTest();
        $filehandle = $this->generator()->create_file_handle();
        $filehandle->mark_as_deleted();

        // Try to retrieve the file and expect an exception.
        $this->expectException(\local_archiving\exception\storage_exception::class);
        $filehandle->retrieve_file();
    }

    /**
     * Tests retrieving the corresponding archiving store instance for a file_handle
     *
     * @covers \local_archiving\file_handle::archivingstore
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_archivingstore(): void {
        // Create file handle with localdir archiving store.
        $this->resetAfterTest();
        $filehandle = $this->generator()->create_file_handle(['archivingstorename' => 'localdir']);

        // Retrieve archivingstore instance and validate.
        $archivingstore = $filehandle->archivingstore();
        $this->assertInstanceOf(\archivingstore_localdir\archivingstore::class, $archivingstore);
    }

}
