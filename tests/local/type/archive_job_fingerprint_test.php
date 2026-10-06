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

namespace local_archiving\local\type;

/**
 * Tests for the archive_job_fingerprint class.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archive_job_fingerprint class.
 */
final class archive_job_fingerprint_test extends \advanced_testcase {
    /**
     * Tests creation of fingerprints and loading them from their raw value.
     *
     * @covers \local_archiving\local\type\archive_job_fingerprint
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     */
    public function test_creation_and_load(): void {
        $fingerprint = archive_job_fingerprint::generate(1, 2, (object) ['foo' => 'bar']);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $fingerprint->get_raw_value(),
            'Fingerprint hash must be a valid SHA-256 hash.'
        );

        $loadedfingerprint = archive_job_fingerprint::from_raw_value($fingerprint->get_raw_value());
        $this->assertTrue($fingerprint->equals($loadedfingerprint), 'Loaded fingerprint must match the original.');
    }

    /**
     * Tests that loading fingerprints with an invalid length fails.
     *
     * @covers \local_archiving\local\type\archive_job_fingerprint
     * @dataProvider invalid_raw_value_data_provider
     *
     * @param string $rawvalue Invalid raw fingerprint value
     * @return void
     * @throws \coding_exception
     */
    public function test_load_invalid_raw_value(string $rawvalue): void {
        $this->expectException(\coding_exception::class);
        archive_job_fingerprint::from_raw_value($rawvalue);
    }

    /**
     * Data provider for test_load_invalid_raw_value.
     *
     * @return array Invalid raw fingerprint values
     */
    public static function invalid_raw_value_data_provider(): array {
        return [
            'Empty' => [''],
            'Too short' => [str_repeat('a', 63)],
            'Too long' => [str_repeat('a', 65)],
        ];
    }

    /**
     * Tests that fingerprints do not depend on the order of settings keys or
     * the order of elements in lists of scalar values.
     *
     * @covers \local_archiving\local\type\archive_job_fingerprint
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     */
    public function test_fingerprint_is_order_independent(): void {
        $a = archive_job_fingerprint::generate(1, 2, (object) [
            'foo' => 'bar',
            'attemptids' => [3, 1, 2],
            'nested' => (object) ['lorem' => 'ipsum', 'dolor' => 42],
        ]);
        $b = archive_job_fingerprint::generate(1, 2, (object) [
            'nested' => (object) ['dolor' => 42, 'lorem' => 'ipsum'],
            'attemptids' => [1, 2, 3],
            'foo' => 'bar',
        ]);

        $this->assertTrue($a->equals($b), 'Fingerprints must not depend on key or list order.');
    }

    /**
     * Tests that fingerprints change whenever any of their inputs change.
     *
     * @covers \local_archiving\local\type\archive_job_fingerprint
     * @dataProvider differing_input_data_provider
     *
     * @param int $courseid Course ID of the compared fingerprint
     * @param int $cmid Course module ID of the compared fingerprint
     * @param \stdClass $settings Settings of the compared fingerprint
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     */
    public function test_fingerprint_changes_with_input(int $courseid, int $cmid, \stdClass $settings): void {
        $reference = archive_job_fingerprint::generate(1, 2, (object) ['foo' => 'bar', 'attemptids' => [1, 2, 3]]);
        $other = archive_job_fingerprint::generate($courseid, $cmid, $settings);

        $this->assertFalse($reference->equals($other), 'Fingerprints of different inputs must differ.');
    }

    /**
     * Data provider for test_fingerprint_changes_with_input.
     *
     * @return array Inputs that differ from the reference fingerprint input
     */
    public static function differing_input_data_provider(): array {
        $settings = (object) ['foo' => 'bar', 'attemptids' => [1, 2, 3]];

        return [
            'Different course' => [3, 2, $settings],
            'Different course module' => [1, 3, $settings],
            'Different setting value' => [1, 2, (object) ['foo' => 'baz', 'attemptids' => [1, 2, 3]]],
            'Different targeted IDs' => [1, 2, (object) ['foo' => 'bar', 'attemptids' => [1, 2, 4]]],
            'Additional setting' => [1, 2, (object) ['foo' => 'bar', 'attemptids' => [1, 2, 3], 'baz' => 1]],
            'Missing setting' => [1, 2, (object) ['foo' => 'bar']],
        ];
    }
}
