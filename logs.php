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
 * Logging overview page
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\archive_job;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE, $USER;

// Try to find job.
$jobid = required_param('jobid', PARAM_INT);
$job = archive_job::get_by_id($jobid);
$ctx = $job->get_context();
list($course, $cm) = get_course_and_cm_from_cmid($ctx->instanceid);

// Check login and capabilities.
require_login($course);
require_capability('local/archiving:view', $ctx->get_course_context());

// Setup page.
$PAGE->set_context($ctx->get_course_context());
$PAGE->set_title(get_string('pluginname', 'local_archiving'));
$PAGE->set_heading($cm->name);
$PAGE->set_url(new moodle_url(
    '/local/archiving/logs.php',
    ['jobid' => $jobid]
));

// Render output.
$renderer = $PAGE->get_renderer('local_archiving');
echo $OUTPUT->header();

// vvvvv DEBUG start vvvvv

echo "<h2>Logs for job {$jobid} ({$job->get_status()->name()})</h2>";

echo "<pre>";
foreach ($job->get_logger()->get_logs() as $entry) {
    echo \local_archiving\logging\logger::format_log_entry($entry)."\r\n";
}
echo "</pre>";

$backurl = new moodle_url('/local/archiving/index.php', ['courseid' => $course->id]);
echo "<a href=\"{$backurl}\">Go back to overview</a>";

// ^^^^^ DEBUG end ^^^^^

echo $OUTPUT->footer();
