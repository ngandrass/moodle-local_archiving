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

namespace local_archiving\type;

/**
 * Tests for the cm_state_fingerprint class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the cm_state_fingerprint class.
 */
final class cm_state_fingerprint_test extends \advanced_testcase {

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
     * Tests creation of fingerprints from various cmdata inputs.
     *
     * @covers \local_archiving\type\cm_state_fingerprint
     * @dataProvider creation_data_provider
     *
     * @param array $cmdata Course module data to generate the fingerprint from.
     * @param bool $isvalid Whether the provided data is valid and should lead to a successful fingerprint creation.
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     */
    public function test_creation_and_load(array $cmdata, bool $isvalid): void {
        if (!$isvalid) {
            $this->expectException(\coding_exception::class);
        }

        // Test creation from data.
        $fingerprint = cm_state_fingerprint::generate($cmdata);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $fingerprint->get_raw_value(),
            'Fingerprint hash must be a valid SHA-256 hash.'
        );

        // Test loading the same fingerprint from its raw value.
        $loadedfingerprint = cm_state_fingerprint::from_raw_value($fingerprint->get_raw_value());
        $this->assertEquals($fingerprint, $loadedfingerprint, 'Loaded fingerprint must match the original.');
    }

    /**
     * Test data provider for test_creation.
     *
     * @return array Test cases with 'cmdata' and 'isvalid' keys.
     */
    public static function creation_data_provider(): array {
        return [
            'Single integer' => ['cmdata' => [42], 'isvalid' => true],
            'Single string' => ['cmdata' => ['example'], 'isvalid' => true],
            'Simple mixed list' => ['cmdata' => [1, 'example', 4.2, true], 'isvalid' => true],
            'Nested array' => ['cmdata' => [1, 2, [3, 4], 'example'], 'isvalid' => true],
            'Empty array' => ['cmdata' => [], 'isvalid' => false],
        ];
    }

    /**
     * Tests that only valid SHA-256 hashes can be used to load a fingerprint.
     *
     * @covers \local_archiving\type\cm_state_fingerprint
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_load_invalid_raw_value(): void {
        $this->expectException(\coding_exception::class);
        cm_state_fingerprint::from_raw_value('invalidhash');
    }

}

