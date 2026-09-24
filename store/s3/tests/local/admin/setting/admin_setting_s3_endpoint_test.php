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
 * Tests for the admin_setting_s3_endpoint class.
 *
 * @package   archivingstore_s3
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the admin_setting_s3_endpoint class.
 */
final class admin_setting_s3_endpoint_test extends \advanced_testcase {
    /**
     * Creates the setting instance under test
     *
     * @return admin_setting_s3_endpoint The created setting instance
     */
    private function create_setting(): admin_setting_s3_endpoint {
        return new admin_setting_s3_endpoint(
            'archivingstore_s3/endpoint',
            'Endpoint',
            'Description',
            ''
        );
    }

    /**
     * Tests validate() against a matrix of valid and invalid endpoint values.
     *
     * @covers \archivingstore_s3\local\admin\setting\admin_setting_s3_endpoint
     * @dataProvider validate_data_provider
     *
     * @param string $value Value to validate
     * @param bool $valid Whether the value is expected to be considered valid
     * @return void
     * @throws \coding_exception
     */
    public function test_validate(string $value, bool $valid): void {
        $setting = $this->create_setting();
        $result = $setting->validate($value);

        if ($valid) {
            $this->assertTrue($result, "Expected '{$value}' to be considered valid.");
        } else {
            $this->assertIsString($result, "Expected '{$value}' to be considered invalid.");
            $this->assertNotSame('', $result, 'Error message should not be empty.');
        }
    }

    /**
     * Data provider for test_validate
     *
     * @return array Data sets
     */
    public static function validate_data_provider(): array {
        return [
            // Valid cases.
            'empty is allowed (unconfigured)' => ['', true],
            'bare hostname' => ['example.com', true],
            'hostname with port' => ['example.com:9000', true],
            'minimum valid port' => ['example.com:1', true],
            'maximum valid port' => ['example.com:65535', true],
            // Invalid cases.
            'scheme is rejected' => ['https://example.com', false],
            'too many colon-separated parts' => ['example.com:9000:extra', false],
            'non-numeric port' => ['example.com:abc', false],
            'port zero is out of range' => ['example.com:0', false],
            'port above maximum is out of range' => ['example.com:65536', false],
            'empty host with port' => [':1234', false],
            'path component is rejected' => ['example.com/bucket', false],
        ];
    }
}
