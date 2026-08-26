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

namespace archivingstore_s3;


use local_archiving\storage;

/**
 * Tests for the archivingstore_s3 implementation.
 *
 * @package   archivingstore_s3
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingstore_s3 implementation.
 */
final class archivingstore_test extends \advanced_testcase {
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
     * Ensures that the correct storage tier is reported.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     */
    public function test_get_storage_tier(): void {
        $this->assertEquals(
            \local_archiving\local\type\storage_tier::REMOTE_FAST,
            archivingstore::get_storage_tier(),
            'Storage tier should be REMOTE_FAST.'
        );
    }

    /**
     * Ensures that the storage reports that it supports retrieval.
     *
     * @covers \archivingstore_s3\archivingstore
     *
     * @return void
     */
    public function test_supports_retrieve(): void {
        $this->assertTrue(archivingstore::supports_retrieve(), 'Storage should support retrieve.');
    }
}
