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

namespace local_archiving\util;


/**
 * Tests for the plugin util class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the plugin util class.
 */
final class plugin_util_test extends \advanced_testcase {
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
     * Tests retrieval of installed sub-plugins / drivers and their metadata.
     *
     * @covers \local_archiving\util\plugin_util
     * @dataProvider get_drivers_data_provider
     *
     * @param string $plugintype Sub-plugin type name
     * @param string $drivertypename Driver name for the driver retrieval function
     * @return void
     * @throws \coding_exception
     */
    public function test_get_drivers(string $plugintype, string $drivertypename): void {
        // Ensure that we have at least one plugin of the given type.
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type($plugintype);
        if (empty($plugins)) {
            $this->markTestSkipped('No matching subplugin installed to be tested.');
        }

        // Try to get available drivers and validate results.
        $drivers = plugin_util::{"get_{$drivertypename}s"}();
        $this->assertIsArray($drivers);
        $this->assertNotEmpty($drivers);

        foreach ($drivers as $driver) {
            $this->assertArrayHasKey('component', $driver);
            $this->assertArrayHasKey('displayname', $driver);
            $this->assertArrayHasKey('rootdir', $driver);
            $this->assertArrayHasKey('class', $driver);
            $this->assertArrayHasKey('enabled', $driver);
            $this->assertArrayHasKey('ready', $driver);
            $this->assertArrayHasKey('version', $driver);
            $this->assertArrayHasKey('release', $driver);

            $component = explode('_', $driver['component'], 2);
            $this->assertTrue(plugin_util::is_subplugin_installed($component[0], $component[1]));
        }
    }

    /**
     * Test data provider for test_get_drivers.
     *
     * @return array[] List of driver types to test and their corresponding method suffixes
     */
    public static function get_drivers_data_provider(): array {
        return [
            'archivingmod' => ['archivingmod', 'activity_archiving_driver'],
            'archivingstore' => ['archivingstore', 'storage_driver'],
            'archivingevent' => ['archivingevent', 'event_connector'],
            'archivingtrigger' => ['archivingtrigger', 'archiving_trigger'],
        ];
    }

    /**
     * Tests retrieval of a list of supported activities.
     *
     * @covers \local_archiving\util\plugin_util
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_get_supported_activities(): void {
        $this->assertNotEmpty(plugin_util::get_supported_activities());
    }

    /**
     * Tests retrieval of an activity archiving driver for a given cm type.
     *
     * @covers \local_archiving\util\plugin_util
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_get_archiving_driver_for_cm(): void {
        foreach (plugin_util::get_supported_activities() as $modname) {
            $drivername = plugin_util::get_archiving_driver_for_cm($modname);
            $this->assertNotEmpty($drivername);
            $this->assertTrue(plugin_util::is_subplugin_installed('archivingmod', $drivername));
        }
    }
}
