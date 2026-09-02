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
 * @package     archivingmod_assign
 * @category    string
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreFile

$string['a'] = '{$a}';
$string['cutoffdate'] = 'Cut-off date';
$string['duedate'] = 'Due date';
$string['error_invalid_submission_filename_pattern'] = 'Invalid submission report filename pattern. Please correct your input and try again.';
$string['error_invalid_submission_foldername_pattern'] = 'Invalid submission report folder name pattern. Please correct your input and try again.';
$string['gradingduedate'] = 'Grading due date';
$string['openingdate'] = 'Opening date';
$string['pluginname'] = 'Assignment';
$string['privacy:metadata'] = 'This plugin does not store any personal data directly, but passes everything to local_archiving instead.';
$string['remote_worker_enqueue_job_failed'] = 'Failed to enqueue archive job at the remote archive worker service.';
$string['remote_worker_enqueue_job_failed_a'] = 'Failed to enqueue archive job at the remote archive worker service: {$a}';
$string['remote_worker_missing_return_param'] = 'The remote archive worker service did not return the expected response. Missing parameter: {$a}';
$string['setting_enabled'] = 'Enabled';
$string['setting_enabled_desc'] = 'Enables or disables this activity archiving driver. If disabled, no activities can be archived using this driver.';
$string['setting_header_archive_worker'] = 'Archive Worker Service';
$string['setting_header_archive_worker_desc'] = 'Configuration of the archive worker service and the Moodle web service it uses.';
$string['setting_header_docs_desc'] = 'This plugin archives assignment submissions. It generates a PDF report for every submission and bundles it together with all supplied files (e.g., submitted files, assignment instructions, annotated PDFs, ...). It <b>requires a separate <a href="https://quizarchiver.gandrass.de/installation/archiveworker/" target="_blank">worker service</a></b> to be installed for the actual archiving process to work. Please refer to the <a href="https://quizarchiver.gandrass.de/" target="_blank">documentation</a> for more details and setup instructions.';
$string['setting_internal_wwwroot'] = 'Custom Moodle base URL';
$string['setting_internal_wwwroot_desc'] = 'Overwrites the default Moodle base URL (<code>$CFG->wwwroot</code>) inside generated assignment submission reports. This can be useful if you are running the archiving worker service inside a private network (e.g., Docker) and want it to access Moodle directly.<br/>Example: <code>http://moodle/</code>';
$string['setting_webservice_enabler'] = 'Moodle web services';
$string['setting_webservice_enabler_desc'] = 'This plugin uses Moodle web services to communicate with the worker service. Therefore, web services and the REST protocol must be enabled for this plugin to work. You can check the current status below. If everything reads green, you are ready to go.';
$string['setting_worker_url'] = 'Archive worker URL';
$string['setting_worker_url_desc'] = 'URL of the archive worker service to call for assignment archiving task execution. If you only want to try the Assignment Archiver, you can use the <a href="https://quizarchiver.gandrass.de/installation/archiveworker/#using-the-free-public-demo-service" target="_blank">free public demo archive worker service</a>, eliminating the need to set up your own worker service right away.<br/>Example: <code>http://127.0.0.1:8080</code> or <code>http://moodle-archiving-worker:8080</code>';
$string['submission'] = 'Submission';
$string['submission_files'] = 'Submission files';
$string['submission_report'] = 'Submission report';
$string['task_archive_flatten'] = 'Flatten archive';
$string['task_archive_flatten_help'] = 'If enabled, all directories inside the exported archive will be omitted. All files will be placed in the root directory of the generated archive. Please make sure to <b>choose a unique submission filename!</b>';
$string['task_attachment_annotation'] = 'Annotated PDF files';
$string['task_attachment_annotation_help'] = 'Include PDF files that were annotated by the grader. This does not include additional files that were uploaded by the grader.';
$string['task_attachment_assignment'] = 'Intro files';
$string['task_attachment_assignment_help'] = 'Include files attached to the assignment introduction. These are files that are given alongside the assignment instructions and were provided by the assignment creator.';
$string['task_attachment_feedback'] = 'Feedback files';
$string['task_attachment_feedback_help'] = 'Include feedback files uploaded by the grader. This does not include PDFs that were annotated by the grader within Moodle.';
$string['task_attachment_submission'] = 'Submission files';
$string['task_attachment_submission_help'] = 'Include files submitted by the student during the submission.';
$string['task_image_optimize'] = 'Optimize images';
$string['task_image_optimize_group'] = 'Maximum image dimensions';
$string['task_image_optimize_group_help'] = 'Maximum dimensions for images inside the submission reports in pixels (width x height). If an image is larger than the given width or height, it will be scaled down so that it fully fits into the given dimensions while maintaining its aspect ratio. This can be useful to reduce the overall archive size if large images are used within the assignment submission.';
$string['task_image_optimize_height'] = 'Maximum image height';
$string['task_image_optimize_height_help'] = 'Maximum height in pixels that images in submission reports will be scaled down to. Images smaller than this value will not be upscaled.';
$string['task_image_optimize_help'] = 'If enabled, images embedded in submission reports will be scaled down and recompressed to reduce file size.';
$string['task_image_optimize_quality'] = 'Image compression';
$string['task_image_optimize_quality_help'] = 'Quality (0 – 100%) to use when recompressing images. Higher values produce better quality but larger files.';
$string['task_image_optimize_width'] = 'Maximum image width';
$string['task_image_optimize_width_help'] = 'Maximum width in pixels that images in submission reports will be scaled down to. Images smaller than this value will not be upscaled.';
$string['task_keep_html_files'] = 'HTML files';
$string['task_keep_html_files_desc'] = 'Keep HTML source files';
$string['task_keep_html_files_help'] = 'Save HTML source files in addition to the generated PDFs during the export process. This can be useful if you want to access the raw HTML DOM the PDFs were generated from. Disabling this option can significantly reduce the archive size.';
$string['task_paper_format'] = 'Paper size';
$string['task_paper_format_help'] = 'The paper size to use for the PDF export. This does not not affect HTML exports.';
$string['task_report_section_feedback'] = 'Feedback';
$string['task_report_section_feedback_help'] = 'Include the feedback section in the submission report.';
$string['task_report_section_feedbackcomments'] = 'Feedback comments';
$string['task_report_section_feedbackcomments_help'] = 'Include feedback comments left by the grader.';
$string['task_report_section_grade'] = 'Grade';
$string['task_report_section_grade_help'] = 'Include the assigned grade in points of the maximum achievable grade.';
$string['task_report_section_gradedetails'] = 'Grading details';
$string['task_report_section_gradedetails_help'] = 'Include grading details (grader identity and grading time).';
$string['task_report_section_header'] = 'Assignment header';
$string['task_report_section_header_help'] = 'Include assignment metadata (title, dates, instructions) as a header section.';
$string['task_report_section_instructions'] = 'Instructions';
$string['task_report_section_instructions_help'] = 'Include the assignment instructions. These are the instructions that are provided by the assignment creator.';
$string['task_report_section_submission'] = 'Submission';
$string['task_report_section_submission_help'] = 'Include the submission details (submitted text, submission time).';
$string['task_report_section_submissioncomments'] = 'Submission comments';
$string['task_report_section_submissioncomments_help'] = 'Include comments left on the submission.';
$string['task_report_section_submissionstatus'] = 'Submission status';
$string['task_report_section_submissionstatus_help'] = 'Include the submission and grading status. This does not include any grade, but only shows whether a submission was already graded or not.';
$string['task_submission_filename_pattern'] = 'Submission file name';
$string['task_submission_filename_pattern_help'] = 'Name of the generated submission reports (PDF files). Variables <b>must</b> follow the <code>${variablename}</code> pattern. The file extension will be added automatically.<br><br><b>Available variables</b>: <ul>{$a->variables}</ul><b>Forbidden characters</b>: <code>{$a->forbiddenchars}</code>';
$string['task_submission_filename_pattern_variable_assignmentid'] = 'Assignment ID';
$string['task_submission_filename_pattern_variable_assignmenttitle'] = 'Assignment title';
$string['task_submission_filename_pattern_variable_attemptnumber'] = 'Submission number';
$string['task_submission_filename_pattern_variable_cmid'] = 'Course module ID';
$string['task_submission_filename_pattern_variable_courseid'] = 'Course ID';
$string['task_submission_filename_pattern_variable_coursename'] = 'Course name';
$string['task_submission_filename_pattern_variable_courseshortname'] = 'Course short name';
$string['task_submission_filename_pattern_variable_date'] = 'Current date <small>(YYYY-MM-DD)</small>';
$string['task_submission_filename_pattern_variable_firstname'] = 'Student first name';
$string['task_submission_filename_pattern_variable_groupidnumbers'] = 'Group ID numbers';
$string['task_submission_filename_pattern_variable_groupids'] = 'Group IDs';
$string['task_submission_filename_pattern_variable_groupnames'] = 'Group names';
$string['task_submission_filename_pattern_variable_idnumber'] = 'Student ID number';
$string['task_submission_filename_pattern_variable_lastname'] = 'Student last name';
$string['task_submission_filename_pattern_variable_submissionid'] = 'Submission ID';
$string['task_submission_filename_pattern_variable_time'] = 'Current time <small>(HH-MM-SS)</small>';
$string['task_submission_filename_pattern_variable_timecreated'] = 'Submission creation time';
$string['task_submission_filename_pattern_variable_timemodified'] = 'Submission modification time';
$string['task_submission_filename_pattern_variable_timestamp'] = 'Current UNIX timestamp';
$string['task_submission_filename_pattern_variable_timestart'] = 'Submission start time';
$string['task_submission_filename_pattern_variable_username'] = 'Student username';
$string['task_submission_foldername_pattern'] = 'Submission folder name';
$string['task_submission_foldername_pattern_help'] = 'Name of the folder(s) the generated submission reports (PDF files) are stored in. Directories can be nested using slashes. Leading and trailing slashes are not allowed. Variables <b>must</b> follow the <code>${variablename}</code> pattern.<br><br><b>Available variables</b>: <ul>{$a->variables}</ul><b>Forbidden characters</b>: <code>{$a->forbiddenchars}</code>';
