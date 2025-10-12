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

namespace local_archiving\plugininfo;

/**
 * Tests for the archivingmod plugininfo class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingmod plugininfo class.
 */
final class archivingmod_test extends \advanced_testcase {
    /**
     * Tests that the plugin reports correct flags.
     *
     * @covers \local_archiving\plugininfo\archivingmod
     *
     * @return void
     */
    public function test_flags(): void {
        // Get a plugin to test.
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('archivingmod');
        if (empty($plugins)) {
            $this->markTestSkipped('No matching subplugin installed to be tested.');
        }
        $plugin = array_shift($plugins);

        // Validate flags.
        $this->assertTrue($plugin->is_uninstall_allowed(), "{$plugin->component} subplugin must allow uninstallation.");
        $this->assertTrue($plugin::plugintype_supports_disabling(), "{$plugin->type} plugintype must support disabling.");
    }

    /**
     * Tests that the plugin can be enabled and disabled.
     *
     * @covers \local_archiving\plugininfo\archivingmod
     *
     * @return void
     */
    public function test_enabling(): void {
        $this->resetAfterTest();

        // Get a plugin to test.
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('archivingmod');
        if (empty($plugins)) {
            $this->markTestSkipped("No matching subplugins installed to be tested.");
        }
        $plugin = array_shift($plugins);

        // Make sure the plugin is enabled.
        $plugin::enable_plugin($plugin->name, 1);
        $this->assertTrue($plugin->is_enabled(), "{$plugin->component} subplugin should be enabled after enabling it.");

        // Disable the plugin.
        $plugin::enable_plugin($plugin->name, 0);
        $this->assertFalse($plugin->is_enabled(), "{$plugin->component} subplugin should be disabled after disabling it.");
        $plugin::enable_plugin($plugin->name, 0);
        $this->assertFalse($plugin->is_enabled(), "{$plugin->component} subplugin should still be disabled after disabling it.");

        // Re-enable the plugin again.
        $plugin::enable_plugin($plugin->name, 1);
        $this->assertTrue($plugin->is_enabled(), "{$plugin->component} subplugin should be enabled after re-enabling it.");
        $plugin::enable_plugin($plugin->name, 1);
        $this->assertTrue($plugin->is_enabled(), "{$plugin->component} subplugin should still be enabled after re-enabling it.");

        // Make sure that this also works if component name is passed as prefix.
        $plugin::enable_plugin($plugin->component, 0);
        $this->assertFalse(
            $plugin->is_enabled(),
            "{$plugin->component} subplugin should be disabled after disabling it with prefixed name."
        );
        $plugin::enable_plugin($plugin->component, 1);
        $this->assertTrue(
            $plugin->is_enabled(),
            "{$plugin->component} subplugin should be enabled after enabling it with prefixed name."
        );
    }

    /**
     * Tests that the correct number of enabled plugins is reported.
     *
     * @covers \local_archiving\plugininfo\archivingmod
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_report_enabled_plugins(): void {
        // Get all plugins and count enabled ones.
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('archivingmod');
        $enabledplugins = array_filter($plugins, fn($p) => $p->is_enabled());

        // Ensure that the same number of enabled plugins is reported.
        $this->assertSameSize(
            $enabledplugins,
            archivingmod::get_enabled_plugins(),
            'The number of enabled archivingmod plugins should match the number reported by get_enabled_plugins().'
        );
    }
}
