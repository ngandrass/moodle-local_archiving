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
 * Adds functionality to detect the sub-plugin name based on the driver class
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\trait;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Adds functionality to detect the sub-plugin name based on the driver class
 */
trait subplugin_get_plugin_name {

    /**
     * Returns the plugin name of the sub-plugin based on the namespace of the
     * driver class.
     *
     * ATTENTION: This trait should only be used inside abstract driver classes!
     *
     * @return string Name of the sub-plugin, e.g. "myplugin" for "archivingmod_myplugin"
     * @throws \coding_exception If the current class namespace does not match any
     * of the allowed sub-plugin types.
     */
    public function get_plugin_name(): string {
        // Define allowed sub-plugin types here because class constants in traits are only supported in PHP 8.2+.
        $plugintypes = [
            'archivingmod',
            'archivingstore',
            'archivingevent',
        ];

        // Get fully-quallified class name and extract the namespace.
        $fqcn = get_class($this);
        $namespace = explode("\\", $fqcn)[0];

        foreach ($plugintypes as $type) {
            // Only allow whitelisted namespaces.
            if (str_starts_with($namespace, $type.'_')) {
                return substr($namespace, strlen($type) + 1);
            }
        }

        throw new \coding_exception("Invalid namespace for sub-plugin: {$fqcn}");
    }

}
