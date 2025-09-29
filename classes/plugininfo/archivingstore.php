<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Subplugin info class for archivingstore
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\plugininfo;

use local_archiving\util\plugin_util;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Subplugin info class for archivingstore
 */
class archivingstore extends \core\plugininfo\base {

    /**
     * Should there be a way to uninstall the plugin via the administration UI.
     *
     * By default uninstallation is not allowed, plugin developers must enable it explicitly!
     *
     * @return bool
     */
    #[\Override]
    public function is_uninstall_allowed(): bool {
        return true;
    }

    /**
     * Whether this plugintype supports its plugins being disabled.
     *
     * @return bool
     */
    #[\Override]
    public static function plugintype_supports_disabling(): bool {
        return true;
    }

    /**
     * Enable or disable a plugin.
     * When possible, the change will be stored into the config_log table, to let admins check when/who has modified it.
     *
     * @param string $pluginname The plugin name to enable/disable.
     * @param int $enabled Whether the pluginname should be enabled (1) or not (0). This is an integer because some plugins, such
     * as filters or repositories, might support more statuses than just enabled/disabled.
     *
     * @return bool Whether $pluginname has been updated or not.
     * @throws \dml_exception
     */
    #[\Override]
    public static function enable_plugin(string $pluginname, int $enabled): bool {
        // Allow to pass the plugin name with or without the 'archivingstore_' prefix.
        if (str_starts_with($pluginname, 'archivingstore_')) {
            $pluginname = substr($pluginname, strlen('archivingstore_'));
        }

        // Determine if the plugin was enabled before.
        $wasenabled = (get_config("archivingstore_{$pluginname}", 'enabled') == 1);

        // Enable or disable the plugin.
        if ($enabled && !$wasenabled) {
            set_config('enabled', 1, "archivingstore_{$pluginname}");
            return true;
        } else if (!$enabled && $wasenabled) {
            set_config('enabled', 0, "archivingstore_{$pluginname}");
            return true;
        }

        return false;
    }

    /**
     * Returns the information about plugin availability
     *
     * True means that the plugin is enabled. False means that the plugin is
     * disabled. Null means that the information is not available, or the
     * plugin does not support configurable availability or the availability
     * can not be changed.
     *
     * @return null|bool
     * @throws \dml_exception
     */
    #[\Override]
    public function is_enabled() {
        if (!$this->rootdir) {
            // Plugin missing. Should not happen, but ...
            return false; // @codeCoverageIgnore
        }

        if (get_config("archivingstore_{$this->name}", 'enabled') == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Finds all enabled plugins, the result may include missing plugins.
     *
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    #[\Override]
    public static function get_enabled_plugins() {
        $enabledplugins = [];

        foreach (plugin_util::get_storage_drivers() as $name => $metadata) {
            if ($metadata['enabled']) {
                $enabledplugins[$name] = $name;
            }
        }

        return $enabledplugins;
    }

}
