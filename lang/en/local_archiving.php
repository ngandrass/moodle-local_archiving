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

$string['pluginname'] = 'Archiving';
$string['privacy:metadata'] = 'TODO!';

// Common.
$string['activitytype'] = 'Activity type';
$string['archiving'] = 'Archiving';
$string['available'] = 'Available';
$string['back_to_overview'] = 'Back to overview';
$string['checksum'] = 'Checksum';
$string['cleanup_filestore_cache'] = 'Cleanup filestore cache';
$string['cleanup_orphaned_backups'] = 'Cleanup orphaned backups';
$string['components'] = 'Components';
$string['create_archive'] = 'Create archive';
$string['disabled'] = 'Disabled';
$string['download_files'] = 'Download files';
$string['file'] = 'File';
$string['files'] = 'Files';
$string['filetype'] = 'File type';
$string['free_space_unknown'] = 'Free space unknown';
$string['id'] = 'ID';
$string['jobid'] = 'Job ID';
$string['last_updated'] = 'Last updated';
$string['new_changes'] = 'New changes';
$string['progress'] = 'Progress';
$string['ready'] = 'Ready';
$string['type'] = 'Type';
$string['unconfigured'] = 'Unconfigured';
$string['used'] = 'Used';
$string['unsupported'] = 'Unsupported';

// Sub-plugins.
$string['subplugintype_archivingmod'] = 'Activity archiving driver';
$string['subplugintype_archivingmod_plural'] = 'Activity archiving drivers';
$string['subplugintype_archivingstore'] = 'Storage driver';
$string['subplugintype_archivingstore_plural'] = 'Storage drivers';
$string['subplugintype_archivingevent'] = 'External event connector';
$string['subplugintype_archivingevent_plural'] = 'External event connectors';

// Capabilities.
$string['archiving:view'] = 'View archiving overview page and sub-pages';
$string['archiving:create'] = 'Create new archives';
$string['archiving:delete'] = 'Delete archives and metadata';

// Archiving overview.
$string['activity_archiving_list_desc'] = 'Below you can find a list of all activities in this course. Supported activities can be archived by clicking the respective entry in the list. If an activity is not supported, it will be disabled.';
$string['archive_jobs_course_table_desc'] = 'This table lists all archive jobs that have been created for any of the activities in this course.';
$string['archiving_course_overview_desc'] = 'This page is allows to create and access archives for all supported activities in this course.';
$string['badge_archived_help'] = 'This activity was successfully archived at the given time.';
$string['badge_cannot_be_archived_help'] = 'This activity can currently not be archived due to an unknown reason.';
$string['badge_disabled_help'] = 'Archiving this activity type is currently disabled by the system administrator.';
$string['badge_never_archived_help'] = 'This activity was never archived.';
$string['badge_new_changes_help'] = 'There has been new changes to the activity since the last successful archiving job.';
$string['badge_not_ready_help'] = 'This activity is currently not ready to be archived.';
$string['badge_unsupported_help'] = 'This activity type is currently not supported by the archiving system.';
$string['hide_unsupported_activities'] = 'Hide unsupported activities';
$string['show_all_activities'] = 'Show all activities';

// Job management.
$string['a_archive_jobs'] = '{$a} archive jobs';
$string['activity_archiving_task_failed'] = 'Activity archiving task failed';
$string['archive_job'] = 'Archive job';
$string['archive_jobs'] = 'Archive jobs';
$string['archive_job_created_details'] = 'A new archive job (<code>#{$a->jobid}</code>) for the activity "<code>{$a->cmname}</code>" has been created successfully.';
$string['archive_job_logs'] = 'Archive job logs';
$string['archive_job_logs_desc'] = 'Archive jobs log certain events and actions during execution. Below you can find a list of all logged events for this archive job. If you need more information, please ask your Moodle administrator to increase the log level on the admin settings page of this plugin.';
$string['archive_jobs_cm_table_desc'] = 'The table below lists all archive jobs that have been created for this activity. To list all archive jobs in this course, please go to the course archiving overview page.';
$string['archived'] = 'Archived';
$string['delete_job'] = 'Delete archive job';
$string['delete_job_warning'] = 'Are you sure that you want to delete this archive job <b>including all archived data?</b>';
$string['delete_job_artifact_file'] = 'Delete job artifact file';
$string['delete_job_artifact_file_warning'] = 'Are you sure that you want to delete this job artifact file, hereby <b>erasing all archived data</b> that ist contained within this file?';
$string['download_job_artifacts'] = 'Download job artifacts';
$string['download_job_artifacts_desc'] = 'This page lists all archived artifacts that were generated by the selected archive job below. You can download each file by clicking on the download button for the specific file.';
$string['never_archived'] = 'Never archived';
$string['no_logs_available_desc'] = 'There are no logs available for this archive job. If you need more information, please ask your system administrator to increase the log level on the admin settings page of this plugin.';
$string['not_ready'] = 'Not ready';
$string['not_signed'] = 'Not signed';
$string['signature'] = 'Signature';
$string['signed_on'] = 'Signed on';
$string['signed_by'] = 'by';
$string['target_activity'] = 'Target activity';
$string['this_file_was_deleted'] = 'This file was deleted';
$string['tsp_query_filename'] = 'query.tsq';
$string['tsp_reply_filename'] = 'reply.tsr';

// Job status.
$string['job_status_0'] = 'Uninitialized';
$string['job_status_0_help'] = 'The job has not been initialized yet.';
$string['job_status_10'] = 'Queued';
$string['job_status_10_help'] = 'The job has been initialized and waits for execution.';
$string['job_status_20'] = 'Pre-Processing';
$string['job_status_20_help'] = 'The job just started and does pre-processing work.';
$string['job_status_30'] = 'Archiving';
$string['job_status_30_help'] = 'The targeted activity is currently being archived.';
$string['job_status_40'] = 'Backup Collection';
$string['job_status_40_help'] = 'Moodle backups are being collected.';
$string['job_status_50'] = 'Post-Processing';
$string['job_status_50_help'] = 'The activity data is currently being post-processed.';
$string['job_status_60'] = 'Storing';
$string['job_status_60_help'] = 'The archived data is currently being transferred to storage.';
$string['job_status_70'] = 'Signing';
$string['job_status_70_help'] = 'Job artifacts are currently being cryptographically signed.';
$string['job_status_90'] = 'Cleanup';
$string['job_status_90_help'] = 'The job is being finalized and cleanup tasks are performed.';
$string['job_status_100'] = 'Completed';
$string['job_status_100_help'] = 'The job has been completed successfully.';
$string['job_status_110'] = 'Deleted';
$string['job_status_110_help'] = 'The archived data has been removed. The job metadata still exists and can be fully deleted, if required';
$string['job_status_200'] = 'Error';
$string['job_status_200_help'] = 'An error occurred during the job processing that is yet to be triaged.';
$string['job_status_210'] = 'Recoverable Error';
$string['job_status_210_help'] = 'A recoverable error occurred. The job will be retried soon.';
$string['job_status_220'] = 'Error Handling';
$string['job_status_220_help'] = 'An error occurred during the job processing that is currently being handled.';
$string['job_status_230'] = 'Timeout';
$string['job_status_230_help'] = 'The job has been aborted due to a timeout. This can happen for very large activities. Please contact your system administrator if this problem persists.';
$string['job_status_240'] = 'Failure';
$string['job_status_240_help'] = 'The job has failed. Please try again and contact your system administrator if this problem persists.';
$string['job_status_255'] = 'Unknown';
$string['job_status_255_help'] = 'The job status is unknown. Please open a bug report if this problem persists.';

// Job metadata fields.
$string['job_metadata_activity_archiving_driver'] = 'Activity archiving driver';
$string['job_metadata_course_backup_id'] = 'Course backup ID';
$string['job_metadata_cm_backup_id'] = 'Activity backup ID';
$string['job_metadata_num_attempts'] = 'Number of attempts';
$string['job_metadata_num_attachments'] = 'Number of attachments';
$string['job_metadata_storage_driver'] = 'Storage driver';

// Job settings: General.
$string['export_course_backup'] = 'Export full Moodle course backup (.mbz)';
$string['export_course_backup_help'] = 'This will export a full Moodle course backup (.mbz) including everything inside this course. This can be useful if you want to import this course into another Moodle instance.';
$string['export_cm_backup'] = 'Export Moodle activity backup (.mbz)';
$string['export_cm_backup_help'] = 'This will export a Moodle backup (.mbz) of the targeted activity. This can be useful if you want to import this activity independent of this course into another Moodle instance.';
$string['job_create_form_header'] = 'Create Archive';
$string['job_create_form_header_desc'] = 'This form triggers the creation of a new archive. Jobs are processed asynchronously in the background and take some time to complete. You can always check the current status on the overview page.';
$string['job_create_form_header_typed'] = 'Create {$a} Archive';
$string['storage_location'] = 'Storage location';
$string['storage_location_help'] = 'Select where the archived data should be stored.';

// Job settings: Filename pattern.
$string['archive_filename_pattern'] = 'Archive name';
$string['archive_filename_pattern_help'] = 'Name of the generated archive. Variables <b>must</b> follow the <code>${variablename}</code> pattern. The file extension will be added automatically.<br><br><b>Available variables</b>: <ul>{$a->variables}</ul><b>Forbidden characters</b>: <code>{$a->forbiddenchars}</code>';
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
$string['setting_header_managecomponents_desc'] = 'This page allows you to manage all sub-plugins of the archiving system. You can enable or disable sub-plugins individually, according to your needs. You can also configure each sub-plugin individually by clicking on its respective settings link. To configure global settings of the archiving system, please navigate to the <a href="{$a}">common settings</a> page.';
$string['setting_header_common_desc'] = 'This section contains common settings that apply globally to all components of the archiving system. To configure individual components, please navigate to their respective configuration pages, as found on the <a href="{$a}">manage components</a> page.';
$string['setting_header_job_presets'] = 'Archive Presets';
$string['setting_header_job_presets_desc'] = 'System wide default settings for archive creation. These defaults can be overridden when creating a new archive. However, each individual setting can also be locked to prevent managers / teachers from changing it. This can be useful when enforcing organization wide archive policies.';
$string['setting_header_tsp'] = 'Archive Signing';
$string['setting_header_tsp_desc'] = 'Archives and their creation date can be digitally signed by a trusted authority using the <a href="https://en.wikipedia.org/wiki/Time_stamp_protocol" target="_blank">Time-Stamp Protocol (TSP)</a> according to <a href="https://www.ietf.org/rfc/rfc3161.txt" target="_blank">RFC 3161</a>. This can be used to cryptographically prove the integrity and creation date of the archive at a later point in time. Archives can be signed automatically at creation or manually later on.';
$string['setting_job_timeout_min'] = 'Job timeout (minutes)';
$string['setting_job_timeout_min_desc'] = 'The number of minutes a single archive job is allowed to run before it is aborted by Moodle. Job web service access tokens become invalid after this timeout.<br/>Note: Additional timeouts can be present in sub-plugins and archive worker services. The shorter timeout always takes precedence.';
$string['setting_log_level'] = 'Log level';
$string['setting_log_level_desc'] = 'The minimum level of events to be logged by the archiving system. All log entries with a lower level are ignored. This setting can be used to increase or reduce the amount of logged events. This only affects internal task logging and does not affect the produced archives in any way.';
$string['setting_tsp_automatic_signing'] = 'Automatically sign archives';
$string['setting_tsp_automatic_signing_desc'] = 'Automatically sign archives when they are created.';
$string['setting_tsp_enable'] = 'Enable archive signing';
$string['setting_tsp_enable_desc'] = 'Allow archives to be signed using the Time-Stamp Protocol (TSP). If this option is disabled, archives can neither be signed manually nor automatically.';
$string['setting_tsp_server_url'] = 'TSP server URL';
$string['setting_tsp_server_url_desc'] = 'URL of the Time-Stamp Protocol (TSP) server to use.<br/>Examples: <code>https://freetsa.org/tsr</code>, <code>https://zeitstempel.dfn.de</code>, <code>http://timestamp.digicert.com</code>';
$string['manage'] = 'Manage';
$string['manage_components'] = 'Manage components';
$string['manage_components_archivingmod_desc'] = 'The activity archiving drivers are responsible for the actual archiving process of a specific Moodle activity. One such driver exists for every Moodle activity that is supported by the archiving system.';
$string['manage_components_archivingstore_desc'] = 'Storage drivers are responsible for taking a freshly create archive and transferring it to a specific storage location designated for long-term storage or post-processing.';
$string['manage_components_archivingevent_desc'] = 'External event connectors allow forwarding of specific events within the archiving system to external services, such as campus management systems. This can be used to trigger specific actions in external systems, such as storing the path to an archived exam file for a given student inside a student record.';
$string['error_localpath_must_be_absolute'] = 'Must be an absolute path.';
$string['error_localpath_cloud_not_be_created'] = 'Could not create directory. Please make sure that the parent directory exists and is writable by the web server user.';
$string['error_localpath_must_be_directory'] = 'A file with the given name already exists but a directory is expected.';

// Storage.
$string['storage_tier'] = 'Storage tier';
$string['storage_tier_LOCAL'] = 'Local';
$string['storage_tier_LOCAL_help'] = 'Data is stored locally on the server or is accessible very fast.';
$string['storage_tier_REMOTE_fast'] = 'Remote (fast)';
$string['storage_tier_REMOTE_fast_help'] = 'Data is stored on a fast remote server but needs to be fetched before use.';
$string['storage_tier_REMOTE_slow'] = 'Remote (slow)';
$string['storage_tier_REMOTE_slow_help'] = 'Data is stored on a slow remote server but needs to be fetched before use.';
$string['storage_usage'] = 'Usage';

// TSP client.
$string['tsp_client_error_content_type'] = 'TSP server returned unexpected content type {$a}';
$string['tsp_client_error_curl'] = 'Error while sending TSP request: {$a}';
$string['tsp_client_error_http_code'] = 'TSP server returned HTTP status code {$a}';
