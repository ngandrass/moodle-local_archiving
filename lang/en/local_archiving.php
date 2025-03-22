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
 * Plugin strings are defined here
 *
 * @package     local_archiving
 * @category    string
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// @codingStandardsIgnoreFile

// Common.
$string['pluginname'] = 'Archiving';
$string['create_archive'] = 'Create archive';

// Sub-plugins.
$string['subplugintype_archivingmod'] = 'Activity archiving driver';
$string['subplugintype_archivingmod_plural'] = 'Activity archiving drivers';
$string['subplugintype_archivingstore'] = 'Storage driver';
$string['subplugintype_archivingstore_plural'] = 'Storage drivers';
$string['subplugintype_archivingevent'] = 'External event connector';
$string['subplugintype_archivingevent_plural'] = 'External event connectors';

// Job settings: General.
$string['export_course_backup'] = 'Export full Moodle course backup (.mbz)';
$string['export_course_backup_help'] = 'This will export a full Moodle course backup (.mbz) including everything inside this course. This can be useful if you want to import this course into another Moodle instance.';
$string['export_cm_backup'] = 'Export Moodle activity backup (.mbz)';
$string['export_cm_backup_help'] = 'This will export a Moodle backup (.mbz) of the targeted activity. This can be useful if you want to import this activity independent of this course into another Moodle instance.';
$string['job_create_form_header'] = 'Create Archive';
$string['job_create_form_header_desc'] = 'This form triggers the creation of a new archive. Jobs are processed asynchronously in the background and take some time to complete. You can always check the current status on the overview page.';
$string['job_create_form_header_typed'] = 'Create {$a} Archive';

// Job settings: Filename pattern.
$string['archive_filename_pattern'] = 'Archive name';
$string['archive_filename_pattern_help'] = 'Name of the generated archive. Variables <b>must</b> follow the <code>${variablename}</code> pattern. The file extension will be added automatically.<br><br><b>Available variables</b>: <ul>{$a->variables}</ul><b>Forbidden characters</b>: <code>{$a->forbiddenchars}</code>';
// TODO (MDL-0): Remove the following 2 lines after deprecation of Moodle 4.1 (LTS) on 08-12-2025.
$string['archive_filename_pattern_moodle42'] = 'Archive name';
$string['archive_filename_pattern_moodle42_help'] = 'Name of the generated archive. Variables <b>must</b> follow the <code>${variablename}</code> pattern. The file extension will be added automatically.<br><br><b>Available variables</b>: <ul><li><code>${courseid}</code>: Course ID</li><li><code>${coursename}</code>: Course name</li><li><code>${courseshortname}</code>: Course short name</li><li><code>${cmid}</code>: Activity ID</li><li><code>${cmtype}</code>: Activity type</li><li><code>${cmname}</code>: Activity name</li><li><code>${date}</code>: Current date <small>(YYYY-MM-DD)</small></li><li><code>${time}</code>: Current time <small>(HH-MM-SS)</small></li><li><code>${timestamp}</code>: Current unix timestamp</li></ul><b>Forbidden characters</b>: <code>\/.:;*?!"&lt;&gt;|</code>';
$string['archive_filename_pattern_variable_courseid'] = 'Course ID';
$string['archive_filename_pattern_variable_coursename'] = 'Course name';
$string['archive_filename_pattern_variable_courseshortname'] = 'Course short name';
$string['archive_filename_pattern_variable_cmid'] = 'Activity ID';
$string['archive_filename_pattern_variable_cmtype'] = 'Activity type';
$string['archive_filename_pattern_variable_cmname'] = 'Activity name';
$string['archive_filename_pattern_variable_date'] = 'Current date <small>(YYYY-MM-DD)</small>';
$string['archive_filename_pattern_variable_time'] = 'Current time <small>(HH-MM-SS)</small>';
$string['archive_filename_pattern_variable_timestamp'] = 'Current unix timestamp';
$string['error_invalid_filename_pattern'] = 'Invalid filename pattern. Please correct your input and try again.';
$string['error_invalid_archive_filename_pattern'] = 'Invalid archive filename pattern. Please correct your input and try again.';

// Job settings: Autodelete.
$string['archive_autodelete'] = 'Automatic deletion';
$string['archive_autodelete_short'] = 'Deletion';
$string['archive_autodelete_help'] = 'Automatically delete this archive after a certain amount of time. The retention time can be configured below, once automatic deletion is activated.';
$string['archive_retention_time'] = 'Retention time';
$string['archive_retention_time_help'] = 'The amount of time this archive should be kept before it is automatically deleted. This setting only takes effect if automatic deletion is activated.';

// Admin settings.
$string['common_settings'] = 'Common settings';
$string['setting_header_common_desc'] = 'TODO';
$string['setting_header_job_presets'] = 'Archive Presets';
$string['setting_header_job_presets_desc'] = 'System wide default settings for archive creation. These defaults can be overridden when creating a new archive. However, each individual setting can also be locked to prevent managers / teachers from changing it. This can be useful when enforcing organization wide archive policies.';
$string['setting_header_tsp'] = 'Archive Signing';
$string['setting_header_tsp_desc'] = 'Archives and their creation date can be digitally signed by a trusted authority using the <a href="https://en.wikipedia.org/wiki/Time_stamp_protocol" target="_blank">Time-Stamp Protocol (TSP)</a> according to <a href="https://www.ietf.org/rfc/rfc3161.txt" target="_blank">RFC 3161</a>. This can be used to cryptographically prove the integrity and creation date of the archive at a later point in time. Archives can be signed automatically at creation or manually later on.';
$string['setting_job_timeout_min'] = 'Job timeout (minutes)';
$string['setting_job_timeout_min_desc'] = 'The number of minutes a single archive job is allowed to run before it is aborted by Moodle. Job web service access tokens become invalid after this timeout.<br/>Note: Additional timeouts can be present in sub-plugins and archive worker services. The shorter timeout always takes precedence.';
$string['setting_tsp_automatic_signing'] = 'Automatically sign archives';
$string['setting_tsp_automatic_signing_desc'] = 'Automatically sign archives when they are created.';
$string['setting_tsp_enable'] = 'Enable archive signing';
$string['setting_tsp_enable_desc'] = 'Allow archives to be signed using the Time-Stamp Protocol (TSP). If this option is disabled, archives can neither be signed manually nor automatically.';
$string['setting_tsp_server_url'] = 'TSP server URL';
$string['setting_tsp_server_url_desc'] = 'URL of the Time-Stamp Protocol (TSP) server to use.<br/>Examples: <code>https://freetsa.org/tsr</code>, <code>https://zeitstempel.dfn.de</code>, <code>http://timestamp.digicert.com</code>';
