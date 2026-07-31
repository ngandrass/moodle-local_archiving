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
 * @package     archivingtrigger_cron
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


global $DB;

if ($hassiteconfig) {
    $settings = new admin_settingpage('archivingtrigger_cron', new lang_string('pluginname', 'archivingtrigger_cron'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // Enabled.
        $settings->add(new admin_setting_configcheckbox(
            'archivingtrigger_cron/enabled',
            get_string('setting_enabled', 'archivingtrigger_cron'),
            get_string('setting_enabled_desc', 'archivingtrigger_cron'),
            '1'
        ));

        // Dryrun.
        $settings->add(new admin_setting_configcheckbox(
            'archivingtrigger_cron/dryrun',
            get_string('setting_dryrun', 'archivingtrigger_cron'),
            get_string('setting_dryrun_desc', 'archivingtrigger_cron'),
            '0'
        ));

        // Archiving schedule.
        $task = \core\task\manager::get_scheduled_task('archivingtrigger_cron\task\trigger_archiving');
        $crontaskconfigurl = new \moodle_url('/admin/tool/task/scheduledtasks.php', [
            'action' => 'edit',
            'task' => 'archivingtrigger_cron\task\trigger_archiving',
        ]);
        $settings->add(new admin_setting_description(
            'archivingtrigger_cron/cronschedule',
            get_string('setting_cronschedule', 'archivingtrigger_cron'),
            get_string('setting_cronschedule_desc', 'archivingtrigger_cron', format_time($task->get_next_run_time() - time())) .
            '<br><a href="' . $crontaskconfigurl . '" class="btn btn-primary my-2" role="button">' .
            get_string('setting_cronschedule_button', 'archivingtrigger_cron') .
            '</a>'
        ));

        // Archiving unchanged activities.
        $settings->add(new admin_setting_configcheckbox(
            'archivingtrigger_cron/archive_unchanged',
            get_string('setting_archive_unchanged', 'archivingtrigger_cron'),
            get_string('setting_archive_unchanged_desc', 'archivingtrigger_cron'),
            '0'
        ));
    }

    // Settingpage is added to tree automatically. No need to add it manually here.
}
