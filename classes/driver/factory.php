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
 * Factory for sub-plugin drivers
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver;


// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Factory for all sub-plugins.
 */
class factory {

    /**
     * Creates a new instance of the requested archivingmod driver
     *
     * @param string $archivingmodname Name of the archivingmod driver to load (e.g., 'quiz' for 'archivingmod_quiz')
     * @param \context_module $context Moodle context this driver instance is associated with
     * @return archivingmod Instance of the requested archivingmod driver
     * @throws \coding_exception If no archivingmod driver with the given name exists
     */
    public static function activity_archiving_driver(string $archivingmodname, \context_module $context): archivingmod {
        $driverclass = self::get_subplugin_class('archivingmod', $archivingmodname, strict: true);
        return new $driverclass($context);
    }

    /**
     * Creates a new instance of the requested archivingstore driver
     *
     * @param string $archivingstorename Name of the archivingstore driver to load (e.g., 'moodle' for 'archivingstore_moodle')
     * @return archivingstore Instance of the requested archivingstore driver
     * @throws \coding_exception If no archivingstore driver with the given name exists
     */
    public static function storage_driver(string $archivingstorename): archivingstore {
        $driverclass = self::get_subplugin_class('archivingstore', $archivingstorename, strict: true);
        return new $driverclass();
    }

    /**
     * Creates a new instance of the requested archivingevent driver
     *
     * @param string $archivingeventname Name of the archivingevent driver to load (e.g., 'cms' for 'archivingevent_cms')
     * @return archivingevent Instance of the requested archivingevent driver
     * @throws \coding_exception If no archivingevent driver with the given name exists
     */
    public static function event_connector(string $archivingeventname): archivingevent {
        $driverclass = self::get_subplugin_class('archivingevent', $archivingeventname, strict: true);
        return new $driverclass();
    }

    /**
     * Retrieves the given subplugin class name for the given driver type and modname
     *
     * @param string $plugintype Type of the subplugin to look for (e.g., 'archivingmod')
     * @param string $pluginname Name of the subplugin to look for (e.g., 'quiz')
     * @param bool $strict If true, throws an exception if the class does not exist, otherwise returns null
     * @return string|null Fully-quallified class name of the subplugin class if found, null otherwise
     * @throws \coding_exception If strict is true and the class does not exist
     */
    public static function get_subplugin_class(string $plugintype, string $pluginname, bool $strict = false): ?string {
        $cls = "\\{$plugintype}_{$pluginname}\\{$plugintype}";

        if (!class_exists($cls)) {
            if ($strict) {
                throw new \coding_exception("No {$plugintype}_{$pluginname} sub-plugin base class found with FQDN '{$cls}'.");
            }

            return null;
        }

        return $cls;
    }

}
