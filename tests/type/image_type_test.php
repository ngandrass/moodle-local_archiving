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
 * Tests for the image_type class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the image_type class.
 */
final class image_type_test extends \advanced_testcase {

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
     * Tests all image types for correct properties and retrieval methods.
     *
     * @covers \local_archiving\type\image_type
     * @dataProvider image_types_data_provider
     *
     * @param image_type $type The image type to test
     * @return void
     */
    public function test_image_types(image_type $type): void {
        // Get and validate properties from image_type.
        $mimetype = $type->mimetype();
        $extension = $type->extension();

        $this->assertNotEmpty($mimetype, "Image type {$type->name} must have a mime type.");
        $this->assertNotEmpty($extension, "Image type {$type->name} must have a file extension.");

        // Try to get image_type from mimetype.
        $this->assertSame(
            $type,
            image_type::from_mimetype($mimetype),
            "Image type {$type->name} could not be retrieved from its mime type."
        );

        // Try to get image_type from extension.
        $this->assertSame(
            $type,
            image_type::from_extension($extension),
            "Image type {$type->name} could not be retrieved from its file extension."
        );

        // Handle JPEG special case.
        if ($type === image_type::JPEG) {
            $this->assertSame(
                $type,
                image_type::from_extension('jpeg'),
                "Image type {$type->name} could not be retrieved from its alternative file extension 'jpeg'."
            );
        }
    }

    /**
     * Test data provider for test_image_types.
     *
     * @return array<int, array{image_type}> All image types.
     */
    public static function image_types_data_provider(): array {
        $res = [];

        foreach (image_type::cases() as $type) {
            $res[$type->name] = [$type];
        }

        return $res;
    }

    /**
     * Tests that an invalid mimetype returns null.
     *
     * @covers \local_archiving\type\image_type
     *
     * @return void
     */
    public function test_invalid_mimetype(): void {
        $this->assertNull(image_type::from_mimetype('invalid/mimetype'));
    }

    /**
     * Tests that an invalid extension returns null.
     *
     * @covers \local_archiving\type\image_type
     *
     * @return void
     */
    public function test_invalid_extension(): void {
        $this->assertNull(image_type::from_extension('invalidextension'));
    }

}

