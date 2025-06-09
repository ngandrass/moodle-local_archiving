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
 * Job artifact download handler
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\archive_job;
use local_archiving\file_handle;
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
    '/local/archiving/download.php',
    ['jobid' => $jobid]
));
$renderer = $PAGE->get_renderer('local_archiving');
$html = "";

// Only allow successfully finished jobs.
if (!$job->get_status() == archive_job_status::COMPLETED) {
    throw new \moodle_exception('job_not_completed', 'local_archiving');
}

// Get file handles for this job.
$filehandles = file_handle::get_by_jobid($job->get_id());
if (count($filehandles) == 0) {
    // No file handles found, display error message.
    $html .= $OUTPUT->notification(get_string('no_files_found', 'local_archiving'), 'error');
} else {
    // Files found, prepare template context.
    $tplctx = [
        "job" => [
            "id" => $job->get_id(),
            "timecreated" => $job->get_timecreated(),
            "cm" => [
                "id" => $cm->id,
                "name" => $cm->name,
                "url" => $cm->url,
            ],
        ],
        "urls" => [
            "back" => new moodle_url('/local/archiving/index.php', ['courseid' => $course->id]),
        ],
        "files" => [],
    ];

    // Add all job files to template context and render the template.
    foreach ($filehandles as $filehandle) {
        $localfile = $filehandle->retrieve_file();

        $downloadurl = moodle_url::make_pluginfile_url(
            $localfile->get_contextid(),
            $localfile->get_component(),
            $localfile->get_filearea(),
            $localfile->get_itemid(),
            $localfile->get_filepath(),
            $localfile->get_filename(),
            forcedownload: true
        );

        $tplctx['files'][] = [
            'filename' => $localfile->get_filename(),
            'filesize' => display_size($localfile->get_filesize()),
            'filetype' => $localfile->get_mimetype(),
            'timecreated' => $filehandle->timecreated,
            'sha256sum' => $filehandle->sha256sum,
            'storagedriver' => get_string('pluginname', "archivingstore_{$filehandle->archivingstorename}"),
            'downloadurl' => $downloadurl->out(false),
        ];
    }

    $html .= $renderer->render_from_template('local_archiving/download_job_artifacts', $tplctx);
}

// If not redirected, render output.
echo $OUTPUT->header();
echo $html;
echo $OUTPUT->footer();
