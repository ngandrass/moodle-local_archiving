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

namespace local_archiving\driver;

/**
 * Tests for the generic driver base.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the generic driver base.
 */
final class base_test extends \advanced_testcase {

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
     * Tests the inference of the sub-plugin frankenstyle name and validation
     *
     * @covers       \local_archiving\driver\base
     * @dataProvider subplugintype_data_provider
     *
     * @param string $subplugintype
     * @param bool $isvalid
     * @return void
     * @throws \coding_exception
     */
    public function test_get_frankenstyle_name(string $subplugintype, bool $isvalid): void {
        $base = $this->getMockForAbstractClass(base::class, [], $subplugintype.'_mock', callOriginalConstructor: false);

        // Expect an exception if the sub-plugin type is not valid.
        if (!$isvalid) {
            $this->expectException(\coding_exception::class);
        }

        // Validate the combined get_frankenstyle_name() method.
        $frankenstyle = $base->get_frankenstyle_name();
        $this->assertSame($subplugintype, $frankenstyle->type, 'Sub-plugin type does not match expected value.');
        $this->assertSame('mock', $frankenstyle->name, 'Sub-plugin name does not match expected value.');

        // Ensure that the get_plugin_type() and get_plugin_name() methods return the same values.
        $this->assertSame($subplugintype, $base->get_plugin_type(), 'get_plugin_type() does not match expected value.');
        $this->assertSame('mock', $base->get_plugin_name(), 'get_plugin_name() does not match expected value.');
    }

    /**
     * Provides valid and invalid sub-plugin types for testing
     *
     * @return array[] Test data with sub-plugin type and expected validity
     */
    public static function subplugintype_data_provider(): array {
        return [
            "Activity archiving driver (archivingmod)" => [
                'subplugintype' => 'archivingmod',
                'isvalid' => true,
            ],
            "Store driver (archivingstore)" => [
                'subplugintype' => 'archivingstore',
                'isvalid' => true,
            ],
            "Event adapter (archivingevent)" => [
                'subplugintype' => 'archivingevent',
                'isvalid' => true,
            ],
            "Invalid sub-plugin type (invalidtype)" => [
                'subplugintype' => 'invalidtype',
                'isvalid' => false,
            ],
        ];
    }

    /**
     * Tests the base implementation that determines if the sub-plugin is enabled.
     *
     * @covers \local_archiving\driver\base
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_is_enabled(): void {
        // Prepare a base instance and enable the corresponding plugin.
        $this->resetAfterTest();
        $base = $this->getMockForAbstractClass(base::class, [], 'archivingstore_localdir');
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('archivingstore_localdir');
        $plugininfo::enable_plugin('archivingstore_localdir', 1);
        $this->assertTrue($plugininfo->is_enabled(), 'The archivingstore_localdir plugin should be enabled by default.');

        // Test that the enabled plugin is detected as enabled.
        $this->assertTrue($base->is_enabled(), 'Base driver should return true for is_enabled() if the plugin is enabled.');

        // Disable the plugin and check that is_enabled() returns false.
        $plugininfo::enable_plugin('archivingstore_localdir', 0);
        $this->assertFalse($base->is_enabled(), 'Base driver should return false for is_enabled() if the plugin is disabled.');
    }

    /**
     * Tests the base implementation that determines if the sub-plugin is enabled
     * with an invalid plugin.
     *
     * @covers \local_archiving\driver\base
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_is_enabled_with_invalid_plugin(): void {
        // Prepare a base instance with an invalid plugin.
        $this->resetAfterTest();
        $base = $this->getMockForAbstractClass(base::class, [], 'archivingmod_invalidplugin');

        // Test that the invalid plugin is detected as not enabled.
        $this->assertFalse($base->is_enabled(), 'Base driver should return false for is_enabled() if the plugin does not exist.');
    }

    /**
     * Tests the base implementation that determines if the sub-plugin is ready.
     *
     * @covers \local_archiving\driver\base
     *
     * @return void
     */
    public function test_is_ready(): void {
        $base = $this->getMockForAbstractClass(base::class);
        $this->assertTrue($base->is_ready(), 'Base driver should always be ready.');
    }

}
