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

namespace archivingstore_s3\local;

use archivingstore_s3\local\type\connection_status;
use local_archiving\local\exception\storage_exception;

/**
 * Tests for the s3_client class.
 *
 * @package   archivingstore_s3
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the s3_client class.
 */
final class s3_client_test extends \advanced_testcase {
    /**
     * Creates an s3_client instance for testing, with the given constructor argument overrides
     *
     * The default endpoint (127.0.0.1:1) refuses connections immediately.
     *
     * @param array $overrides Constructor argument overrides
     * @return s3_client The created client
     */
    private function create_client(array $overrides = []): s3_client {
        $args = array_merge([
            'endpoint' => '127.0.0.1:1',
            'usetls' => false,
            'verifytls' => false,
            'pathstyle' => true,
            'region' => 'eu-central-1',
            'bucket' => 'mybucket',
            'keyprefix' => '',
            'accesskey' => 'myaccesskey',
            'secretkey' => 'opensesame',
        ], $overrides);

        return new s3_client(...$args);
    }

    /**
     * Reads the value of a private/protected property of an object via reflection
     *
     * @param object $object Object to read the property from
     * @param string $name Name of the property
     * @return mixed Value of the property
     * @throws \ReflectionException
     */
    private function get_private_property(object $object, string $name): mixed {
        $property = new \ReflectionProperty($object, $name);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Invokes a private/protected method of an object via reflection
     *
     * @param object $object Object to invoke the method on
     * @param string $name Name of the method
     * @param array $args Positional arguments to pass to the method
     * @return mixed Return value of the method
     * @throws \ReflectionException
     */
    private function invoke_private_method(object $object, string $name, array $args = []): mixed {
        $method = new \ReflectionMethod($object, $name);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    /**
     * Creates a temporary local file with the given content
     *
     * @param string $content Content to write to the file
     * @return string Absolute path of the created file
     */
    private function create_temp_file_with_content(string $content): string {
        $path = tempnam(sys_get_temp_dir(), 'archivingstore_s3_test_');
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Creates a sparse local file that reports the given size without allocating real disk space for it
     *
     * @param int $size Desired file size in bytes
     * @return string Absolute path of the created file
     */
    private function create_sparse_file(int $size): string {
        $path = tempnam(sys_get_temp_dir(), 'archivingstore_s3_test_');
        $fp = fopen($path, 'r+');
        fseek($fp, $size - 1);
        fwrite($fp, "\0");
        fclose($fp);
        return $path;
    }

    /**
     * Tests parsing of "bucket[/optional/prefix]" strings.
     *
     * @covers \archivingstore_s3\local\s3_client
     * @dataProvider parse_bucket_path_data_provider
     *
     * @param string $raw Raw bucket path string to parse
     * @param string|null $expectedbucket Expected bucket name, or null if a \moodle_exception is expected
     * @param string|null $expectedprefix Expected key prefix, or null if a \moodle_exception is expected
     * @return void
     * @throws \moodle_exception
     */
    public function test_parse_bucket_path(string $raw, ?string $expectedbucket, ?string $expectedprefix): void {
        if ($expectedbucket === null) {
            $this->expectException(\moodle_exception::class);
            s3_client::parse_bucket_path($raw);
            return;
        }

        $result = s3_client::parse_bucket_path($raw);
        $this->assertSame($expectedbucket, $result->bucket, 'Bucket name should match.');
        $this->assertSame($expectedprefix, $result->prefix, 'Key prefix should match.');
    }

    /**
     * Data provider for test_parse_bucket_path
     *
     * @return array Data sets
     */
    public static function parse_bucket_path_data_provider(): array {
        return [
            // Valid cases.
            'bucket only' => ['mybucket', 'mybucket', ''],
            'bucket with prefix' => ['mybucket/folder', 'mybucket', 'folder'],
            'bucket with nested prefix' => ['mybucket/a/b/c', 'mybucket', 'a/b/c'],
            's3 scheme stripped' => ['s3://mybucket/folder', 'mybucket', 'folder'],
            's3 scheme stripped case-insensitively' => ['S3://mybucket', 'mybucket', ''],
            'surrounding slashes trimmed' => ['/mybucket/folder/', 'mybucket', 'folder'],
            'surrounding whitespace trimmed' => ['  mybucket  ', 'mybucket', ''],
            'minimum length bucket (3 chars)' => ['abc', 'abc', ''],
            'maximum length bucket (63 chars)' => [str_repeat('a', 63), str_repeat('a', 63), ''],
            'bucket with dots and hyphens' => ['my-bucket.name', 'my-bucket.name', ''],
            // Invalid cases.
            'empty string' => ['', null, null],
            'whitespace only' => ['   ', null, null],
            'bucket too short (2 chars)' => ['ab', null, null],
            'bucket too long (64 chars)' => [str_repeat('a', 64), null, null],
            'bucket with uppercase' => ['MyBucket', null, null],
            'bucket with invalid characters' => ['my_bucket', null, null],
            'prefix with dot-dot' => ['mybucket/../secret', null, null],
            'prefix with double slash' => ['mybucket/a//b', null, null],
            'prefix starting with slash' => ['mybucket//a', null, null],
            'prefix with control character' => ["mybucket/a\x01b", null, null],
        ];
    }

    /**
     * Tests that instance() throws when no configuration is present at all.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \dml_exception
     * @throws storage_exception
     * @throws \moodle_exception
     */
    public function test_instance_throws_when_not_configured(): void {
        $this->resetAfterTest();
        $this->expectException(storage_exception::class);
        s3_client::instance();
    }

    /**
     * Tests that instance() throws when only some required settings are present.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws storage_exception
     */
    public function test_instance_throws_with_partial_config(): void {
        $this->resetAfterTest();
        set_config('endpoint', 'example.com', 'archivingstore_s3');
        set_config('region', 'us-east-1', 'archivingstore_s3');
        // Settings bucket_path, access_key, secret_key intentionally left unset.

        $this->expectException(storage_exception::class);
        s3_client::instance();
    }

    /**
     * Tests that instance() correctly builds a client from a full, valid configuration.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws storage_exception
     */
    public function test_instance_builds_from_config(): void {
        $this->resetAfterTest();
        set_config('endpoint', 'example.com:9000', 'archivingstore_s3');
        set_config('region', 'eu-central-1', 'archivingstore_s3');
        set_config('use_tls', '1', 'archivingstore_s3');
        set_config('verify_tls', '0', 'archivingstore_s3');
        set_config('path_style', '1', 'archivingstore_s3');
        set_config('bucket_path', 'mybucket/some/prefix', 'archivingstore_s3');
        set_config('access_key', 'myaccesskey', 'archivingstore_s3');
        set_config('secret_key', 'mysecretkey', 'archivingstore_s3');

        $client = s3_client::instance();
        $this->assertInstanceOf(s3_client::class, $client);

        $this->assertSame('example.com:9000', $this->get_private_property($client, 'endpoint'));
        $this->assertSame('eu-central-1', $this->get_private_property($client, 'region'));
        $this->assertTrue($this->get_private_property($client, 'usetls'));
        $this->assertFalse($this->get_private_property($client, 'verifytls'));
        $this->assertTrue($this->get_private_property($client, 'pathstyle'));
        $this->assertSame('mybucket', $this->get_private_property($client, 'bucket'));
        $this->assertSame('some/prefix', $this->get_private_property($client, 'keyprefix'));
        $this->assertSame('myaccesskey', $this->get_private_property($client, 'accesskey'));
        $this->assertSame('mysecretkey', $this->get_private_property($client, 'secretkey'));
    }

    /**
     * Tests that check_connection() classifies an unreachable endpoint correctly.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \Exception
     */
    public function test_check_connection_unreachable(): void {
        $client = $this->create_client();
        $result = $client->check_connection();

        $this->assertSame(
            connection_status::CONNECTION_ERROR,
            $result->status,
            'Unreachable endpoint should be classified as a connection error.'
        );
        $this->assertNotNull($result->message, 'A connection error should carry a descriptive message.');
        $this->assertFalse($result->is_ok(), 'Unreachable endpoint should not be considered ok.');
    }

    /**
     * Tests that put_object() rejects a local file that cannot be read.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \coding_exception
     * @throws storage_exception
     */
    public function test_put_object_invalid_local_file(): void {
        $client = $this->create_client();
        $missingpath = sys_get_temp_dir() . '/archivingstore_s3_test_missing_' . uniqid();

        $this->expectException(storage_exception::class);
        $client->put_object('some/key.txt', $missingpath, str_repeat('a', 64));
    }

    /**
     * Tests that put_object() rejects a local file exceeding the maximum supported upload size.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \coding_exception
     * @throws storage_exception
     */
    public function test_put_object_too_large(): void {
        $path = $this->create_sparse_file(s3_client::MAX_PUT_OBJECT_SIZE + 1);

        try {
            $client = $this->create_client();
            $this->expectException(storage_exception::class);
            $client->put_object('some/key.txt', $path, str_repeat('a', 64));
        } finally {
            @unlink($path);
        }
    }

    /**
     * Tests that put_object() propagates a storage_exception when the endpoint is unreachable.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \coding_exception
     * @throws storage_exception
     */
    public function test_put_object(): void {
        $client = $this->create_client();
        $path = $this->create_temp_file_with_content('hello world');

        try {
            $this->expectException(storage_exception::class);
            $client->put_object('some/key.txt', $path, hash_file('sha256', $path));
        } finally {
            @unlink($path);
        }
    }

    /**
     * Tests that get_object() propagates a storage_exception when the endpoint is unreachable.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     */
    public function test_get_object(): void {
        $client = $this->create_client();
        $destpath = sys_get_temp_dir() . '/archivingstore_s3_test_get_' . uniqid();

        try {
            $this->expectException(storage_exception::class);
            $client->get_object('some/key.txt', $destpath);
        } finally {
            @unlink($destpath);
        }
    }

    /**
     * Tests that delete_object() propagates a storage_exception when the endpoint is unreachable.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     */
    public function test_delete_object(): void {
        $client = $this->create_client();

        $this->expectException(storage_exception::class);
        $client->delete_object('some/key.txt');
    }

    /**
     * Tests that object_exists() propagates a storage_exception when the endpoint is unreachable.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     */
    public function test_object_exists(): void {
        $client = $this->create_client();

        $this->expectException(storage_exception::class);
        $client->object_exists('some/key.txt');
    }

    /**
     * Tests parsing of S3 XML error response bodies.
     *
     * @covers \archivingstore_s3\local\s3_client
     * @dataProvider parse_error_message_data_provider
     *
     * @param string $body Raw HTTP response body
     * @param string|null $expected Expected parsed message
     * @return void
     * @throws \ReflectionException
     */
    public function test_parse_error_message(string $body, ?string $expected): void {
        $client = $this->create_client();
        $result = $this->invoke_private_method($client, 'parse_error_message', [$body]);

        $this->assertSame($expected, $result);
    }

    /**
     * Data provider for test_parse_error_message
     *
     * @return array Data sets
     */
    public static function parse_error_message_data_provider(): array {
        return [
            'empty body' => ['', null],
            'whitespace only body' => ['   ', null],
            'xml with message' => [
                '<Error><Code>NoSuchBucket</Code><Message>The specified bucket does not exist</Message></Error>',
                'The specified bucket does not exist',
            ],
            'xml with only code' => ['<Error><Code>AccessDenied</Code></Error>', 'AccessDenied'],
            'short non-xml body' => ['plain text error', 'plain text error'],
        ];
    }

    /**
     * Tests that a non-XML error body longer than 200 characters is truncated.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_parse_error_message_truncates_long_body(): void {
        $client = $this->create_client();
        $body = str_repeat('a', 250);
        $result = $this->invoke_private_method($client, 'parse_error_message', [$body]);

        $this->assertSame(200, strlen($result), 'Truncated message should be exactly 200 characters long.');
        $this->assertStringEndsWith('...', $result, 'Truncated message should end with an ellipsis.');
    }

    /**
     * Tests canonical request path construction for path-style addressing.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_canonical_path_path_style(): void {
        $client = $this->create_client(['pathstyle' => true, 'bucket' => 'mybucket', 'keyprefix' => '']);

        $this->assertSame('/mybucket', $this->invoke_private_method($client, 'canonical_path', ['']));
        $this->assertSame('/mybucket/file.txt', $this->invoke_private_method($client, 'canonical_path', ['file.txt']));
        $this->assertSame(
            '/mybucket/folder/sub%20file.txt',
            $this->invoke_private_method($client, 'canonical_path', ['folder/sub file.txt'])
        );
    }

    /**
     * Tests canonical request path construction for virtual-hosted-style addressing.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_canonical_path_virtual_hosted_style(): void {
        $client = $this->create_client(['pathstyle' => false, 'bucket' => 'mybucket', 'keyprefix' => '']);

        $this->assertSame('/', $this->invoke_private_method($client, 'canonical_path', ['']));
        $this->assertSame('/file.txt', $this->invoke_private_method($client, 'canonical_path', ['file.txt']));
    }

    /**
     * Tests canonical request path construction with a configured key prefix.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_canonical_path_with_key_prefix(): void {
        $client = $this->create_client(['pathstyle' => false, 'bucket' => 'mybucket', 'keyprefix' => 'pre/fix']);

        $this->assertSame('/pre/fix/file.txt', $this->invoke_private_method($client, 'canonical_path', ['file.txt']));
    }

    /**
     * Tests combination of the configured key prefix with a caller-supplied key.
     *
     * @covers \archivingstore_s3\local\s3_client
     * @dataProvider full_key_data_provider
     *
     * @param string $keyprefix Configured key prefix
     * @param string $key Caller-supplied key
     * @param string $expected Expected combined key
     * @return void
     * @throws \ReflectionException
     */
    public function test_full_key(string $keyprefix, string $key, string $expected): void {
        $client = $this->create_client(['keyprefix' => $keyprefix]);
        $this->assertSame($expected, $this->invoke_private_method($client, 'full_key', [$key]));
    }

    /**
     * Data provider for test_full_key
     *
     * @return array Data sets
     */
    public static function full_key_data_provider(): array {
        return [
            'no prefix' => ['', 'foo/bar/', 'foo/bar'],
            'with prefix' => ['pre', '/foo', 'pre/foo'],
            'empty key, no prefix' => ['', '', ''],
        ];
    }

    /**
     * Tests request host construction for path-style addressing (endpoint used as-is).
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_request_host_path_style(): void {
        $client = $this->create_client(['pathstyle' => true, 'endpoint' => 'example.com:9000', 'bucket' => 'mybucket']);
        $this->assertSame('example.com:9000', $this->invoke_private_method($client, 'request_host'));
    }

    /**
     * Tests request host construction for virtual-hosted-style addressing, with and without a port.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_request_host_virtual_hosted_style(): void {
        $client = $this->create_client(['pathstyle' => false, 'endpoint' => 'example.com:9000', 'bucket' => 'mybucket']);
        $this->assertSame('mybucket.example.com:9000', $this->invoke_private_method($client, 'request_host'));

        $client = $this->create_client(['pathstyle' => false, 'endpoint' => 'example.com', 'bucket' => 'mybucket']);
        $this->assertSame('mybucket.example.com', $this->invoke_private_method($client, 'request_host'));
    }

    /**
     * Tests canonical query string construction (key sorting and encoding).
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_canonical_query_string(): void {
        $client = $this->create_client();

        $this->assertSame('', $this->invoke_private_method($client, 'canonical_query_string', [[]]));
        $this->assertSame(
            'a=1&b=2',
            $this->invoke_private_method($client, 'canonical_query_string', [['b' => '2', 'a' => '1']])
        );
        $this->assertSame(
            'key%20with%20space=val%2Fslash',
            $this->invoke_private_method($client, 'canonical_query_string', [['key with space' => 'val/slash']])
        );
    }

    /**
     * Tests the URL scheme selection based on the TLS setting.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_scheme(): void {
        $client = $this->create_client(['usetls' => true]);
        $this->assertSame('https', $this->invoke_private_method($client, 'scheme'));

        $client = $this->create_client(['usetls' => false]);
        $this->assertSame('http', $this->invoke_private_method($client, 'scheme'));
    }

    /**
     * Tests splitting of the configured endpoint into host and optional port segments.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_split_endpoint(): void {
        $client = $this->create_client(['endpoint' => 'example.com:9000']);
        $this->assertSame(['example.com', '9000'], $this->invoke_private_method($client, 'split_endpoint'));

        $client = $this->create_client(['endpoint' => 'example.com']);
        $this->assertSame(['example.com', null], $this->invoke_private_method($client, 'split_endpoint'));
    }

    /**
     * Tests formatting of an associative header array into curl's "Name: Value" list format.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_format_http_headers(): void {
        $client = $this->create_client();
        $result = $this->invoke_private_method($client, 'format_http_headers', [[
            'Host' => 'example.com',
            'X-Hello-World' => '42',
        ]]);

        $this->assertSame(['Host: example.com', 'X-Hello-World: 42'], $result);
    }

    /**
     * Tests that build_signed_headers() produces a well-formed set of SigV4 headers.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_build_signed_headers(): void {
        $client = $this->create_client(['pathstyle' => true, 'bucket' => 'mybucket', 'region' => 'us-east-1']);
        $emptypayloadhash = hash('sha256', '');

        $headers = $this->invoke_private_method($client, 'build_signed_headers', [
            'GET',
            '/mybucket',
            [],
            $emptypayloadhash,
        ]);

        $this->assertSame(
            $this->invoke_private_method($client, 'request_host'),
            $headers['Host'],
            'Host header should match the request host.'
        );
        $this->assertSame($emptypayloadhash, $headers['X-Amz-Content-Sha256']);
        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}Z$/', $headers['X-Amz-Date']);
        $this->assertMatchesRegularExpression(
            '/^AWS4-HMAC-SHA256 Credential=myaccesskey\/\d{8}\/us-east-1\/s3\/aws4_request, ' .
                'SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature=[0-9a-f]{64}$/',
            $headers['Authorization']
        );
    }

    /**
     * Tests derivation of the SigV4 signing key against an independently computed golden value.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_signing_key(): void {
        // Golden value independently computed (not copied from the implementation) for:
        // secretkey=opensesame, region=eu-central-1, service=s3, datestamp=20260827.
        $client = $this->create_client([
            'region' => 'eu-central-1',
            'secretkey' => 'opensesame',
        ]);

        $result = $this->invoke_private_method($client, 'signing_key', ['20260827']);

        $this->assertSame(
            '42c7d96e7631335a3beefa2181add7e73ee0c4404356526d47d203dfcadd1b11',
            bin2hex($result)
        );
    }

    /**
     * Tests the curl progress-callback option builder for both upload and download directions.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_progress_curl_option(): void {
        $client = $this->create_client();
        $capture = (object) ['exception' => null];

        // No callback given: no options should be set.
        $this->assertSame([], $this->invoke_private_method($client, 'progress_curl_option', [null, true, $capture]));

        // Upload progress should report (uploadnow, uploadtotal).
        $captured = null;
        $callback = function (int $current, int $total) use (&$captured) {
            $captured = [$current, $total];
        };
        $options = $this->invoke_private_method($client, 'progress_curl_option', [$callback, true, $capture]);
        $this->assertFalse($options['CURLOPT_NOPROGRESS']);
        $returnvalue = ($options['CURLOPT_XFERINFOFUNCTION'])(null, 1000, 200, 500, 300);
        $this->assertSame(0, $returnvalue, 'Progress callback must return 0 to keep the transfer going.');
        $this->assertSame([300, 500], $captured, 'Upload progress should report (uploadnow, uploadtotal).');
        $this->assertNull($capture->exception);

        // Download progress should report (downloadnow, downloadtotal).
        $captured = null;
        $options = $this->invoke_private_method($client, 'progress_curl_option', [$callback, false, $capture]);
        ($options['CURLOPT_XFERINFOFUNCTION'])(null, 1000, 200, 500, 300);
        $this->assertSame([200, 1000], $captured, 'Download progress should report (downloadnow, downloadtotal).');
    }

    /**
     * Tests that a progress callback throwing is translated into the libcurl abort signal
     * (non-zero) with the exception stashed in $capture, and that a non-throwing callback returns
     * "continue" (0) and leaves $capture untouched, for both upload and download.
     *
     * @covers \archivingstore_s3\local\s3_client
     *
     * @return void
     * @throws \ReflectionException
     */
    public function test_progress_curl_option_propagates_abort(): void {
        $client = $this->create_client();

        foreach ([true, false] as $upload) {
            // Non-throwing callback: continue, nothing captured.
            $capture = (object) ['exception' => null];
            $callback = fn (int $current, int $total) => null;
            $options = $this->invoke_private_method($client, 'progress_curl_option', [$callback, $upload, $capture]);
            $returnvalue = ($options['CURLOPT_XFERINFOFUNCTION'])(null, 1000, 200, 500, 300);
            $this->assertSame(
                0,
                $returnvalue,
                ($upload ? 'Upload' : 'Download') . ' xferinfo function should return 0 when the callback does not throw'
            );
            $this->assertNull($capture->exception);

            // Throwing callback: abort, exception captured unchanged.
            $capture = (object) ['exception' => null];
            $exception = new storage_exception('error_retrieval_cancelled', 'local_archiving');
            $callback = function (int $current, int $total) use ($exception) {
                throw $exception;
            };
            $options = $this->invoke_private_method($client, 'progress_curl_option', [$callback, $upload, $capture]);
            $returnvalue = ($options['CURLOPT_XFERINFOFUNCTION'])(null, 1000, 200, 500, 300);
            $this->assertSame(
                1,
                $returnvalue,
                ($upload ? 'Upload' : 'Download') . ' xferinfo function should return 1 when the callback throws'
            );
            $this->assertSame($exception, $capture->exception);
        }
    }
}
