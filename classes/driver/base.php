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
 * Abstract base class for all sub-plugin / driver interfaces
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Abstract base class for all sub-plugin / driver interfaces
 */
abstract class base {
    /** @var string[] Allowed sub-plugin types to be detected */
    public const ALLOWED_PLUGIN_TYPES = [
        'archivingmod',
        'archivingstore',
        'archivingevent',
        'archivingtrigger',
    ];

    /**
     * Retrieves the frankenstyle name (type and name) of this sub-plugin
     *
     * @return \stdClass Object with 'type' and 'name' properties
     * @throws \coding_exception If the current class namespace does not match
     * any of the allowed sub-plugin types.
     */
    public function get_frankenstyle_name(): \stdClass {
        // Get fully-quallified class name and extract the namespace.
        $fqcn = get_class($this);
        $namespace = explode("\\", $fqcn)[0];

        foreach (self::ALLOWED_PLUGIN_TYPES as $type) {
            // Only allow whitelisted namespaces.
            if (str_starts_with($namespace, $type . '_')) {
                return (object) [
                    'type' => $type,
                    'name' => substr($namespace, strlen($type) + 1),
                ];
            }
        }

        throw new \coding_exception("Invalid namespace for sub-plugin: {$fqcn}");
    }

    /**
     * Returns the plugin type of the sub-plugin based on the namespace of the
     * driver class.
     *
     * @return string Type of the sub-plugin, e.g. "archivingmod" for "archivingmod_myplugin"
     * @throws \coding_exception If the current class namespace does not match
     * any of the allowed sub-plugin types.
     */
    public function get_plugin_type(): string {
        return $this->get_frankenstyle_name()->type;
    }

    /**
     * Returns the plugin name of the sub-plugin based on the namespace of the
     * driver class.
     *
     * @return string Name of the sub-plugin, e.g. "myplugin" for "archivingmod_myplugin"
     * @throws \coding_exception If the current class namespace does not match
     * any of the allowed sub-plugin types.
     */
    public function get_plugin_name(): string {
        return $this->get_frankenstyle_name()->name;
    }

    /**
     * Determines if this sub-plugin is enabled
     *
     * @return bool True if the sub-plugin is enabled, false otherwise
     * @throws \coding_exception
     */
    public function is_enabled(): bool {
        $frankenstyle = $this->get_frankenstyle_name();
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info("{$frankenstyle->type}_{$frankenstyle->name}");

        if (!$plugininfo) {
            // Plugin not found.
            return false;
        }

        return $plugininfo->is_enabled();
    }

    /**
     * Determines if this sub-plugin is fully configured and ready to operate.
     *
     * This can be overridden, if your sub-plugin requires an initial setup / configuration
     * step prior to first operation.
     *
     * @return bool True, if this sub-plugin is fully configured and ready
     */
    public static function is_ready(): bool {
        return true;
    }
}
