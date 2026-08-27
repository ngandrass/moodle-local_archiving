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

/**
 * Tests for the connection_check_result class.
 *
 * @package   archivingstore_s3
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the connection_check_result class.
 */
final class connection_check_result_test extends \advanced_testcase {
    /**
     * Tests that is_ok() is true only for the OK status, for every possible connection_status case.
     *
     * @covers \archivingstore_s3\local\connection_check_result
     * @dataProvider status_data_provider
     *
     * @param connection_status $status Status to construct the result with
     * @return void
     */
    public function test_is_ok(connection_status $status): void {
        $result = new connection_check_result($status, 'some message');

        $this->assertSame($status, $result->status, 'Status should match the constructor argument.');
        $this->assertSame(
            $status === connection_status::OK,
            $result->is_ok(),
            "is_ok() should only be true for the OK status (tested with {$status->name})."
        );
    }

    /**
     * Data provider for test_is_ok
     *
     * @return array Data sets
     */
    public static function status_data_provider(): array {
        $res = [];
        foreach (connection_status::cases() as $status) {
            $res[$status->name] = [$status];
        }
        return $res;
    }

    /**
     * Tests that the message defaults to null when not provided.
     *
     * @covers \archivingstore_s3\local\connection_check_result
     *
     * @return void
     */
    public function test_message_defaults_to_null(): void {
        $result = new connection_check_result(connection_status::OK);
        $this->assertNull($result->message, 'Message should default to null.');
    }
}
