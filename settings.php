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

use local_archiving\local\admin\setting\admin_setting_managecomponents;
use local_archiving\local\admin\setting\admin_setting_filename_pattern;
use local_archiving\storage;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


global $DB;

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_archiving', new lang_string('pluginname', 'local_archiving')));
    $settings = new admin_settingpage('local_archiving_common', new lang_string('common_settings', 'local_archiving'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // TODO (MDL-0): Add settings to configure logging levels and retention.

        $settings->add(new admin_setting_managecomponents('local_archiving/managecomponents'));

        // Header.
        $settings->add(new admin_setting_heading('local_archiving/header_docs',
            null,
            get_string('setting_header_common_desc', 'local_archiving')
        ));

        // Job timeout.
        $settings->add(new admin_setting_configtext('local_archiving/job_timeout_min',
            get_string('setting_job_timeout_min', 'local_archiving'),
            get_string('setting_job_timeout_min_desc', 'local_archiving'),
            '120',
            PARAM_INT
        ));

        // Job Presets.
        $settings->add(new admin_setting_heading('local_archiving/header_job_presets',
            get_string('setting_header_job_presets', 'local_archiving'),
            get_string('setting_header_job_presets_desc', 'local_archiving'),
        ));

        // Job Preset: Export Activity (course module) Backup.
        $set = new admin_setting_configcheckbox('local_archiving/job_preset_export_cm_backup',
            get_string('export_cm_backup', 'local_archiving'),
            get_string('export_cm_backup_help', 'local_archiving'),
            '1',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $settings->add($set);

        // Job Preset: Export Course Backup.
        $set = new admin_setting_configcheckbox('local_archiving/job_preset_export_course_backup',
            get_string('export_course_backup', 'local_archiving'),
            get_string('export_course_backup_help', 'local_archiving'),
            '0',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $settings->add($set);

        // Job Preset: Archive filename pattern.
        $set = new admin_setting_filename_pattern('local_archiving/job_preset_archive_filename_pattern',
            get_string('archive_filename_pattern', 'local_archiving'),
            get_string('archive_filename_pattern_help', 'local_archiving', [
                'variables' => array_reduce(
                    \local_archiving\type\archive_filename_variable::values(),
                    fn ($res, $varname) => $res."<li><code>\${".$varname."}</code>: ".
                        get_string('archive_filename_pattern_variable_'.$varname, 'local_archiving').
                        "</li>"
                    , ""
                ),
                'forbiddenchars' => implode('', storage::FILENAME_FORBIDDEN_CHARACTERS),
            ]),
            'archive-${courseshortname}-${courseid}-${cmtype}-${cmname}-${cmid}_${date}-${time}',
            \local_archiving\type\archive_filename_variable::values(),
            storage::FILENAME_FORBIDDEN_CHARACTERS,
            PARAM_TEXT,
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $settings->add($set);

        // Job Preset: Archive autodelete.
        $set = new admin_setting_configcheckbox('local_archiving/job_preset_archive_autodelete',
            get_string('archive_autodelete', 'local_archiving'),
            get_string('archive_autodelete_help', 'local_archiving'),
            '0',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, true);
        $settings->add($set);

        // Job Preset: Archive autodelete retention time.
        $set = new admin_setting_configduration('local_archiving/job_preset_archive_retention_time',
            get_string('archive_retention_time', 'local_archiving'),
            get_string('archive_retention_time_help', 'local_archiving'),
            3 * YEARSECS,
            DAYSECS
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, true);
        $set->add_dependent_on('local_archiving/job_preset_archive_autodelete');
        $settings->add($set);

        // Time-Stamp Protocol settings.
        $settings->add(new admin_setting_heading('quit_archiver/header_tsp',
            get_string('setting_header_tsp', 'local_archiving'),
            get_string('setting_header_tsp_desc', 'local_archiving')
        ));

        // TSP: Enable.
        $settings->add(new admin_setting_configcheckbox('local_archiving/tsp_enable',
            get_string('setting_tsp_enable', 'local_archiving'),
            get_string('setting_tsp_enable_desc', 'local_archiving'),
            '0'
        ));

        // TSP: Automatic signing.
        $settings->add(new admin_setting_configcheckbox('local_archiving/tsp_automatic_signing',
            get_string('setting_tsp_automatic_signing', 'local_archiving'),
            get_string('setting_tsp_automatic_signing_desc', 'local_archiving'),
            '1'
        ));

        // TSP: Server URL.
        $settings->add(new admin_setting_configtext('local_archiving/tsp_server_url',
            get_string('setting_tsp_server_url', 'local_archiving'),
            get_string('setting_tsp_server_url_desc', 'local_archiving'),
            'https://freetsa.org/tsr',
            PARAM_URL
        ));
    }

    // Add common settings page.
    $ADMIN->add('local_archiving', $settings);

    // Load settings from subplugins.
    foreach (array_keys(\core_component::get_subplugins('local_archiving')) as $subplugintype) {
        foreach (\core_component::get_plugin_list_with_file($subplugintype, 'settings.php') as $settingsfile) {
            /** @var admin_settingpage $settings */
            $settings = null;
            include($settingsfile);
            if (!empty($settings)) {
                $settings->visiblename = "[$subplugintype] $settings->visiblename";
                $ADMIN->add('local_archiving', $settings);
            }
        }
    }

    $settings = null;
}
