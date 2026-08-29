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

namespace archivingstore_s3;


use local_archiving\local\exception\storage_exception;

/**
 * Tests for the archivingstore_s3 implementation.
 *
 * @package   archivingstore_s3
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingstore_s3 implementation.
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
     * Sets a full, valid archivingstore_s3 configuration, with the given overrides applied on top.
     *
     * The default endpoint (127.0.0.1:1) refuses connections immediately, which is used throughout this test
     * class to exercise network-failure code paths without depending on external infrastructure.
     *
     * @param array $overrides Config key => value pairs to override the defaults with
     * @return void
     */
    private function set_valid_config(array $overrides = []): void {
        $config = array_merge([
            'endpoint' => '127.0.0.1:1',
            'region' => 'eu-central-1',
            'use_tls' => '0',
            'verify_tls' => '0',
            'path_style' => '1',
            'bucket_path' => 'mybucket',
            'access_key' => 'myaccesskey',
            'secret_key' => 'opensesame',
        ], $overrides);

        foreach ($config as $key => $value) {
            set_config($key, $value, 'archivingstore_s3');
        }
    }

    /**
     * Ensures that the correct storage tier is reported.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     */
    public function test_get_storage_tier(): void {
        $this->assertEquals(
            \local_archiving\local\type\storage_tier::REMOTE_FAST,
            archivingstore::get_storage_tier(),
            'Storage tier should be REMOTE_FAST.'
        );
    }

    /**
     * Ensures that the storage reports that it supports retrieval.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     */
    public function test_supports_retrieve(): void {
        $this->assertTrue(archivingstore::supports_retrieve(), 'Storage should support retrieve.');
    }

    /**
     * Tests that is_configured() correctly reflects the presence of all required settings.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_is_configured(): void {
        $this->resetAfterTest();

        $this->assertFalse(archivingstore::is_configured(), 'Should not be configured with no settings set.');

        $config = [
            'endpoint' => 'example.com',
            'region' => 'eu-central-1',
            'bucket_path' => 'mybucket',
            'access_key' => 'myaccesskey',
            'secret_key' => 'opensesame',
        ];
        foreach ($config as $key => $value) {
            set_config($key, $value, 'archivingstore_s3');
        }
        $this->assertTrue(archivingstore::is_configured(), 'Should be configured once all required settings are set.');

        // Removing any single required setting should make the driver unconfigured again.
        foreach (array_keys($config) as $missingkey) {
            set_config($missingkey, '', 'archivingstore_s3');
            $this->assertFalse(
                archivingstore::is_configured(),
                "Should not be configured when '{$missingkey}' is empty."
            );
            set_config($missingkey, $config[$missingkey], 'archivingstore_s3');
        }
    }

    /**
     * Tests that is_ready() delegates to is_configured().
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     */
    public function test_is_ready(): void {
        $this->resetAfterTest();

        $this->assertFalse(archivingstore::is_ready(), 'Should not be ready with no settings set.');

        $this->set_valid_config();
        $this->assertTrue(archivingstore::is_ready(), 'Should be ready once fully configured.');
    }

    /**
     * Tests that an unconfigured storage driver is reported as unavailable.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     */
    public function test_is_available_not_configured(): void {
        $this->resetAfterTest();

        $store = new archivingstore();
        $this->assertFalse($store->is_available(), 'Should not be available when not configured.');
    }

    /**
     * Tests that a configured driver an unreachable s3 endpoint is reported as unavailable.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     */
    public function test_is_available_unreachable(): void {
        $this->resetAfterTest();
        $this->set_valid_config();

        $store = new archivingstore();
        $this->assertFalse($store->is_available(), 'Should not be available when the endpoint is unreachable.');
    }

    /**
     * Tests that S3 storage never reports a meaningful free byte count.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     */
    public function test_get_free_bytes(): void {
        $store = new archivingstore();
        $this->assertNull($store->get_free_bytes(), 'S3 storage should report null free bytes.');
    }

    /**
     * Tests that store() propagates a storage_exception when the S3 endpoint is unreachable
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws storage_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function test_store(): void {
        $this->resetAfterTest();
        $this->set_valid_config();
        $job = $this->generator()->create_archive_job();
        $inputfile = $this->generator()->create_temp_file();

        $store = new archivingstore();
        try {
            $store->store($job->get_id(), $inputfile, '/foo/bar');
            $this->fail('Expected a storage_exception to be thrown.');
        } catch (storage_exception) { // phpcs:ignore
            // Expected.
        }
    }

    /**
     * Tests that retrieve() propagates a storage_exception when the S3 endpoint is unreachable
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws storage_exception
     */
    public function test_retrieve(): void {
        $this->resetAfterTest();
        $this->set_valid_config();
        $handle = $this->generator()->create_file_handle(['archivingstorename' => 's3']);
        $fileinfo = (object) [
            'contextid' => \context_system::instance()->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $handle->filename,
        ];

        $store = new archivingstore();
        try {
            $store->retrieve($handle, $fileinfo);
            $this->fail('Expected a storage_exception to be thrown.');
        } catch (storage_exception) { // phpcs:ignore
            // Expected.
        }
    }

    /**
     * Tests that a non-strict delete() propagates a storage_exception when the S3 endpoint is unreachable.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws storage_exception
     */
    public function test_delete_non_strict(): void {
        $this->resetAfterTest();
        $this->set_valid_config();
        $handle = $this->generator()->create_file_handle(['archivingstorename' => 's3']);

        $store = new archivingstore();
        $this->expectException(storage_exception::class);
        $store->delete($handle, strict: false);
    }

    /**
     * Tests that a strict delete() propagates a storage_exception when the S3 endpoint is unreachable.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws storage_exception
     */
    public function test_delete_strict(): void {
        $this->resetAfterTest();
        $this->set_valid_config();
        $handle = $this->generator()->create_file_handle(['archivingstorename' => 's3']);

        $store = new archivingstore();
        $this->expectException(storage_exception::class);
        $store->delete($handle, strict: true);
    }

    /**
     * Tests the derivation of S3 object keys from a file handle's path and filename.
     *
     * @covers \archivingstore_s3\archivingstore
     * @dataProvider s3_object_key_data_provider
     *
     * @param string $filepath Filepath to derive the object key from
     * @param string $filename Filename to derive the object key from
     * @param string $expected Expected object key
     * @return void
     * @throws \ReflectionException
     */
    public function test_s3_object_key(string $filepath, string $filename, string $expected): void {
        $store = new archivingstore();
        $method = new \ReflectionMethod(archivingstore::class, 's3_object_key');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($store, $filepath, $filename), 'S3 object key should match.');
    }

    /**
     * Data provider for test_s3_object_key
     *
     * @return array Data sets
     */
    public static function s3_object_key_data_provider(): array {
        return [
            'no path' => ['', 'file.txt', 'file.txt'],
            'root slash only' => ['/', 'file.txt', 'file.txt'],
            'simple path' => ['foo', 'file.txt', 'foo/file.txt'],
            'nested path' => ['foo/bar', 'file.txt', 'foo/bar/file.txt'],
            'leading slash' => ['/foo/bar', 'file.txt', 'foo/bar/file.txt'],
            'trailing slash' => ['foo/bar/', 'file.txt', 'foo/bar/file.txt'],
            'leading and trailing slash' => ['/foo/bar/', 'file.txt', 'foo/bar/file.txt'],
        ];
    }
}
