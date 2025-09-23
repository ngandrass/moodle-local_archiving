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
 * Tests for the task_content_metadata class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the task_content_metadata class.
 */
final class task_content_metadata_test extends \advanced_testcase {

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
     * Tests creation of valid and invalid task_content_metadata from various combinations of input data.
     *
     * @covers \local_archiving\type\task_content_metadata
     * @dataProvider construction_data_provider
     *
     * @param int $taskid ID of the task the metadata belongs to
     * @param int $userid ID of the user that owns the referenced data
     * @param string|null $reftable Name of the table the referenced data is stored in
     * @param int|null $refid ID of the referenced data
     * @param string|null $summary A short summary of the content
     * @param bool $isvalid Whether the provided data is valid and should lead to a successful creation
     * @return void
     * @throws \moodle_exception
     */
    public function test_construction(
        int $taskid,
        int $userid,
        ?string $reftable,
        ?int $refid,
        ?string $summary,
        bool $isvalid
    ): void {
        // Expect exception if the data is invalid.
        if (!$isvalid) {
            $this->expectException(\moodle_exception::class);
        }

        // Test creation from data and validate that the metadata can be retrieved.
        $metadata = new task_content_metadata($taskid, $userid, $reftable, $refid, $summary);
        $this->assertSame($taskid, $metadata->taskid, 'Task ID does not match.');
        $this->assertSame($userid, $metadata->userid, 'User ID does not match.');
        $this->assertSame($reftable, $metadata->reftable, 'Ref table does not match.');
        $this->assertSame($refid, $metadata->refid, 'Ref ID does not match.');
        $this->assertSame($summary, $metadata->summary, 'Summary does not match.');

        $array = $metadata->as_array();
        $this->assertSame($taskid, $array['taskid'], 'Array task ID does not match.');
        $this->assertSame($userid, $array['userid'], 'Array user ID does not match.');
        $this->assertSame($reftable, $array['reftable'], 'Array ref table does not match.');
        $this->assertSame($refid, $array['refid'], 'Array ref ID does not match.');
        $this->assertSame($summary, $array['summary'], 'Array summary does not match.');
    }

    /**
     * Test data provider for test_construction.
     *
     * @return array<string, array{int, int, ?string, ?int, ?string, bool}> Test cases with various combinations of input data.
     */
    public static function construction_data_provider(): array {
        return [
            'Valid data with all fields' => [1, 2, 'course', 3, 'Test summary', true],
            'Valid data with only referenced data' => [1, 2, 'course', 42, null, true],
            'Valid data with only summary' => [1, 2, null, null, 'Lorem ipsum', true],
            'Invalid data with missing summary and reference data' => [1, 2, null, null, null, false],
            'Invalid data with missing reference table but present refid' => [1, 2, null, 5, null, false],
            'Invalid data with missing refid but present reference table' => [1, 2, 'course', null, null, false],
        ];
    }

}

