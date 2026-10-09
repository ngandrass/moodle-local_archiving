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
 * Plugin administration pages are defined here
 *
 * @package     archivingtrigger_event
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


global $DB;

if ($hassiteconfig) {
    $settings = new admin_settingpage('archivingtrigger_event', new lang_string('pluginname', 'archivingtrigger_event'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // Enabled.
        $settings->add(new admin_setting_configcheckbox(
            'archivingtrigger_event/enabled',
            get_string('setting_enabled', 'archivingtrigger_event'),
            get_string('setting_enabled_desc', 'archivingtrigger_event'),
            '0'
        ));

        // Event sensitivity list.
        foreach (\archivingtrigger_event\archivingtrigger::get_eventlist() as $drivername => $eventlist) {
            // Translate event names.
            $options = [];
            foreach ($eventlist as $event) {
                $options[$event] = $event::get_name() . " (<code>{$event}</code>)";
            }

            // Build setting element.
            $settings->add(new admin_setting_configmulticheckbox(
                'archivingtrigger_event/sensitivity_' . $drivername,
                get_string('setting_sensitivity', 'archivingtrigger_event', $drivername),
                get_string('setting_sensitivity_desc', 'archivingtrigger_event', $drivername),
                [],
                $options
            ));
        }
    }

    // Settingpage is added to tree automatically. No need to add it manually here.
}
