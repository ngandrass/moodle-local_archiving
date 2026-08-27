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

namespace archivingstore_s3\local\admin\setting;

/**
 * Tests for the admin_setting_s3_connection_status class.
 *
 * @package   archivingstore_s3
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the admin_setting_s3_connection_status class.
 *
 * Only the branches reachable without a live S3-compatible server are covered.
 */
final class admin_setting_s3_connection_status_test extends \advanced_testcase {
    /**
     * Creates the setting instance under test
     *
     * @return admin_setting_s3_connection_status The created setting instance
     */
    private function create_setting(): admin_setting_s3_connection_status {
        return new admin_setting_s3_connection_status(
            'archivingstore_s3/connection_status',
            'Connection status',
            'Description'
        );
    }

    /**
     * Tests that an unconfigured driver renders its reachability/accessibility checks as skipped.
     *
     * @covers \archivingstore_s3\local\admin\setting\admin_setting_s3_connection_status
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_output_html_not_configured(): void {
        $this->resetAfterTest();

        $html = $this->create_setting()->output_html('');

        $this->assertStringContainsString(
            get_string('status_check_skipped', 'archivingstore_s3'),
            $html,
            'Reachability/accessibility checks should be reported as skipped when unconfigured.'
        );
        $this->assertStringContainsString('list-group-item-danger', $html, 'Unconfigured state should be flagged as not ok.');
    }

    /**
     * Tests that a fully configured driver performs the live check and reports failure.
     *
     * @covers \archivingstore_s3\local\admin\setting\admin_setting_s3_connection_status
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_output_html_configured_unreachable(): void {
        $this->resetAfterTest();
        set_config('endpoint', '127.0.0.1:1', 'archivingstore_s3');
        set_config('region', 'eu-central-1', 'archivingstore_s3');
        set_config('path_style', '1', 'archivingstore_s3');
        set_config('bucket_path', 'mybucket', 'archivingstore_s3');
        set_config('access_key', 'myaccesskey', 'archivingstore_s3');
        set_config('secret_key', 'opensesame', 'archivingstore_s3');

        $html = $this->create_setting()->output_html('');

        $this->assertStringNotContainsString(
            get_string('status_check_skipped', 'archivingstore_s3'),
            $html,
            'A configured driver should attempt the live check instead of skipping it.'
        );
        $this->assertStringContainsString(
            'list-group-item-danger',
            $html,
            'An unreachable endpoint should be flagged as not ok.'
        );
    }
}
