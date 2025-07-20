<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the tsp_client class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

/**
 * Tests for the tsp_client class
 */
final class tsp_client_test extends \advanced_testcase {

    /**
     * Tests the creation of a tsp_client instance
     *
     * @covers \local_archiving\tsp_client::__construct
     * @covers \local_archiving\tsp_client::get_serverurl
     *
     * @return void
     */
    public function test_creation(): void {
        $client = new tsp_client('http://localhost:12345');
        $this->assertInstanceOf(tsp_client::class, $client);
        $this->assertEquals('http://localhost:12345', $client->get_serverurl());
    }

    /**
     * Tests the generation of a nonce
     *
     * @covers \local_archiving\tsp_client::generate_nonce
     *
     * @return void
     * @throws \Exception
     */
    public function test_generate_nonce(): void {
        $nonce = tsp_client::generate_nonce();
        $this->assertNotEmpty($nonce, 'Nonce is empty');
        $this->assertSame(16, strlen($nonce), 'Nonce length is not 16 bytes');

        for ($i = 0; $i < 100; $i++) {
            $this->assertNotEquals(
                $nonce,
                tsp_client::generate_nonce(),
                'Repeated calls to generate_nonce() return the same nonce'
            );
        }
    }

    /**
     * Tests the generation of a TSP request from valid data
     *
     * @covers \local_archiving\tsp_client::sign
     * @covers \local_archiving\tsp_client::create_timestamp_request
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_signing_valid_data(): void {
        $client = new tsp_client('http://localhost:12345');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/'.get_string('tsp_client_error_curl', 'local_archiving', '').'/');
        $client->sign('6e82908cfa04dbf1706aa938e32f27e6a1d5f096df5c472795a93f8ab9de4c72');
    }

    /**
     * Test the generation of a TSP request from invalid data
     *
     * @covers \local_archiving\tsp_client::sign
     *
     * @return void
     * @throws \Exception
     */
    public function test_signing_invalid_data(): void {
        $client = new tsp_client('http://localhost:12345');

        $this->expectException(\ValueError::class);
        $this->expectExceptionMessageMatches('/Invalid hexadecimal SHA256 hash/');
        $client->sign('invalid-data');
    }

}
