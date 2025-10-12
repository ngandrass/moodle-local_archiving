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
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\local\admin\setting\admin_setting_coursecat_multiselect;
use local_archiving\local\admin\setting\admin_setting_managecomponents;
use local_archiving\local\admin\setting\admin_setting_filename_pattern;
use local_archiving\storage;
use local_archiving\type\log_level;
use local_archiving\util\plugin_util;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


global $DB;

if ($hassiteconfig) {
    $settingsroot = new admin_category('local_archiving', new lang_string('pluginname', 'local_archiving'));
    $ADMIN->add('localplugins', $settingsroot);

    // Settings page: Common.
    $commonpage = new admin_settingpage('local_archiving_common', new lang_string('common_settings', 'local_archiving'));
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // Common: Header.
        $commonpage->add(new admin_setting_heading(
            'local_archiving/header_common',
            null,
            get_string(
                'setting_header_common_desc',
                'local_archiving',
                new \moodle_url('/admin/settings.php', ['section' => 'local_archiving_components_manage'])
            )
        ));

        // Common: Job timeout.
        $commonpage->add(new admin_setting_configtext(
            'local_archiving/job_timeout_min',
            get_string('setting_job_timeout_min', 'local_archiving'),
            get_string('setting_job_timeout_min_desc', 'local_archiving'),
            '120',
            PARAM_INT
        ));

        // Common: Max concurrent jobs.
        $commonpage->add(new admin_setting_configtext(
            'local_archiving/max_concurrent_jobs',
            get_string('setting_max_concurrent_jobs', 'local_archiving'),
            get_string('setting_max_concurrent_jobs_desc', 'local_archiving'),
            '5',
            PARAM_INT
        ));

        // Common: Log level.
        $commonpage->add(new admin_setting_configselect(
            'local_archiving/log_level',
            get_string('setting_log_level', 'local_archiving'),
            get_string('setting_log_level_desc', 'local_archiving'),
            log_level::INFO->value,
            array_combine(log_level::values(), log_level::keys())
        ));

        // Common: Course categories.
        $commonpage->add(new admin_setting_coursecat_multiselect(
            'local_archiving/coursecat_whitelist',
            get_string('setting_coursecat_whitelist', 'local_archiving'),
            get_string('setting_coursecat_whitelist_desc', 'local_archiving'),
        ));

        // Common: Job Presets.
        $commonpage->add(new admin_setting_heading(
            'local_archiving/header_job_presets',
            get_string('setting_header_job_presets', 'local_archiving'),
            get_string('setting_header_job_presets_desc', 'local_archiving'),
        ));

        // Common - Job Preset: Export Activity (course module) Backup.
        $set = new admin_setting_configcheckbox(
            'local_archiving/job_preset_export_cm_backup',
            get_string('export_cm_backup', 'local_archiving'),
            get_string('export_cm_backup_help', 'local_archiving'),
            '1',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $commonpage->add($set);

        // Common - Job Preset: Export Course Backup.
        $set = new admin_setting_configcheckbox(
            'local_archiving/job_preset_export_course_backup',
            get_string('export_course_backup', 'local_archiving'),
            get_string('export_course_backup_help', 'local_archiving'),
            '0',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $commonpage->add($set);

        // Common - Job Preset: Storage driver.
        $storagedriverselects = [];
        foreach (plugin_util::get_storage_drivers() as $name => $driver) {
            if ($driver['enabled']) {
                $storagedriverselects[$name] = $driver['displayname'];
            }
        }
        if (empty($storagedriverselects)) {
            // No storage drivers available, add a dummy option.
            $storagedriverselects['null'] = '';
        }
        $set = new admin_setting_configselect_with_lock(
            'local_archiving/job_preset_storage_driver',
            get_string('storage_location', 'local_archiving'),
            get_string('storage_location_help', 'local_archiving'),
            [
                'value' => 'moodle',
                'locked' => false,
            ],
            $storagedriverselects
        );
        $commonpage->add($set);

        // Common - Job Preset: Archive filename pattern.
        $set = new admin_setting_filename_pattern(
            'local_archiving/job_preset_archive_filename_pattern',
            get_string('archive_filename_pattern', 'local_archiving'),
            get_string('archive_filename_pattern_help', 'local_archiving', [
                'variables' => array_reduce(
                    \local_archiving\type\archive_filename_variable::values(),
                    fn ($res, $varname) => $res . "<li><code>\${" . $varname . "}</code>: " .
                        get_string('archive_filename_pattern_variable_' . $varname, 'local_archiving') .
                        "</li>",
                    ""
                ),
                'forbiddenchars' => implode('', storage::FILENAME_FORBIDDEN_CHARACTERS),
            ]),
            'archive-${courseshortname}-${courseid}-${cmtype}-${cmname}-${cmid}_${date}-${time}',
            \local_archiving\type\archive_filename_variable::values(),
            storage::FILENAME_FORBIDDEN_CHARACTERS,
            PARAM_TEXT,
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $commonpage->add($set);

        // Common - Job Preset: Archive autodelete.
        $set = new admin_setting_configcheckbox(
            'local_archiving/job_preset_archive_autodelete',
            get_string('archive_autodelete', 'local_archiving'),
            get_string('archive_autodelete_help', 'local_archiving'),
            '0',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, true);
        $commonpage->add($set);

        // Common - Job Preset: Archive autodelete retention time.
        $set = new admin_setting_configduration(
            'local_archiving/job_preset_archive_retention_time',
            get_string('archive_retention_time', 'local_archiving'),
            get_string('archive_retention_time_help', 'local_archiving'),
            3 * YEARSECS,
            DAYSECS
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, true);
        $set->add_dependent_on('local_archiving/job_preset_archive_autodelete');
        $commonpage->add($set);

        // Common: Time-Stamp Protocol settings.
        $commonpage->add(new admin_setting_heading(
            'local_archiving/header_tsp',
            get_string('setting_header_tsp', 'local_archiving'),
            get_string('setting_header_tsp_desc', 'local_archiving')
        ));

        // Common - TSP: Enable.
        $commonpage->add(new admin_setting_configcheckbox(
            'local_archiving/tsp_enable',
            get_string('setting_tsp_enable', 'local_archiving'),
            get_string('setting_tsp_enable_desc', 'local_archiving'),
            '0'
        ));

        // Common - TSP: Automatic signing.
        $commonpage->add(new admin_setting_configcheckbox(
            'local_archiving/tsp_automatic_signing',
            get_string('setting_tsp_automatic_signing', 'local_archiving'),
            get_string('setting_tsp_automatic_signing_desc', 'local_archiving'),
            '1'
        ));

        // Common - TSP: Server URL.
        $commonpage->add(new admin_setting_configtext(
            'local_archiving/tsp_server_url',
            get_string('setting_tsp_server_url', 'local_archiving'),
            get_string('setting_tsp_server_url_desc', 'local_archiving'),
            'https://freetsa.org/tsr',
            PARAM_URL
        ));
    }

    // Settings category: Components.
    $managecomponentspage = new admin_settingpage(
        'local_archiving_components_manage',
        new lang_string('manage_components', 'local_archiving')
    );
    if ($ADMIN->fulltree) {
        // Manage components: Header.
        $managecomponentspage->add(new admin_setting_heading(
            'local_archiving/header_managecomponents',
            null,
            get_string(
                'setting_header_managecomponents_desc',
                'local_archiving',
                new \moodle_url('/admin/settings.php', ['section' => 'local_archiving_common'])
            )
        ));

        // Manage components: Manage components.
        $managecomponentspage->add(new admin_setting_managecomponents('local_archiving/managecomponents'));
    }

    // Add core settings pages.
    $ADMIN->add($settingsroot->name, $managecomponentspage);
    $ADMIN->add($settingsroot->name, $commonpage);

    // Load settings from subplugins.
    foreach (array_keys(\core_component::get_subplugins('local_archiving')) as $subplugintype) {
        // Only add settings category for current sub-plugin-type if at least one plugin
        // with settings of this type is installed.
        $subpluginswithsettings = \core_component::get_plugin_list_with_file($subplugintype, 'settings.php');
        if (empty($subpluginswithsettings)) {
            continue;
        }

        // Create a category for sub-plugins of this type and add their setting pages.
        $subplugincategory = new admin_category(
            $subplugintype,
            new lang_string("subplugintype_{$subplugintype}_plural", 'local_archiving')
        );
        $ADMIN->add($settingsroot->name, $subplugincategory);
        foreach ($subpluginswithsettings as $settingsfile) {
            $settings = null;
            include($settingsfile);
            if (!empty($settings)) {
                $ADMIN->add($subplugincategory->name, $settings);
            }
        }

        $settings = null;
    }
}
