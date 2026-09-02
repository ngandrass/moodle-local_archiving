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
 * @package     archivingmod_assign
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use archivingmod_assign\local\type\attachment_type;
use archivingmod_assign\local\type\submission_filename_variable;
use archivingmod_assign\local\type\submission_report_section;
use local_archiving\local\admin\setting\admin_setting_filename_pattern;
use local_archiving\local\admin\setting\admin_setting_webservice_enabler;
use local_archiving\local\type\paper_format;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


global $DB;

if ($hassiteconfig) {
    $settings = new admin_settingpage('archivingmod_assign', new lang_string('pluginname', 'archivingmod_assign'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // Descriptive text.
        $settings->add(new admin_setting_heading(
            'archivingmod_assign/header_docs',
            null,
            get_string('setting_header_docs_desc', 'archivingmod_assign')
        ));

        // Enabled.
        $settings->add(new admin_setting_configcheckbox(
            'archivingmod_assign/enabled',
            get_string('setting_enabled', 'archivingmod_assign'),
            get_string('setting_enabled_desc', 'archivingmod_assign'),
            '1'
        ));

        // Worker service.
        $settings->add(new admin_setting_heading(
            'archivingmod_assign/header_archive_worker',
            get_string('setting_header_archive_worker', 'archivingmod_assign'),
            get_string('setting_header_archive_worker_desc', 'archivingmod_assign')
        ));

        // Worker service: Global webservice settings.
        $settings->add(new admin_setting_webservice_enabler(
            'archivingmod_assign/webservice_enabler',
            get_string('setting_webservice_enabler', 'archivingmod_assign'),
            get_string('setting_webservice_enabler_desc', 'archivingmod_assign')
        ));

        // Worker service: URL.
        $settings->add(new admin_setting_configtext(
            'archivingmod_assign/worker_url',
            get_string('setting_worker_url', 'archivingmod_assign'),
            get_string('setting_worker_url_desc', 'archivingmod_assign'),
            '',
            PARAM_TEXT
        ));

        // Worker service: Custom Moodle base URL.
        $settings->add(new admin_setting_configtext(
            'archivingmod_assign/internal_wwwroot',
            get_string('setting_internal_wwwroot', 'archivingmod_assign'),
            get_string('setting_internal_wwwroot_desc', 'archivingmod_assign'),
            '',
            PARAM_TEXT
        ));

        // Job Presets.
        $settings->add(new admin_setting_heading(
            'archivingmod_assign/header_job_presets',
            get_string('setting_header_job_presets', 'local_archiving'),
            get_string('setting_header_job_presets_desc', 'local_archiving'),
        ));

        // Job preset: Submission report sections.
        foreach (submission_report_section::cases() as $section) {
            $set = new admin_setting_configcheckbox(
                'archivingmod_assign/job_preset_report_section_' . $section->value,
                get_string('task_report_section_' . $section->value, 'archivingmod_assign'),
                get_string('task_report_section_' . $section->value . '_help', 'archivingmod_assign'),
                '1',
            );
            $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);

            foreach ($section->dependencies() as $dependency) {
                $set->add_dependent_on('archivingmod_assign/job_preset_report_section_' . $dependency->value);
            }

            $settings->add($set);
        }

        // Job preset: Submission attachments.
        foreach (attachment_type::cases() as $section) {
            $set = new admin_setting_configcheckbox(
                'archivingmod_assign/job_preset_attachment_' . $section->value,
                get_string('task_attachment_' . $section->value, 'archivingmod_assign'),
                get_string('task_attachment_' . $section->value . '_help', 'archivingmod_assign'),
                '1',
            );
            $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
            $settings->add($set);
        }

        // Job preset: Export paper format.
        $set = new admin_setting_configselect(
            'archivingmod_assign/job_preset_paper_format',
            get_string('task_paper_format', 'archivingmod_assign'),
            get_string('task_paper_format_help', 'archivingmod_assign'),
            'A4',
            array_combine(paper_format::values(), paper_format::values()),
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $settings->add($set);

        // Job preset: Keep HTML files.
        $set = new admin_setting_configcheckbox(
            'archivingmod_assign/job_preset_keep_html_files',
            get_string('task_keep_html_files', 'archivingmod_assign'),
            get_string('task_keep_html_files_help', 'archivingmod_assign'),
            '0',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $settings->add($set);

        // Job preset: Submission filename pattern.
        $set = new admin_setting_filename_pattern(
            'archivingmod_assign/job_preset_submission_filename_pattern',
            get_string('task_submission_filename_pattern', 'archivingmod_assign'),
            get_string('task_submission_filename_pattern_help', 'archivingmod_assign', [
                'variables' => array_reduce(
                    submission_filename_variable::values(),
                    fn($res, $varname) => $res . "<li><code>\${" . $varname . "}</code>: " .
                        get_string('task_submission_filename_pattern_variable_' . $varname, 'archivingmod_assign') .
                        "</li>",
                    ""
                ),
                'forbiddenchars' => implode('', \local_archiving\storage::FILENAME_FORBIDDEN_CHARACTERS),
            ]),
            'submission-${submissionid}-${username}_${date}-${time}',
            submission_filename_variable::values(),
            \local_archiving\storage::FILENAME_FORBIDDEN_CHARACTERS,
            PARAM_TEXT,
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $settings->add($set);

        // Flatten export archive.
        $set = new admin_setting_configcheckbox(
            'archivingmod_assign/job_preset_archive_flatten',
            get_string('task_archive_flatten', 'archivingmod_assign'),
            get_string('task_archive_flatten_help', 'archivingmod_assign'),
            '0',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $settings->add($set);

        // Job preset: Submission folder name pattern.
        $set = new admin_setting_filename_pattern(
            'archivingmod_assign/job_preset_submission_foldername_pattern',
            get_string('task_submission_foldername_pattern', 'archivingmod_assign'),
            get_string('task_submission_foldername_pattern_help', 'archivingmod_assign', [
                'variables' => array_reduce(
                    submission_filename_variable::values(),
                    fn($res, $varname) => $res . "<li><code>\${" . $varname . "}</code>: " .
                        get_string('task_submission_filename_pattern_variable_' . $varname, 'archivingmod_assign') .
                        "</li>",
                    ""
                ),
                'forbiddenchars' => implode('', \local_archiving\storage::FOLDERNAME_FORBIDDEN_CHARACTERS),
            ]),
            '${username}-${submissionid}-${date}_${time}',
            submission_filename_variable::values(),
            \local_archiving\storage::FOLDERNAME_FORBIDDEN_CHARACTERS,
            PARAM_TEXT,
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $set->add_dependent_on('archivingmod_assign/job_preset_archive_flatten');
        $settings->add($set);

        // Job preset: Image optimization.
        $set = new admin_setting_configcheckbox(
            'archivingmod_assign/job_preset_image_optimize',
            get_string('task_image_optimize', 'archivingmod_assign'),
            get_string('task_image_optimize_help', 'archivingmod_assign'),
            '0',
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $settings->add($set);

        // Job preset: Image optimization: Max width.
        $set = new admin_setting_configtext(
            'archivingmod_assign/job_preset_image_optimize_width',
            get_string('task_image_optimize_width', 'archivingmod_assign'),
            get_string('task_image_optimize_width_help', 'archivingmod_assign'),
            '1280',
            PARAM_INT
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $set->add_dependent_on('archivingmod_assign/job_preset_image_optimize');
        $settings->add($set);

        // Job preset: Image optimization: Max height.
        $set = new admin_setting_configtext(
            'archivingmod_assign/job_preset_image_optimize_height',
            get_string('task_image_optimize_height', 'archivingmod_assign'),
            get_string('task_image_optimize_height_help', 'archivingmod_assign'),
            '1280',
            PARAM_INT
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $set->add_dependent_on('archivingmod_assign/job_preset_image_optimize');
        $settings->add($set);

        // Job preset: Image optimization: Quality.
        $set = new admin_setting_configtext(
            'archivingmod_assign/job_preset_image_optimize_quality',
            get_string('task_image_optimize_quality', 'archivingmod_assign'),
            get_string('task_image_optimize_quality_help', 'archivingmod_assign'),
            '85',
            PARAM_INT
        );
        $set->set_locked_flag_options(admin_setting_flag::ENABLED, false);
        $set->add_dependent_on('archivingmod_assign/job_preset_image_optimize');
        $settings->add($set);
    }

    // Settingpage is added to tree automatically. No need to add it manually here.
}
