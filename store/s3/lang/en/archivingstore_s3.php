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
 * Plugin strings are defined here
 *
 * @package     archivingstore_s3
 * @category    string
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// @codingStandardsIgnoreFile

// Common
$string['pluginname'] = 'Object Store (S3)';
$string['privacy:metadata'] = 'All data stored via this storage driver remains owned by local_archiving and will be handled directly by its privacy API instead.';

// Settings.
$string['setting_enabled'] = 'Enabled';
$string['setting_enabled_desc'] = 'Enables or disables this storage driver. If disabled, no archives can be sent to or retrieved from this storage.';
$string['setting_header_connection'] = 'Connection';
$string['setting_header_connection_desc'] = 'Configure the connection to your S3-compatible object storage endpoint. This works with self-hosted S3-compatible services such as RustFS as well as AWS S3.';
$string['setting_endpoint'] = 'Endpoint';
$string['setting_endpoint_desc'] = 'Hostname of the S3 endpoint, optionally followed by a port. Do not include a scheme (http:// / https://) or bucket path here. Examples: <code>rustfs.example.com:9000</code> or <code>s3.eu-central-1.amazonaws.com</code>.';
$string['setting_region'] = 'Region';
$string['setting_region_desc'] = 'S3 region to use for request signing. Many self-hosted S3-compatible services accept an arbitrary value here, but a value is always required.';
$string['setting_use_tls'] = 'Use TLS';
$string['setting_use_tls_desc'] = 'If enabled, connections to the S3 endpoint are made via HTTPS instead of HTTP.';
$string['setting_verify_tls'] = 'Verify TLS certificate';
$string['setting_verify_tls_desc'] = 'If enabled, the TLS certificate presented by the S3 endpoint is verified. <strong>Disabling this reduces security</strong> and should only be done for self-hosted deployments with self-signed certificates that you fully trust.';
$string['setting_path_style'] = 'Use path-style addressing';
$string['setting_path_style_desc'] = 'If enabled, the bucket name is included in the request path (<code>https://endpoint/bucket/key</code>) instead of the hostname (<code>https://bucket.endpoint/key</code>). Self-hosted S3-compatible services such as MinIO or Ceph often require path-style addressing.';
$string['setting_header_bucket'] = 'Bucket & Credentials';
$string['setting_header_bucket_desc'] = 'Configure the bucket to store created archives in and the credentials used to access it.';
$string['setting_bucket_path'] = 'Bucket path';
$string['setting_bucket_path_desc'] = 'The bucket to store archives in, optionally followed by a key prefix (subfolder). Format: <code>bucket</code> or <code>bucket/optional/prefix</code>.<br>Example: <code>mynicebucket/folder/subfolder</code>.';
$string['setting_access_key'] = 'Access key';
$string['setting_access_key_desc'] = 'The access key used to authenticate with the S3 endpoint.';
$string['setting_secret_key'] = 'Secret key';
$string['setting_secret_key_desc'] = 'The secret key used to authenticate with the S3 endpoint.';
$string['setting_connection_status'] = 'Connection status';
$string['setting_connection_status_desc'] = 'Shows whether the settings below are complete and whether the configured endpoint and bucket are reachable. This check is performed live whenever this page is loaded, using the settings as of the last save.';

