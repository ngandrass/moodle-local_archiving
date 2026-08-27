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

/**
 * Minimal S3-compatible REST client with SigV4 signing
 *
 * @package     archivingstore_s3
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_s3\local;

use archivingstore_s3\local\type\connection_status;
use curl;
use local_archiving\local\exception\storage_exception;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Minimal S3-compatible REST client
 */
final class s3_client {
    /** @var int Maximum object size supported via a single PUT request (5 GiB - 1 byte) */
    public const MAX_PUT_OBJECT_SIZE = 5 * 1024 * 1024 * 1024 - 1;

    /** @var string SHA-256 hash of an empty string, used as the payload hash for requests without a body */
    private const EMPTY_PAYLOAD_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    /** @var string AWS service name used for request signing */
    private const SERVICE = 's3';

    /**
     * Creates a new S3 client instance
     *
     * @param string $endpoint Bare hostname of the S3 endpoint, optionally followed by ":port"
     * @param bool $usetls Whether to connect via HTTPS instead of HTTP
     * @param bool $verifytls Whether to verify the endpoint's TLS certificate
     * @param bool $pathstyle Whether to use path-style addressing (bucket in the URL path) instead of
     * virtual-hosted-style addressing (bucket in the hostname)
     * @param string $region Region to use for request signing
     * @param string $bucket Name of the bucket to operate on
     * @param string $keyprefix Key prefix (subfolder) within the bucket, without leading/trailing slashes,
     * or an empty string for no prefix
     * @param string $accesskey Access key used to authenticate requests
     * @param string $secretkey Secret key used to authenticate requests
     */
    public function __construct(
        /** @var string $endpoint Bare hostname of the S3 endpoint, optionally followed by ":port" */
        private readonly string $endpoint,
        /** @var bool $usetls Whether to connect via HTTPS instead of HTTP */
        private readonly bool $usetls,
        /** @var bool $verifytls Whether to verify the endpoint's TLS certificate */
        private readonly bool $verifytls,
        /** @var bool $pathstyle Whether to use path-style addressing (bucket in the URL path) instead of
         * virtual-hosted-style addressing (bucket in the hostname) */
        private readonly bool $pathstyle,
        /** @var string $region Region to use for request signing */
        private readonly string $region,
        /** @var string $bucket Name of the bucket to operate on */
        private readonly string $bucket,
        /** @var string $keyprefix Key prefix (subfolder) within the bucket, without leading/trailing slashes,
         * or an empty string for no prefix */
        private readonly string $keyprefix,
        /** @var string $accesskey Access key used to authenticate requests */
        private readonly string $accesskey,
        /** @var string $secretkey Secret key used to authenticate requests */
        private readonly string $secretkey
    ) {
    }

    /**
     * Builds a new S3 client from the archivingstore_s3 plugin configuration
     *
     * @return s3_client The created client instance
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws storage_exception If required settings are missing or malformed
     */
    public static function instance(): self {
        $config = get_config('archivingstore_s3');

        foreach (['endpoint', 'region', 'bucket_path', 'access_key', 'secret_key'] as $key) {
            if (empty($config->$key)) {
                throw new storage_exception('error_s3_not_configured', 'archivingstore_s3');
            }
        }

        $bucketpath = self::parse_bucket_path($config->bucket_path);

        return new self(
            endpoint: $config->endpoint,
            usetls: !empty($config->use_tls),
            verifytls: !empty($config->verify_tls),
            pathstyle: !empty($config->path_style),
            region: $config->region,
            bucket: $bucketpath->bucket,
            keyprefix: $bucketpath->prefix,
            accesskey: $config->access_key,
            secretkey: $config->secret_key
        );
    }

    /**
     * Checks whether the configured endpoint is reachable and the configured
     * bucket is accessible with the configured credentials
     *
     * Performs an authenticated ListObjectsV2 request with a result limit of 0
     * against the bucket. This is used, rather than a plain HEAD request,
     * because Moodle's curl::head() suppresses the response body, which would
     * leave failures with no parseable detail.
     *
     * @return connection_check_result Result of the connection check
     * @throws \Exception On unexpected date generation error
     */
    public function check_connection(): connection_check_result {
        // Prepare request headers.
        $path = $this->canonical_path();
        $query = ['list-type' => '2', 'max-keys' => '0'];
        $headers = $this->build_signed_headers('GET', $path, $query, self::EMPTY_PAYLOAD_SHA256);

        // Perform ListObjectV2 request.
        $c = new curl(['ignoresecurity' => true]);
        $body = $c->get($this->request_url($path, $query), [], [
            'CURLOPT_HTTPHEADER' => $this->format_http_headers($headers),
            'CURLOPT_SSL_VERIFYPEER' => $this->verifytls,
            'CURLOPT_SSL_VERIFYHOST' => $this->verifytls ? 2 : 0,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_TIMEOUT' => 15,
        ]);

        // Handle generic curl error.
        if (!empty($c->error)) {
            return new connection_check_result(connection_status::CONNECTION_ERROR, $c->error);
        }

        // @codeCoverageIgnoreStart

        // If we got a 2xx response, the endpoint is reachable and the bucket is accessible with the given credentials.
        $httpcode = (int) ($c->get_info()['http_code'] ?? 0);
        if ($httpcode >= 200 && $httpcode < 300) {
            return new connection_check_result(connection_status::OK);
        }

        // Otherwise, parse the S3 error message and classify the failure.
        $message = $this->parse_error_message((string) $body);
        return match ($httpcode) {
            403 => new connection_check_result(connection_status::AUTH_ERROR, $message),
            404 => new connection_check_result(connection_status::BUCKET_NOT_FOUND, $message),
            default => new connection_check_result(
                connection_status::UNEXPECTED_ERROR,
                "HTTP {$httpcode}" . ($message !== null ? ": {$message}" : '')
            ),
        };
        // @codeCoverageIgnoreEnd
    }

    /**
     * Uploads a local file to this storage under the given object key
     *
     * @param string $key Object key to store the file under (relative to the configured key prefix)
     * @param string $localpath Absolute path of the local file to upload
     * @param string $sha256 SHA-256 checksum of the local file
     * @param callable|null $progresscallback Optional callback invoked with (int $bytessent, int $bytestotal)
     * @throws storage_exception
     * @throws \coding_exception
     */
    public function put_object(string $key, string $localpath, string $sha256, ?callable $progresscallback = null): void {
        // Validate given local file.
        $filesize = @filesize($localpath);
        if ($filesize === false) {
            throw new storage_exception(
                'error_s3_object_store_failed',
                'archivingstore_s3',
                a: get_string('invalidfile', 'error') . ": {$localpath}"
            );
        }
        if ($filesize > self::MAX_PUT_OBJECT_SIZE) {
            throw new storage_exception('error_s3_object_too_large', 'archivingstore_s3');
        }

        // Prepare request headers and options.
        $path = $this->canonical_path($key);
        $headers = $this->build_signed_headers('PUT', $path, [], $sha256);

        $options = array_merge(
            $this->base_curl_options(timeout: HOURSECS),
            $this->progress_curl_option($progresscallback, upload: true),
            ['CURLOPT_HTTPHEADER' => $this->format_http_headers($headers)]
        );

        // Perform the upload.
        $c = new curl(['ignoresecurity' => true]);
        $body = $c->put($this->request_url($path), ['file' => $localpath], $options);

        // Error handling.
        if (!empty($c->error)) {
            throw new storage_exception('error_s3_object_store_failed', 'archivingstore_s3', a: $c->error);
        }

        // @codeCoverageIgnoreStart

        $httpcode = (int) ($c->get_info()['http_code'] ?? 0);
        if ($httpcode >= 200 && $httpcode < 300) {
            return;
        }

        $message = $this->parse_error_message((string) $body) ?? "HTTP {$httpcode}";
        throw new storage_exception('error_s3_object_store_failed', 'archivingstore_s3', a: $message);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Downloads an object from this storage to a local file
     *
     * @param string $key Object key to retrieve (relative to the configured key prefix)
     * @param string $localpath Absolute path to write the downloaded file to
     * @param callable|null $progresscallback Optional callback invoked with (int $bytesreceived, int $bytestotal)
     * @throws storage_exception
     */
    public function get_object(string $key, string $localpath, ?callable $progresscallback = null): void {
        // Prepare request headers and options.
        $path = $this->canonical_path($key);
        $headers = $this->build_signed_headers('GET', $path, [], self::EMPTY_PAYLOAD_SHA256);

        $options = array_merge(
            $this->base_curl_options(timeout: 0),
            $this->progress_curl_option($progresscallback, upload: false),
            [
                'CURLOPT_HTTPHEADER' => $this->format_http_headers($headers),
                'filepath' => $localpath,
            ]
        );

        // Download requested file.
        $c = new curl(['ignoresecurity' => true]);
        $c->download_one($this->request_url($path), null, $options);

        // Error handling.
        if (!empty($c->error)) {
            // Curl automatically removes the (incomplete) destination file on internal errors.
            throw new storage_exception('error_s3_object_retrieve_failed', 'archivingstore_s3', a: $c->error);
        }

        // @codeCoverageIgnoreStart

        $httpcode = (int) ($c->get_info()['http_code'] ?? 0);
        if ($httpcode >= 200 && $httpcode < 300) {
            // Everything fine.
            return;
        }

        // If re reached this point, the download_one() call streamed the S3 error body straight to $localpath.
        // Parse the error body and unlink the local file.
        $body = is_readable($localpath) ? (string) file_get_contents($localpath) : '';
        @unlink($localpath);

        if ($httpcode === 404) {
            throw new storage_exception('filenotfound', 'error');
        }

        $message = $this->parse_error_message($body) ?? "HTTP {$httpcode}";
        throw new storage_exception('error_s3_object_retrieve_failed', 'archivingstore_s3', a: $message);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Deletes an object from this storage
     *
     * @param string $key Object key to delete (relative to the configured key prefix)
     * @throws storage_exception
     */
    public function delete_object(string $key): void {
        // Prepare request headers and options.
        $path = $this->canonical_path($key);
        $headers = $this->build_signed_headers('DELETE', $path, [], self::EMPTY_PAYLOAD_SHA256);

        // Perform the deletion.
        $c = new curl(['ignoresecurity' => true]);
        $body = $c->delete($this->request_url($path), [], array_merge(
            $this->base_curl_options(),
            ['CURLOPT_HTTPHEADER' => $this->format_http_headers($headers)]
        ));

        // Error handling.
        if (!empty($c->error)) {
            throw new storage_exception('error_s3_object_delete_failed', 'archivingstore_s3', a: $c->error);
        }

        // @codeCoverageIgnoreStart

        $httpcode = (int) ($c->get_info()['http_code'] ?? 0);
        if ($httpcode >= 200 && $httpcode < 300) {
            return;
        }

        $message = $this->parse_error_message((string) $body) ?? "HTTP {$httpcode}";
        throw new storage_exception('error_s3_object_delete_failed', 'archivingstore_s3', a: $message);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Checks whether an object exists in this storage
     *
     * @param string $key Object key to check (relative to the configured key prefix)
     * @return bool True if the object exists
     * @throws storage_exception If the existence check itself fails for a reason other than "not found"
     */
    public function object_exists(string $key): bool {
        // Prepare request headers and options.
        $path = $this->canonical_path($key);
        $headers = $this->build_signed_headers('HEAD', $path, [], self::EMPTY_PAYLOAD_SHA256);

        // Perform the check request.
        $c = new curl(['ignoresecurity' => true]);
        $c->head($this->request_url($path), array_merge(
            $this->base_curl_options(),
            ['CURLOPT_HTTPHEADER' => $this->format_http_headers($headers)]
        ));

        // Generic CURL error.
        if (!empty($c->error)) {
            throw new storage_exception('error_s3_object_delete_failed', 'archivingstore_s3', a: $c->error);
        }

        // @codeCoverageIgnoreStart

        $httpcode = (int) ($c->get_info()['http_code'] ?? 0);
        if ($httpcode === 200) {
            // Object exists.
            return true;
        }
        if ($httpcode === 404) {
            // Object does not exist.
            return false;
        }

        // Other error. Treat as check failure.
        throw new storage_exception('error_s3_object_delete_failed', 'archivingstore_s3', a: "HTTP {$httpcode}");
        // @codeCoverageIgnoreEnd
    }

    /**
     * Parses a "bucket[/optional/prefix]" string into its bucket name and key prefix
     *
     * Tolerates (and strips) a leading "s3://" scheme for convenience, though the
     * canonical, documented format does not require it.
     *
     * @param string $raw Raw bucket path string, e.g. "mybucket/folder/subfolder"
     * @return object Object with `bucket` (string) and `prefix` (string, '' if none) properties
     * @throws \moodle_exception describing the first validation error encountered
     */
    public static function parse_bucket_path(string $raw): object {
        // Strip s3:// prefix and trim slashes.
        $raw = trim($raw);
        if (stripos($raw, 's3://') === 0) {
            $raw = substr($raw, 5);
        }
        $raw = trim($raw, '/');

        if ($raw === '') {
            throw new \moodle_exception('error_s3_bucket_path_invalid_bucket_name', 'archivingstore_s3');
        }

        // Split into bucket and prefix.
        [$bucket, $prefix] = array_pad(explode('/', $raw, 2), 2, '');

        // Validate bucket name.
        if (!preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucket)) {
            throw new \moodle_exception('error_s3_bucket_path_invalid_bucket_name', 'archivingstore_s3');
        }

        // Validate optional file key prefix.
        if (
            str_contains($prefix, '..') ||
            str_starts_with($prefix, '/') ||
            str_contains($prefix, '//') ||
            preg_match('/[\x00-\x1F\x7F]/', $prefix) // No unwanted ASCII control characters.
        ) {
            throw new \moodle_exception('error_s3_bucket_path_invalid_prefix', 'archivingstore_s3');
        }

        return (object) ['bucket' => $bucket, 'prefix' => $prefix];
    }

    /**
     * Parses the <Message>/<Code> elements of an S3 XML error response body
     *
     * @param string $body Raw HTTP response body
     * @return string|null Parsed error detail, or a truncated raw body if it is not parseable XML, or null if empty
     */
    private function parse_error_message(string $body): ?string {
        // Bail out early.
        if (trim($body) === '') {
            return null;
        }

        // Parse XML.
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors(false);

        // Extract <Message> or <Code> if present, otherwise return a truncated raw body.
        if ($xml !== false) {
            if (isset($xml->Message)) {
                return (string) $xml->Message;
            }
            if (isset($xml->Code)) {
                return (string) $xml->Code;
            }
        }

        return mb_strimwidth(trim($body), 0, 200, '...');
    }

    /**
     * Returns the URL scheme to use based on the configured TLS setting
     *
     * @return string "https" or "http"
     */
    private function scheme(): string {
        return $this->usetls ? 'https' : 'http';
    }

    /**
     * Splits the configured endpoint into its host and optional port
     *
     * @return array{0: string, 1: ?string} Host and port (or null if no port was configured)
     */
    private function split_endpoint(): array {
        $parts = explode(':', $this->endpoint, 2);
        return [$parts[0], $parts[1] ?? null];
    }

    /**
     * Returns the Host header / hostname to use for bucket-level requests, based
     * on the configured addressing style
     *
     * @return string Hostname, optionally including a port
     */
    private function request_host(): string {
        // Path-style addressing: bucket is part of the URL path.
        if ($this->pathstyle) {
            return $this->endpoint;
        }

        // Virtual-hosted-style addressing: bucket is part of the hostname.
        [$host, $port] = $this->split_endpoint();
        return $port !== null ? "{$this->bucket}.{$host}:{$port}" : "{$this->bucket}.{$host}";
    }

    /**
     * Combines the configured key prefix with a caller-supplied key relative to it
     *
     * @param string $key Object key relative to the configured key prefix
     * @return string Full object key, including the configured key prefix (if any)
     */
    private function full_key(string $key): string {
        $key = trim($key, '/');

        return $this->keyprefix !== '' ? "{$this->keyprefix}/{$key}" : $key;
    }

    /**
     * Returns the canonical (percent-encoded) request path for a bucket- or object-level request
     *
     * @param string $key Object key relative to the configured key prefix, or '' for a bucket-level request
     * @return string Canonical request path, e.g. "/", "/bucket", or "/bucket/some/key"
     */
    private function canonical_path(string $key = ''): string {
        $fullkey = $key !== '' ? $this->full_key($key) : '';

        // Populate segemnts based on selected path-style.
        $segments = [];
        if ($this->pathstyle) {
            $segments[] = $this->bucket;
        }
        if ($fullkey !== '') {
            $segments = array_merge($segments, explode('/', $fullkey));
        }

        // Build full canonical path.
        if (empty($segments)) {
            return '/';
        }

        return '/' . implode('/', array_map('rawurlencode', $segments));
    }

    /**
     * Builds the set of curl options shared by all signed object-level requests
     *
     * @param int $timeout Maximum transfer time in seconds, or 0 for no limit (e.g. large uploads/downloads)
     * @return array<string, mixed> Curl options
     */
    private function base_curl_options(int $timeout = 30): array {
        return [
            // Explicitly disable all HTTP auth negotiation: some curl:: methods (e.g. put(), delete()) default
            // CURLOPT_USERPWD to an anonymous placeholder credential when unset, which would otherwise cause
            // libcurl to send its own preemptive "Authorization: Basic ..." header alongside our manually
            // signed one.
            'CURLOPT_HTTPAUTH' => CURLAUTH_NONE,
            'CURLOPT_SSL_VERIFYPEER' => $this->verifytls,
            'CURLOPT_SSL_VERIFYHOST' => $this->verifytls ? 2 : 0,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_TIMEOUT' => $timeout,
        ];
    }

    /**
     * Builds the curl progress-callback options for an upload or download, if requested
     *
     * @param callable|null $progresscallback Callback invoked with (int $current, int $total), or null for none
     * @param bool $upload True to report upload progress, false to report download progress
     * @return array<string, mixed> Curl options, empty if no callback was given
     */
    private function progress_curl_option(?callable $progresscallback, bool $upload): array {
        if ($progresscallback === null) {
            return [];
        }

        return [
            'CURLOPT_NOPROGRESS' => false,
            'CURLOPT_XFERINFOFUNCTION' => function (
                $resource,
                $downloadtotal,
                $downloadnow,
                $uploadtotal,
                $uploadnow
            ) use (
                $progresscallback,
                $upload
            ) {
                if ($upload) {
                    $progresscallback((int) $uploadnow, (int) $uploadtotal);
                } else {
                    $progresscallback((int) $downloadnow, (int) $downloadtotal);
                }

                return 0;
            },
        ];
    }

    /**
     * Builds the canonical (sorted and encoded) query string for the given query parameters
     *
     * @param array<string, string> $query Query parameters
     * @return string Canonical query string
     */
    private function canonical_query_string(array $query): string {
        ksort($query);

        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $parts);
    }

    /**
     * Builds the full request URL for the given canonical path and query parameters
     *
     * @param string $path Canonical request path
     * @param array<string, string> $query Query parameters
     * @return string Full request URL
     */
    private function request_url(string $path, array $query = []): string {
        $url = $this->scheme() . '://' . $this->request_host() . $path;
        $querystring = $this->canonical_query_string($query);

        return $querystring !== '' ? "{$url}?{$querystring}" : $url;
    }

    /**
     * Builds the set of SigV4 signed headers for a request
     *
     * See: https://github.com/aws-samples/sigv4-signing-examples
     *
     * @param string $method HTTP method, e.g. "GET"
     * @param string $path Canonical request path
     * @param array<string, string> $query Query parameters
     * @param string $payloadsha256hex Hexadecimal SHA-256 hash of the request payload
     * @return array<string, string> Headers to send with the request, including "Authorization"
     * @throws \Exception
     */
    private function build_signed_headers(string $method, string $path, array $query, string $payloadsha256hex): array {
        // Prepare canonical request with required components for SigV4.
        $host = $this->request_host();
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $amzdate = $now->format('Ymd\THis\Z');
        $datestamp = $now->format('Ymd');

        $canonicalheaders = "host:{$host}\n" .
            "x-amz-content-sha256:{$payloadsha256hex}\n" .
            "x-amz-date:{$amzdate}\n";
        $signedheadernames = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalrequest = implode("\n", [
            $method,
            $path,
            $this->canonical_query_string($query),
            $canonicalheaders,
            $signedheadernames,
            $payloadsha256hex,
        ]);

        // Prepare final string to generate a signature for.
        $credentialscope = "{$datestamp}/{$this->region}/" . self::SERVICE . '/aws4_request';
        $stringtosign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzdate,
            $credentialscope,
            hash('sha256', $canonicalrequest),
        ]);

        // Sign the string with the derived signing key.
        $signature = hash_hmac('sha256', $stringtosign, $this->signing_key($datestamp));

        // Build the final set of signed request headers.
        return [
            'Host' => $host,
            'X-Amz-Content-Sha256' => $payloadsha256hex,
            'X-Amz-Date' => $amzdate,
            'Authorization' =>
                'AWS4-HMAC-SHA256 ' .
                "Credential={$this->accesskey}/{$credentialscope}, " .
                "SignedHeaders={$signedheadernames}, " .
                "Signature={$signature}",
        ];
    }

    /**
     * Derives the AWS Signature Version 4 signing key for the given date
     *
     * See: https://github.com/aws-samples/sigv4-signing-examples
     *
     * @param string $datestamp Date in "Ymd" format
     * @return string Raw (binary) signing key
     */
    private function signing_key(string $datestamp): string {
        $kdate = hash_hmac('sha256', $datestamp, 'AWS4' . $this->secretkey, true);
        $kregion = hash_hmac('sha256', $this->region, $kdate, true);
        $kservice = hash_hmac('sha256', self::SERVICE, $kregion, true);

        return hash_hmac('sha256', 'aws4_request', $kservice, true);
    }

    /**
     * Formats an associative array of headers into curl's "Name: Value" list format
     *
     * @param array<string, string> $headers Associative array of headers
     * @return string[] Headers formatted as "Name: Value" strings
     */
    private function format_http_headers(array $headers): array {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = "{$name}: {$value}";
        }

        return $formatted;
    }
}
