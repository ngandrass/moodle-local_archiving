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

namespace archivingstore_localdir;


use local_archiving\storage;

/**
 * Tests for the archivingstore_localdir implementation.
 *
 * @package   archivingstore_localdir
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingstore_localdir implementation.
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
     * Creates a new temporary directory for testing purposes.
     *
     * @return string Path to the created temporary directory.
     */
    protected function create_temp_dir(): string {
        $tempdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'archivingstore_localdir_test_' . uniqid();

        if (!mkdir($tempdir) && !is_dir($tempdir)) {
            throw new \RuntimeException("Failed to create temporary directory: {$tempdir}");
        }

        return $tempdir;
    }

    /**
     * Ensures that the correct storage tier is reported.
     *
     * @covers \archivingstore_localdir\archivingstore
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
     * @covers \archivingstore_localdir\archivingstore
     *
     * @return void
     */
    public function test_supports_retrieve(): void {
        $this->assertTrue(archivingstore::supports_retrieve(), 'Storage should support retrieve.');
    }

    /**
     * Tests if the storage driver correctly reports its availability, based on disk space.
     *
     * @covers \archivingstore_localdir\archivingstore
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
     * @covers \archivingstore_localdir\archivingstore
     *
     * @return void
     */
    public function test_get_free_bytes(): void {
        $this->resetAfterTest();
        try {
            // Create temporary directory and use as storage path.
            $tempdir = $this->create_temp_dir();
            set_config('storage_path', $tempdir, 'archivingstore_localdir');

            // Calculate free bytes and check that this roughly matches the disk free space reported by PHP.
            $store = new archivingstore();
            $freebytes = $store->get_free_bytes();
            $this->assertIsInt($freebytes, 'Free bytes should be an integer.');

            $diff = abs($freebytes - disk_free_space($tempdir));
            $this->assertLessThan(1024 * 1024 * 1024, $diff, 'Free bytes should match disk free space within a rough 1 GB margin.');
        } finally {
            // Clean up temp dir.
            if (is_dir($tempdir)) {
                rmdir($tempdir);
            }
        }
    }

    /**
     * Tests storing and retrieving a file.
     *
     * @covers \archivingstore_localdir\archivingstore
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
            // Create temporary directory and use as storage path.
            $tempdir = $this->create_temp_dir();
            set_config('storage_path', $tempdir, 'archivingstore_localdir');

            // Try to store the file.
            $store = new archivingstore();
            $handle = $store->store($job->get_id(), $inputfile, $filepath);
            $this->assertSame($job->get_id(), $handle->jobid, 'Job ID should match.');
            $this->assertSame('localdir', $handle->archivingstorename, 'Archiving store name should match.');
            $this->assertSame($inputfile->get_filename(), $handle->filename, 'Filename should match.');
            $this->assertSame(trim($filepath, '/'), $handle->filepath, 'Filepath should match.');
            $this->assertSame($inputfile->get_filesize(), $handle->filesize, 'Filesize should match.');
            $this->assertSame(storage::hash_file($inputfile), $handle->sha256sum, 'SHA256 hash should match.');
            $this->assertSame($inputfile->get_mimetype(), $handle->mimetype, 'Mimetype should match.');

            // Verify that the file is actually stored in the target directory.
            $storedfilepath = $tempdir . '/' . trim($handle->filepath, '/') . '/' . $handle->filename;
            $this->assertFileExists($storedfilepath, 'Stored file should exist in the target directory.');
            $this->assertSame(
                storage::hash_file($inputfile),
                hash_file('sha256', $storedfilepath),
                'Stored file hash should match original file hash.'
            );

            // Try to retrieve the file.
            $retrievedfile = $store->retrieve($handle, $handle->generate_retrieval_fileinfo_record());
            $this->assertSame($inputfile->get_filename(), $retrievedfile->get_filename(), 'Retrieved filename should match.');
            $this->assertSame($inputfile->get_filesize(), $retrievedfile->get_filesize(), 'Retrieved filesize should match.');
            $this->assertSame($inputfile->get_mimetype(), $retrievedfile->get_mimetype(), 'Retrieved mimetype should match.');
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

            // Clean up temp dir and input file.
            if (is_dir($tempdir)) {
                rmdir($tempdir);
            }
        }
    }

    /**
     * Tests storing and deleting a file.
     *
     * @covers \archivingstore_localdir\archivingstore
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

        try {
            // Create temporary directory and use as storage path.
            $tempdir = $this->create_temp_dir();
            set_config('storage_path', $tempdir, 'archivingstore_localdir');

            // Try to store the file.
            $store = new archivingstore();
            $handle = $store->store($job->get_id(), $inputfile, $filepath);

            $storedfilepath = $tempdir . '/' . trim($handle->filepath, '/') . '/' . $handle->filename;
            $this->assertFileExists($storedfilepath, 'Stored file should exist before deletion.');

            // Try to delete the file and verify that it is gone.
            $store->delete($handle);
            $this->assertFileDoesNotExist($storedfilepath, 'Stored file should not exist after deletion.');

            // Try to delete a non-existing file in non-strict mode (should not throw an error).
            $store->delete($handle, strict: false);

            // Try to delete a non-existing file in strict mode (should throw an error).
            $this->expectException(\local_archiving\exception\storage_exception::class);
            $store->delete($handle, strict: true);
        } finally {
            // Clean up temp dir and input file.
            if (is_dir($tempdir)) {
                rmdir($tempdir);
            }
        }
    }
}
