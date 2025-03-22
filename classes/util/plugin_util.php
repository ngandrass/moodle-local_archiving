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
 * @category    util
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\util;

use local_archiving\driver\archivingevent_base;
use local_archiving\driver\archivingmod_base;
use local_archiving\driver\archivingstore_base;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Utility functions for working with subplugins
 */
class plugin_util {

    /**
     * Returns a list of all installed archivingmod plugins and their respective
     * metadata
     *
     * @return array List of installed archivingmod plugins
     */
    public static function get_activity_archiving_drivers(): array {
        // Retrieve list of installed archivingmod plugins.
        $plugins = \core_component::get_plugin_list('archivingmod');
        $res = [];

        // Iterate over all plugins and collect their metadata.
        foreach ($plugins as $pluginname => $basedir) {
            /** @var archivingmod_base $pluginclass */
            $pluginclass = '\\archivingmod_'.$pluginname.'\\archivingmod';

            $res[$pluginname] = [
                'name' => $pluginclass::get_name(),
                'activities' => $pluginclass::get_supported_activities(),
                'basedir' => $basedir,
                'class' => $pluginclass
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
     * @return string|null Class of the chosen driver or null if no driver is available
     */
    public static function get_archiving_driver_for_cm(string $modname): ?string {
        foreach (self::get_activity_archiving_drivers() as $driver) {
            if (array_search($modname, $driver['activities']) !== false) {
                return $driver['class'];
            }
        }

        return null;
    }

    /**
     * Returns a list of all installed archivingstore plugins and their
     * respective metadata
     *
     * @return array List of installed archivingstore plugins
     */
    public static function get_storage_drivers(): array {
        // Retrieve list of installed archivingstore plugins.
        $plugins = \core_component::get_plugin_list('archivingstore');
        $res = [];

        // Iterate over all plugins and collect their metadata.
        foreach ($plugins as $pluginname => $basedir) {
            /** @var archivingstore_base $pluginclass */
            $pluginclass = '\\archivingstore_'.$pluginname.'\\archivingstore';

            $res[$pluginname] = [
                'name' => $pluginclass::get_name(),
                'basedir' => $basedir,
                'class' => $pluginclass
            ];
        }

        return $res;
    }

    /**
     * Returns a list of all installed archivingevent plugins and their
     * respective metadata
     *
     * @return array List of installed archivingevent plugins
     */
    public static function get_event_connectors(): array {
        // Retrieve list of installed archivingevent plugins.
        $plugins = \core_component::get_plugin_list('archivingevent');
        $res = [];

        // Iterate over all plugins and collect their metadata.
        foreach ($plugins as $pluginname => $basedir) {
            /** @var archivingevent_base $pluginclass */
            $pluginclass = '\\archivingevent_'.$pluginname.'\\archivingevent';

            $res[$pluginname] = [
                'name' => $pluginclass::get_name(),
                'basedir' => $basedir,
                'class' => $pluginclass
            ];
        }

        return $res;
    }

}
