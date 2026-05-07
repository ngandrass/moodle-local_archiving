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
 * @package     archivingstore_moodle
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


global $DB;

if ($hassiteconfig) {
    $settings = new admin_settingpage('archivingstore_moodle', new lang_string('pluginname', 'archivingstore_moodle'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // Enabled.
        $settings->add(new admin_setting_configcheckbox(
            'archivingstore_moodle/enabled',
            get_string('setting_enabled', 'archivingstore_moodle'),
            get_string('setting_enabled_desc', 'archivingstore_moodle'),
            '1'
        ));
    }

    // Settingpage is added to tree automatically. No need to add it manually here.
}
