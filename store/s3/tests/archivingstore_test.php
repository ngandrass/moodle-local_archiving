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
use local_archiving\local\logging\job_logger;
use local_archiving\local\type\log_level;

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
     * Tests that store() propagates a storage_exception when the S3 endpoint is unreachable.
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
    public function test_store_network_failure(): void {
        $this->resetAfterTest();
        $this->set_valid_config();
        $job = $this->generator()->create_archive_job();
        $inputfile = $this->generator()->create_temp_file();

        $store = new archivingstore();
        $this->expectException(storage_exception::class);
        $store->store($job->get_id(), $inputfile, '/foo/bar');
    }

    /**
     * Tests that retrieve() propagates a storage_exception when the S3 endpoint is unreachable.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws storage_exception
     */
    public function test_retrieve_network_failure(): void {
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
        $this->expectException(storage_exception::class);
        $store->retrieve($handle, $fileinfo);
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
    public function test_delete_non_strict_network_failure(): void {
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
    public function test_delete_strict_network_failure(): void {
        $this->resetAfterTest();
        $this->set_valid_config();
        $handle = $this->generator()->create_file_handle(['archivingstorename' => 's3']);

        $store = new archivingstore();
        $this->expectException(storage_exception::class);
        $store->delete($handle, strict: true);
    }

    /**
     * Invokes the private upload_progress_callback() method to obtain a progress callback
     *
     * @param archivingstore $store Store instance to invoke the method on
     * @param int $jobid Job ID to build the callback for
     * @return callable The built progress callback
     * @throws \ReflectionException
     */
    private function invoke_upload_progress_callback(archivingstore $store, int $jobid): callable {
        $method = new \ReflectionMethod(archivingstore::class, 'upload_progress_callback');
        $method->setAccessible(true);
        return $method->invoke($store, $jobid);
    }

    /**
     * Tests that no log entry is written when the reported total size is zero or negative.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \ReflectionException
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_upload_progress_callback_zero_total(): void {
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $job = $this->generator()->create_archive_job();

        $callback = $this->invoke_upload_progress_callback(new archivingstore(), $job->get_id());
        $callback(0, 0);

        $this->assertCount(0, (new job_logger($job->get_id()))->get_logs(), 'No log entry should be written.');
    }

    /**
     * Tests that the first observed progress tick is always logged.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \ReflectionException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_upload_progress_callback_logs_first_tick(): void {
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $job = $this->generator()->create_archive_job();

        $callback = $this->invoke_upload_progress_callback(new archivingstore(), $job->get_id());
        $callback(50, 200);

        $logs = array_values((new job_logger($job->get_id()))->get_logs());
        $this->assertCount(1, $logs, 'Expected exactly one log entry after the first progress tick.');
        $this->assertEquals(log_level::INFO->value, $logs[0]->level, 'Progress should be logged at INFO level.');
        $this->assertEquals($job->get_id(), $logs[0]->jobid, 'Log entry should be linked to the job.');
        $this->assertStringContainsString('25%', $logs[0]->message, 'Log message does not include percentage.');
    }

    /**
     * Tests that intermediate progress ticks are throttled to at most one log entry every 10 seconds.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \ReflectionException
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_upload_progress_callback_throttles_intermediate_ticks(): void {
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $job = $this->generator()->create_archive_job();

        $callback = $this->invoke_upload_progress_callback(new archivingstore(), $job->get_id());
        $callback(50, 200);
        $callback(100, 200);

        $this->assertCount(
            1,
            (new job_logger($job->get_id()))->get_logs(),
            'A second intermediate progress tick within 10 seconds should not be logged again.'
        );
    }

    /**
     * Tests that the final 100% completion tick is always logged, even within the throttle window.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \ReflectionException
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_upload_progress_callback_always_logs_completion(): void {
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $job = $this->generator()->create_archive_job();

        $callback = $this->invoke_upload_progress_callback(new archivingstore(), $job->get_id());
        $callback(50, 200);
        $callback(200, 200);

        $logs = array_values((new job_logger($job->get_id()))->get_logs());
        $this->assertCount(2, $logs, 'Completion should be logged even though it is within the throttle window.');
        $this->assertStringContainsString('100%', $logs[1]->message, 'Second log entry should report 100% completion.');
    }

    /**
     * Tests that the completion tick is only logged once, even if reported multiple times.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \ReflectionException
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_upload_progress_callback_completion_logged_once(): void {
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $job = $this->generator()->create_archive_job();

        $callback = $this->invoke_upload_progress_callback(new archivingstore(), $job->get_id());
        $callback(200, 200);
        $callback(200, 200);

        $this->assertCount(
            1,
            (new job_logger($job->get_id()))->get_logs(),
            'A repeated completion tick should not be logged again.'
        );
    }

    /**
     * Tests that separate calls to upload_progress_callback() produce independent closures that do not share state.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     * @throws \ReflectionException
     * @throws \dml_exception
     */
    public function test_upload_progress_callback_independent_instances(): void {
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $job1 = $this->generator()->create_archive_job();
        $job2 = $this->generator()->create_archive_job();

        $store = new archivingstore();
        $callback1 = $this->invoke_upload_progress_callback($store, $job1->get_id());
        $callback2 = $this->invoke_upload_progress_callback($store, $job2->get_id());

        $callback1(50, 200);
        $callback2(100, 400);

        $logs1 = array_values((new job_logger($job1->get_id()))->get_logs());
        $logs2 = array_values((new job_logger($job2->get_id()))->get_logs());

        $this->assertCount(1, $logs1, 'First job should have exactly one log entry.');
        $this->assertCount(1, $logs2, 'Second job should have exactly one log entry.');
        $this->assertStringContainsString('25%', $logs1[0]->message, 'First job log should report its own progress.');
        $this->assertStringContainsString('25%', $logs2[0]->message, 'Second job log should report its own progress.');
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
