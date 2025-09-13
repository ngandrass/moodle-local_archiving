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

/**
 * Utility class for subplugin management
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\util;

use local_archiving\driver\archivingevent;
use local_archiving\driver\archivingmod;
use local_archiving\driver\archivingstore;
use local_archiving\driver\archivingtrigger;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Utility functions for working with subplugins
 */
class plugin_util {

    /**
     * Checks if the base class for the given sub-plugin exists
     *
     * @param string $type Type of the sub-plugin (e.g., 'archivingmod', 'archivingstore', 'archivingevent')
     * @param string $name Name of the sub-plugin (e.g., 'quiz', 'moodle', 'logstore')
     * @return bool True if the sub-plugin is installed, false otherwise
     */
    public static function is_subplugin_installed(string $type, string $name): bool {
        return \local_archiving\driver\factory::get_subplugin_class($type, $name, strict: false) !== null;
    }

    /**
     * Returns a list of all installed archivingmod plugins and their respective
     * metadata
     *
     * @return array List of installed archivingmod plugins
     */
    public static function get_activity_archiving_drivers(): array {
        // Retrieve list of installed archivingmod plugins.
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('archivingmod');
        $res = [];

        // Iterate over all plugins and collect their metadata.
        foreach ($plugins as $plugin) {
            if (!$plugin->rootdir) {
                // Skip plugins with missing sources.
                continue;
            }

            /** @var archivingmod $pluginclass */
            $pluginclass = \local_archiving\driver\factory::get_subplugin_class('archivingmod', $plugin->name);

            $res[$plugin->name] = [
                'component' => $plugin->component,
                'displayname' => $plugin->displayname,
                'rootdir' => $plugin->rootdir,
                'class' => $pluginclass,
                'activities' => $pluginclass::get_supported_activities(),
                'enabled' => $plugin->is_enabled() ?? false,
                'ready' => $pluginclass::is_ready() ?? false,
                'version' => $plugin->versiondb,
                'release' => $plugin->release,
            ];
        }

        return $res;
    }

    /**
     * Retrieves a list of all Moodle activities that are supported by at least
     * one of the installed activity archiving driver plugins
     *
     * @return array List of supported activities for archiving
     */
    public static function get_supported_activities(): array {
        $res = [];

        foreach (self::get_activity_archiving_drivers() as $archiver) {
            foreach ($archiver['activities'] as $activitiy) {
                $res[$activitiy] = $activitiy;
            }
        }

        return $res;
    }

    /**
     * Tries to find an installed activity archiving driver (archivingmod) for
     * the given activity type (dm modname)
     *
     * @param string $modname Name of the course module
     * @return string|null Name of the chosen driver or null if no driver is available
     */
    public static function get_archiving_driver_for_cm(string $modname): ?string {
        foreach (self::get_activity_archiving_drivers() as $drivername => $drivermeta) {
            if (in_array($modname, $drivermeta['activities'])) {
                return $drivername;
            }
        }

        return null;
    }

    /**
     * Returns a list of all installed archivingstore plugins and their
     * respective metadata
     *
     * @return array List of installed archivingstore plugins
     * @throws \coding_exception
     */
    public static function get_storage_drivers(): array {
        // Retrieve list of installed archivingstore plugins.
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('archivingstore');
        $res = [];

        // Iterate over all plugins and collect their metadata.
        foreach ($plugins as $plugin) {
            if (!$plugin->rootdir) {
                // Skip plugins with missing sources.
                continue;
            }

            /** @var archivingstore $pluginclass */
            $pluginclass = \local_archiving\driver\factory::get_subplugin_class('archivingstore', $plugin->name);

            $res[$plugin->name] = [
                'component' => $plugin->component,
                'displayname' => $plugin->displayname,
                'rootdir' => $plugin->rootdir,
                'class' => $pluginclass,
                'tier' => $pluginclass::get_storage_tier(),
                'enabled' => $plugin->is_enabled() ?? false,
                'ready' => $pluginclass::is_ready() ?? false,
                'version' => $plugin->versiondb,
                'release' => $plugin->release,
            ];
        }

        return $res;
    }

    /**
     * Returns a list of all installed archivingevent plugins and their
     * respective metadata
     *
     * @return array List of installed archivingevent plugins
     * @throws \coding_exception
     */
    public static function get_event_connectors(): array {
        // Retrieve list of installed archivingevent plugins.
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('archivingevent');
        $res = [];

        // Iterate over all plugins and collect their metadata.
        foreach ($plugins as $plugin) {
            if (!$plugin->rootdir) {
                // Skip plugins with missing sources.
                continue;
            }

            /** @var archivingevent $pluginclass */
            $pluginclass = \local_archiving\driver\factory::get_subplugin_class('archivingevent', $plugin->name);

            $res[$plugin->name] = [
                'component' => $plugin->component,
                'displayname' => $plugin->displayname,
                'rootdir' => $plugin->rootdir,
                'class' => $pluginclass,
                'enabled' => $plugin->is_enabled() ?? false,
                'ready' => $pluginclass::is_ready() ?? false,
                'version' => $plugin->versiondb,
                'release' => $plugin->release,
            ];
        }

        return $res;
    }

    /**
     * Returns a list of all installed archivingtrigger plugins and their
     * respective metadata
     *
     * @return array List of installed archivingtrigger plugins
     * @throws \coding_exception
     */
    public static function get_archiving_triggers(): array {
        // Retrieve list of installed archivingevent plugins.
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('archivingtrigger');
        $res = [];

        // Iterate over all plugins and collect their metadata.
        foreach ($plugins as $plugin) {
            if (!$plugin->rootdir) {
                // Skip plugins with missing sources.
                continue;
            }

            /** @var archivingtrigger $pluginclass */
            $pluginclass = \local_archiving\driver\factory::get_subplugin_class('archivingtrigger', $plugin->name);

            $res[$plugin->name] = [
                'component' => $plugin->component,
                'displayname' => $plugin->displayname,
                'rootdir' => $plugin->rootdir,
                'class' => $pluginclass,
                'enabled' => $plugin->is_enabled() ?? false,
                'ready' => $pluginclass::is_ready() ?? false,
                'version' => $plugin->versiondb,
                'release' => $plugin->release,
            ];
        }

        return $res;
    }

}
