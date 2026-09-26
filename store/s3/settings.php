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
 * Plugin administration pages are defined here
 *
 * @package     archivingstore_s3
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use archivingstore_s3\local\admin\setting\admin_setting_s3_bucket_path;
use archivingstore_s3\local\admin\setting\admin_setting_s3_connection_status;
use archivingstore_s3\local\admin\setting\admin_setting_s3_endpoint;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


global $DB;

if ($hassiteconfig) {
    $settings = new admin_settingpage('archivingstore_s3', new lang_string('pluginname', 'archivingstore_s3'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // Enabled.
        $settings->add(new admin_setting_configcheckbox(
            'archivingstore_s3/enabled',
            get_string('setting_enabled', 'archivingstore_s3'),
            get_string('setting_enabled_desc', 'archivingstore_s3'),
            '0'
        ));

        // Connection status.
        $settings->add(new admin_setting_s3_connection_status(
            'archivingstore_s3/connection_status',
            get_string('setting_connection_status', 'archivingstore_s3'),
            get_string('setting_connection_status_desc', 'archivingstore_s3')
        ));

        // Connection.
        $settings->add(new admin_setting_heading(
            'archivingstore_s3/header_connection',
            get_string('setting_header_connection', 'archivingstore_s3'),
            get_string('setting_header_connection_desc', 'archivingstore_s3')
        ));

        $settings->add(new admin_setting_s3_endpoint(
            'archivingstore_s3/endpoint',
            get_string('setting_endpoint', 'archivingstore_s3'),
            get_string('setting_endpoint_desc', 'archivingstore_s3'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'archivingstore_s3/region',
            get_string('setting_region', 'archivingstore_s3'),
            get_string('setting_region_desc', 'archivingstore_s3'),
            'eu-central-1',
            PARAM_RAW_TRIMMED
        ));

        $settings->add(new admin_setting_configcheckbox(
            'archivingstore_s3/use_tls',
            get_string('setting_use_tls', 'archivingstore_s3'),
            get_string('setting_use_tls_desc', 'archivingstore_s3'),
            '1'
        ));

        $settings->add(new admin_setting_configcheckbox(
            'archivingstore_s3/verify_tls',
            get_string('setting_verify_tls', 'archivingstore_s3'),
            get_string('setting_verify_tls_desc', 'archivingstore_s3'),
            '1'
        ));

        $settings->add(new admin_setting_configcheckbox(
            'archivingstore_s3/path_style',
            get_string('setting_path_style', 'archivingstore_s3'),
            get_string('setting_path_style_desc', 'archivingstore_s3'),
            '1'
        ));

        // Bucket & credentials.
        $settings->add(new admin_setting_heading(
            'archivingstore_s3/header_bucket',
            get_string('setting_header_bucket', 'archivingstore_s3'),
            get_string('setting_header_bucket_desc', 'archivingstore_s3')
        ));

        $settings->add(new admin_setting_s3_bucket_path(
            'archivingstore_s3/bucket_path',
            get_string('setting_bucket_path', 'archivingstore_s3'),
            get_string('setting_bucket_path_desc', 'archivingstore_s3'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'archivingstore_s3/access_key',
            get_string('setting_access_key', 'archivingstore_s3'),
            get_string('setting_access_key_desc', 'archivingstore_s3'),
            '',
            PARAM_RAW_TRIMMED
        ));

        $settings->add(new admin_setting_configpasswordunmask(
            'archivingstore_s3/secret_key',
            get_string('setting_secret_key', 'archivingstore_s3'),
            get_string('setting_secret_key_desc', 'archivingstore_s3'),
            ''
        ));
    }

    // Settingpage is added to tree automatically. No need to add it manually here.
}
