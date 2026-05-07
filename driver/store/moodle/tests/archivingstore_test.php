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

namespace archivingstore_moodle;


use local_archiving\storage;

/**
 * Tests for the archivingstore_moodle implementation.
 *
 * @package   archivingstore_moodle
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingstore_moodle implementation.
 */
final class archivingstore_test extends \advanced_testcase {
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
     * Ensures that the correct storage tier is reported.
     *
     * @covers \archivingstore_moodle\archivingstore
     *
     * @return void
     */
    public function test_get_storage_tier(): void {
        $this->assertEquals(
            \local_archiving\type\storage_tier::LOCAL,
            archivingstore::get_storage_tier(),
            'Storage tier should be LOCAL.'
        );
    }

    /**
     * Ensures that the storage reports that it supports retrieval.
     *
     * @covers \archivingstore_moodle\archivingstore
     *
     * @return void
     */
    public function test_supports_retrieve(): void {
        $this->assertTrue(archivingstore::supports_retrieve(), 'Storage should support retrieve.');
    }

    /**
     * Tests if the storage driver correctly reports its availability, based on disk space.
     *
     * @covers \archivingstore_moodle\archivingstore
     *
     * @return void
     */
    public function test_is_available(): void {
        // Test that a file storage with a lot of space available is considered available.
        $mock = $this->getMockBuilder(archivingstore::class)
            ->onlyMethods(['get_free_bytes'])
            ->getMock();
        $mock->method('get_free_bytes')->willReturn(2 * 1024 * 1024 * 1024); // 2 GB free space.

        $this->assertTrue($mock->is_available(), 'Storage should be considered available if more than 1 GB is available.');

        // Test that a file storage with little space available is considered unavailable.
        $mock = $this->getMockBuilder(archivingstore::class)
            ->onlyMethods(['get_free_bytes'])
            ->getMock();
        $mock->method('get_free_bytes')->willReturn(512); // 512 bytes free space.

        $this->assertFalse($mock->is_available(), 'Storage should be considered unavailable if only a few bytes are available.');
    }

    /**
     * Tests if the storage driver correctly reports free bytes.
     *
     * @covers \archivingstore_moodle\archivingstore
     *
     * @return void
     */
    public function test_get_free_bytes(): void {
        global $CFG;

        // Calculate free bytes and check that this roughly matches the disk free space reported by PHP.
        $store = new archivingstore();
        $freebytes = $store->get_free_bytes();
        $this->assertIsInt($freebytes, 'Free bytes should be an integer.');

        $diff = abs($freebytes - disk_free_space($CFG->dataroot));
        $this->assertLessThan(1024 * 1024 * 1024, $diff, 'Free bytes should match disk free space within a rough 1 GB margin.');
    }

    /**
     * Tests storing and retrieving a file.
     *
     * @covers \archivingstore_moodle\archivingstore
     *
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \local_archiving\exception\storage_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function test_store_and_retrieve(): void {
        // Prepare test data.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $inputfile = $this->generator()->create_temp_file();
        $filepath = '/foo/bar';

        try {
            // Try to store the file.
            $store = new archivingstore();
            $handle = $store->store($job->get_id(), $inputfile, $filepath);
            $this->assertSame($job->get_id(), $handle->jobid, 'Job ID should match.');
            $this->assertSame('moodle', $handle->archivingstorename, 'Archiving store name should match.');
            $this->assertSame($inputfile->get_filename(), $handle->filename, 'Filename should match.');
            $this->assertSame(trim($filepath, '/'), $handle->filepath, 'Filepath should match.');
            $this->assertSame($inputfile->get_filesize(), $handle->filesize, 'Filesize should match.');
            $this->assertSame(storage::hash_file($inputfile), $handle->sha256sum, 'SHA256 hash should match.');
            $this->assertSame($inputfile->get_mimetype(), $handle->mimetype, 'Mimetype should match.');

            // Verify that the file can be found by via the Moodle File API by its ID stored in the handles filekey.
            $storedfile = get_file_storage()->get_file_by_id($handle->filekey);
            $this->assertInstanceOf(\stored_file::class, $storedfile, 'Stored file should be retrievable via file ID.');
            $this->assertSame(
                storage::hash_file($inputfile, 'sha1'),
                $storedfile->get_contenthash(),
                'Stored file hash should match original file hash.'
            );

            // Try to retrieve the file.
            $retrievedfile = $store->retrieve($handle, $handle->generate_retrieval_fileinfo_record());
            $this->assertEquals($inputfile->get_filename(), $retrievedfile->get_filename(), 'Retrieved filename should match.');
            $this->assertEquals($inputfile->get_filesize(), $retrievedfile->get_filesize(), 'Retrieved filesize should match.');
            $this->assertEquals($inputfile->get_mimetype(), $retrievedfile->get_mimetype(), 'Retrieved mimetype should match.');
            $this->assertSame(
                storage::hash_file($inputfile),
                storage::hash_file($retrievedfile),
                'Retrieved file hash should match original file hash.'
            );
        } finally {
            // Remove file stored in temp dir.
            if ($handle) {
                $store->delete($handle);
            }
        }
    }

    /**
     * Tests storing and deleting a file.
     *
     * @covers \archivingstore_moodle\archivingstore
     *
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \local_archiving\exception\storage_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function test_delete(): void {
        // Prepare test data.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $inputfile = $this->generator()->create_temp_file();
        $filepath = '/';

        // Try to store the file.
        $fs = get_file_storage();
        $store = new archivingstore();
        $handle = $store->store($job->get_id(), $inputfile, $filepath);

        $this->assertInstanceOf(
            \stored_file::class,
            $fs->get_file_by_id($handle->filekey),
            'Stored file should exist after storing.'
        );

        // Try to delete the file and verify that it is gone.
        $store->delete($handle);
        $this->assertFalse(
            $fs->get_file_by_id($handle->filekey),
            'Stored file should not exist after deletion.'
        );

        // Try to delete a non-existing file in non-strict mode (should not throw an error).
        $store->delete($handle, strict: false);

        // Try to delete a non-existing file in strict mode (should throw an error).
        $this->expectException(\local_archiving\exception\storage_exception::class);
        $store->delete($handle, strict: true);
    }
}
