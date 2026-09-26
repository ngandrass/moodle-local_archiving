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
 * Tests for the admin_setting_s3_bucket_path class.
 *
 * @package   archivingstore_s3
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the admin_setting_s3_bucket_path class.
 */
final class admin_setting_s3_bucket_path_test extends \advanced_testcase {
    /**
     * Creates the setting instance under test
     *
     * @return admin_setting_s3_bucket_path The created setting instance
     */
    private function create_setting(): admin_setting_s3_bucket_path {
        return new admin_setting_s3_bucket_path(
            'archivingstore_s3/bucket_path',
            'Bucket path',
            'Description',
            ''
        );
    }

    /**
     * Tests validate() against a matrix of valid and invalid bucket path values.
     *
     * @covers \archivingstore_s3\local\admin\setting\admin_setting_s3_bucket_path
     * @dataProvider validate_data_provider
     *
     * @param string $value Value to validate
     * @param bool $valid Whether the value is expected to be considered valid
     * @return void
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
            'bucket only' => ['mybucket', true],
            'bucket with prefix' => ['mybucket/folder/sub', true],
            's3 scheme is tolerated' => ['s3://mybucket/folder', true],
            // Invalid cases.
            'bucket too short' => ['ab', false],
            'bucket with uppercase' => ['MyBucket', false],
            'bucket with invalid characters' => ['my_bucket', false],
            'prefix with dot-dot' => ['mybucket/../secret', false],
            'prefix with double slash' => ['mybucket/a//b', false],
        ];
    }
}
