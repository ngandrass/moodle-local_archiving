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
use local_archiving\logging\logger;
use local_archiving\type\archive_job_status;

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
$jobmeta = $job->get_metadata_entries();
$renderer = $PAGE->get_renderer('local_archiving');
echo $OUTPUT->header();
echo $renderer->render_from_template('local_archiving/job_logs', [
    'job' => [
        'id' => $job->get_id(),
        'triggername' => get_string('pluginname', 'archivingtrigger_' . $job->get_trigger()) ?: $job->get_trigger(),
        'status' => $job->get_status()->status_display_args(),
        'completed' => $job->is_completed(),
        'timecreated' => $job->get_timecreated(),
        'timemodified' => $job->get_timemodified(),
        'logs' => array_reduce(
            $job->get_logger()->get_logs(),
            fn ($log, $entry) => $log . logger::format_log_entry($entry) . "\r\n",
            ""
        ),
        // TODO (MDL-0): Move this to a separate archive inspection page.
        "metadata" => array_map(
            fn($key, $value): array => [
                'key' => $key,
                'humankey' => get_string("job_metadata_{$key}", 'local_archiving'),
                'value' => $value,
            ],
            array_keys($jobmeta),
            array_values($jobmeta)
        ),
    ],
    'urls' => [
        'back' => new moodle_url('/local/archiving/index.php', ['courseid' => $course->id]),
        'refresh' => $PAGE->url,
    ],
]);
echo $OUTPUT->footer();
