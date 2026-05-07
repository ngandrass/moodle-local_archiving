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
 * @package     archivingmod_quiz
 * @category    string
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// @codingStandardsIgnoreFile

// Common.
$string['pluginname'] = 'Quiz';
$string['a'] = '{$a}';
$string['archived'] = 'Archived';

// Privacy.
$string['privacy:metadata'] = 'This plugin does not store any personal data directly, but passes everything to local_archiving instead.';

// Task settings: General.
$string['task_export_attempts'] = 'Export quiz attempts';
$string['task_export_attempts_help'] = 'Quiz attempts will always be exported';
$string['task_export_attempts_num'] = 'Export quiz attempts ({$a})';
$string['task_export_attempts_num_help'] = 'Quiz attempts will always be exported';
$string['task_keep_html_files'] = 'HTML files';
$string['task_keep_html_files_desc'] = 'Keep HTML source files';
$string['task_keep_html_files_help'] = 'Save HTML source files in addition to the generated PDFs during the export process. This can be useful if you want to access the raw HTML DOM the PDFs were generated from. Disabling this option can significantly reduce the archive size.';
$string['task_paper_format'] = 'Paper size';
$string['task_paper_format_help'] = 'The paper size to use for the PDF export. This does not not affect HTML exports.';

// Task settings: Attempt report sections
$string['task_report_section_header'] = 'Include quiz header';
$string['task_report_section_header_help'] = 'Display quiz metadata (e.g., user, time taken, grade, ...) inside the attempt report.';
$string['task_report_section_question'] = 'Include questions';
$string['task_report_section_question_help'] = 'Display all questions that are part of this attempt inside the attempt report.';
$string['task_report_section_rightanswer'] = 'Include correct answers';
$string['task_report_section_rightanswer_help'] = 'Display the correct answers for each question inside the attempt report.';
$string['task_report_section_quiz_feedback'] = 'Include overall quiz feedback';
$string['task_report_section_quiz_feedback_help'] = 'Display the overall quiz feedback inside the attempt report header.';
$string['task_report_section_question_feedback'] = 'Include individual question feedback';
$string['task_report_section_question_feedback_help'] = 'Display the individual feedback for each question inside the attempt report.';
$string['task_report_section_general_feedback'] = 'Include general question feedback';
$string['task_report_section_general_feedback_help'] = 'Display the general feedback for each question inside the attempt report.';
$string['task_report_section_history'] = 'Include answer history';
$string['task_report_section_history_help'] = 'Display the answer history for each question inside the attempt report.';
$string['task_report_section_attachments'] = 'Include file attachments';
$string['task_report_section_attachments_help'] = 'Include all file attachments (e.g., essay file submissions) inside the archive. Warning: This can significantly increase the archive size.';

// Task settings: Optimization.
$string['task_image_optimize'] = 'Optimize images';
$string['task_image_optimize_help'] = 'If enabled, images inside the quiz attempt reports will compressed and large images will be shrunk with respect to the specified dimensions. Images will only ever be scaled down. This only affects PDF exports. HTML source files will always keep the original image size.';
$string['task_image_optimize_group'] = 'Maximum image dimensions';
$string['task_image_optimize_group_help'] = 'Maximum dimensions for images inside the quiz attempt reports in pixels (width x height). If an image is larger than the given width or height, it will be scaled down so that it fully fits into the given dimensions while maintaining its aspect ratio. This can be useful to reduce the overall archive size if large images are used within the quiz.';
$string['task_image_optimize_height'] = 'Maximum image height';
$string['task_image_optimize_height_help'] = 'Maximum height of images inside the quiz attempt reports in pixels. If an images height is larger than the given height, it will be scaled down to the given height while maintaining its aspect ratio.';
$string['task_image_optimize_quality'] = 'Image compression';
$string['task_image_optimize_quality_help'] = 'Quality of compressed images (0 - 100 %). The higher the quality, the larger the file size. This behaves like JPEG compression intensity. A good default value is 85 %.';
$string['task_image_optimize_width'] = 'Maximum image width';
$string['task_image_optimize_width_help'] = 'Maximum width of images inside the quiz attempt reports in pixels. If an images width is larger than the given width, it will be scaled down to the given width while maintaining its aspect ratio.';

// Task settings: Filename pattern.
$string['task_attempt_filename_pattern'] = 'Attempt name';
$string['task_attempt_filename_pattern_help'] = 'Name of the generated quiz attempt reports (PDF files). Variables <b>must</b> follow the <code>${variablename}</code> pattern. The file extension will be added automatically.<br><br><b>Available variables</b>: <ul>{$a->variables}</ul><b>Forbidden characters</b>: <code>{$a->forbiddenchars}</code>';
$string['task_attempt_filename_pattern_variable_courseid'] = 'Course ID';
$string['task_attempt_filename_pattern_variable_coursename'] = 'Course name';
$string['task_attempt_filename_pattern_variable_courseshortname'] = 'Course short name';
$string['task_attempt_filename_pattern_variable_cmid'] = 'Course module ID';
$string['task_attempt_filename_pattern_variable_groupids'] = 'Group IDs';
$string['task_attempt_filename_pattern_variable_groupidnumbers'] = 'Group ID numbers';
$string['task_attempt_filename_pattern_variable_groupnames'] = 'Group names';
$string['task_attempt_filename_pattern_variable_quizid'] = 'Quiz ID';
$string['task_attempt_filename_pattern_variable_quizname'] = 'Quiz name';
$string['task_attempt_filename_pattern_variable_attemptid'] = 'Attempt ID';
$string['task_attempt_filename_pattern_variable_username'] = 'Student username';
$string['task_attempt_filename_pattern_variable_firstname'] = 'Student first name';
$string['task_attempt_filename_pattern_variable_lastname'] = 'Student last name';
$string['task_attempt_filename_pattern_variable_idnumber'] = 'Student ID number';
$string['task_attempt_filename_pattern_variable_timestart'] = 'Attempt start unix timestamp';
$string['task_attempt_filename_pattern_variable_timefinish'] = 'Attempt finish unix timestamp';
$string['task_attempt_filename_pattern_variable_date'] = 'Current date <small>(YYYY-MM-DD)</small>';
$string['task_attempt_filename_pattern_variable_time'] = 'Current time <small>(HH-MM-SS)</small>';
$string['task_attempt_filename_pattern_variable_timestamp'] = 'Current unix timestamp';
$string['task_attempt_foldername_pattern'] = 'Attempt folder name';
$string['task_attempt_foldername_pattern_help'] = 'Name of the folder(s) the generated quiz attempt reports (PDF files) are stored in. Directories can be nested using slashes. Leading and trailing slashes are not allowed. Variables <b>must</b> follow the <code>${variablename}</code> pattern.<br><br><b>Available variables</b>: <ul>{$a->variables}</ul><b>Forbidden characters</b>: <code>{$a->forbiddenchars}</code>';
$string['error_invalid_attempt_filename_pattern'] = 'Invalid attempt report filename pattern. Please correct your input and try again.';
$string['error_invalid_attempt_foldername_pattern'] = 'Invalid attempt report folder name pattern. Please correct your input and try again.';

// Admin settings.
$string['setting_autoconfigure'] = 'Automatic configuration';
$string['setting_enabled'] = 'Enabled';
$string['setting_enabled_desc'] = 'Enables or disables this activity archiving driver. If disabled, no activities can be archived using this driver.';
$string['setting_header_archive_worker'] = 'Archive Worker Service';
$string['setting_header_archive_worker_desc'] = 'Configuration of the archive worker service and the Moodle web service it uses.';
$string['setting_header_docs_desc'] = 'This plugin archives quiz attempts as PDF and HTML files. It <b>requires a separate <a href="https://quizarchiver.gandrass.de/installation/archiveworker/" target="_blank">worker service</a></b> to be installed for the actual archiving process to work. Please refer to the <a href="https://quizarchiver.gandrass.de/" target="_blank">documentation</a> for more details and setup instructions.';
$string['setting_internal_wwwroot'] = 'Custom Moodle base URL';
$string['setting_internal_wwwroot_desc'] = 'Overwrites the default Moodle base URL (<code>$CFG->wwwroot</code>) inside generated attempt reports. This can be useful if you are running the archive worker service inside a private network (e.g., Docker) and want it to access Moodle directly.<br/>Example: <code>http://moodle/</code>';
$string['setting_webservice_enabler'] = 'Moodle web services';
$string['setting_webservice_enabler_desc'] = 'This plugin uses Moodle web services to communicate with the worker service. Therefore, web services and the REST protocol must be enabled for this plugin to work. You can check the current status below. If everything reads green, you are ready to go.';
$string['setting_worker_url'] = 'Archive worker URL';
$string['setting_worker_url_desc'] = 'URL of the archive worker service to call for quiz archive task execution. If you only want to try the Quiz Archiver, you can use the <a href="https://quizarchiver.gandrass.de/installation/archiveworker/#using-the-free-public-demo-service" target="_blank">free public demo quiz archive worker service</a>, eliminating the need to set up your own worker service right away.<br/>Example: <code>http://127.0.0.1:8080</code> or <code>http://moodle-quiz-archive-worker:8080</code>';

// Remote archive worker.
$string['remote_worker_enqueue_job_failed'] = 'Failed to enqueue archive job at the remote archive worker service.';
$string['remote_worker_enqueue_job_failed_a'] = 'Failed to enqueue archive job at the remote archive worker service: {$a}';
$string['remote_worker_missing_return_param'] = 'The remote archive worker service did not return the expected response. Missing parameter: {$a}';
