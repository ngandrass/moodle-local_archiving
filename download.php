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
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\archive_job;
use local_archiving\file_handle;
use local_archiving\local\type\archive_job_status;
use local_archiving\local\type\file_fetch_status;
use local_archiving\local\type\storage_tier;
use local_archiving\remote_file_fetcher;
use local_archiving\local\util\time_util;
use local_archiving\tsp_manager;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE, $USER;

// Try to find job.
$jobid = required_param('jobid', PARAM_INT);
$job = archive_job::get_by_id($jobid);
$ctx = $job->get_context();
[$course, $cm] = get_course_and_cm_from_cmid($ctx->instanceid);

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

    // Sort file handles. Display existing files first, sorted by their name. Then display deleted files, sorted by their name.
    usort($filehandles, fn($a, $b) => $a->deleted <=> $b->deleted ?: $a->filename <=> $b->filename);

    // Tracks whether any file needs a live progress refresh (set while looping below).
    $needsrefresh = false;

    // Add all job files to template context and render the template.
    foreach ($filehandles as $filehandle) {
        // Build the file object, starting from the "deleted/unavailable" defaults that will be populated below if applicable.
        $file = [
            'deleted' => $filehandle->deleted,
            'filename' => $filehandle->filename,
            'filesize' => display_size($filehandle->filesize),
            'filetype' => $filehandle->mimetype,
            'timecreated' => $filehandle->timecreated,
            'timemodified' => $filehandle->timemodified,
            'retentiontime' => null,
            'sha256sum' => $filehandle->sha256sum,
            'tsp' => null,
            'storagedriver' => get_string('pluginname', "archivingstore_{$filehandle->archivingstorename}"),
            'downloadurl' => null,
            'deleteurl' => null,
            'fetch' => null,
        ];

        // Retrieve non-deleted files.
        if (!$filehandle->deleted) {
            $tier = $filehandle->archivingstore()::get_storage_tier();

            if ($tier === storage_tier::LOCAL) {
                // Local files are transparently fetched synchronously by the pluginfile handler.
                $file['downloadurl'] = $filehandle->get_local_download_url()->out(false);
            } else if ($filehandle->get_local_file()) {
                // Already cached files can be downloaded directly.
                $file['downloadurl'] = $filehandle->get_local_download_url()->out(false);
            } else {
                // Remote files that have not yet been cached need to be fetched asynchronously.
                $fetch = [
                    'url' => (new moodle_url('/local/archiving/fetch.php', [
                        'filehandleid' => $filehandle->id,
                        'contextid' => $ctx->id,
                    ]))->out(false),
                    'cancelurl' => (new moodle_url('/local/archiving/fetch.php', [
                        'filehandleid' => $filehandle->id,
                        'contextid' => $ctx->id,
                        'action' => 'cancel',
                        'sesskey' => sesskey(),
                    ]))->out(false),
                    'queued' => false,
                    'fetching' => false,
                    'percent' => 0,
                    'bytestransferred' => null,
                    'bytestotal' => null,
                    'failed' => false,
                    'error' => null,
                    'showcancel' => false,
                    'cancelling' => false,
                ];

                $fetchrecord = remote_file_fetcher::get($filehandle->id);
                $cancelling = false;
                if ($fetchrecord !== null) {
                    if ($fetchrecord['status'] === file_fetch_status::QUEUED->value) {
                        // Fetch task is queued.
                        $fetch['queued'] = true;
                    } else if ($fetchrecord['status'] === file_fetch_status::FETCHING->value) {
                        // Fetch task is being executed.
                        $fetch['fetching'] = true;
                        $fetch['bytestransferred'] = display_size($fetchrecord['bytestransferred']);
                        $fetch['bytestotal'] = display_size($fetchrecord['bytestotal']);
                        if ($fetchrecord['bytestotal'] > 0) {
                            $fetch['percent'] = (int) floor(($fetchrecord['bytestransferred'] / $fetchrecord['bytestotal']) * 100);
                        }
                    } else if ($fetchrecord['status'] === file_fetch_status::FAILED->value) {
                        // Previous fetch task failed.
                        $fetch['failed'] = true;
                        $fetch['error'] = $fetchrecord['error'];
                    }

                    // A cancel was requested but the task hasn't noticed/cleaned up yet (still
                    // queued, or actively fetching until its next ~1s cancel check). Suppress the
                    // cancel button itself during this window so it can't be clicked again.
                    $cancelling = ($fetch['queued'] || $fetch['fetching']) && ($fetchrecord['cancelrequested'] ?? false);
                }

                // Determine buttons to show and propagate the fetch template data.
                $fetch['showcancel'] = ($fetch['queued'] || $fetch['fetching']) && !$cancelling;
                $fetch['cancelling'] = $cancelling;
                $file['fetch'] = $fetch;

                // Mark page as requiring refreshes while we have active fetch tasks.
                $needsrefresh = $needsrefresh || $fetch['queued'] || $fetch['fetching'];
            }

            if (has_capability('local/archiving:delete', $ctx->get_course_context())) {
                $file['deleteurl'] = new \moodle_url('/local/archiving/manage.php', [
                    'action' => 'filedelete',
                    'filehandleid' => $filehandle->id,
                    'contextid' => $ctx->id,
                    'wantsurl' => $PAGE->url->out(false),
                ]);
            }
        }

        // Get TSP data if available.
        $tspmanager = new tsp_manager($filehandle);
        $tspdata = $tspmanager->get_tsp_data();
        if ($tspdata) {
            $file['tsp'] = [
                'timecreated' => $tspdata->timecreated,
                'server' => $tspdata->server,
                'querydownloadurl' => $tspmanager->get_query_download_url(),
                'replydownloadurl' => $tspmanager->get_reply_download_url(),
            ];
        }

        // Prepare retention time data if available.
        if ($filehandle->retentiontime) {
            $file['retentiontime'] = (object) [
                'absolute' => $filehandle->retentiontime,
                'relative' => time_util::duration_to_human_readable(max(0, $filehandle->retentiontime - time())),
                'elapsed' => time() > $filehandle->retentiontime,
            ];
        }

        $tplctx['files'][] = $file;
    }

    // Auto-refresh the page if any logic above requested it.
    if ($needsrefresh) {
        $PAGE->set_periodic_refresh_delay(5);
    }

    $html .= $renderer->render_from_template('local_archiving/download_job_artifacts', $tplctx);
}

// If not redirected, render output.
echo $OUTPUT->header();
echo $html;
echo $OUTPUT->footer();
