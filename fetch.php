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
 * On-demand artifact retrieval handler for remote storages
 *
 * @package     local_archiving
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\archive_job;
use local_archiving\file_handle;
use local_archiving\local\type\file_fetch_status;
use local_archiving\local\type\storage_tier;
use local_archiving\remote_file_fetcher;
use local_archiving\task\retrieve_remote_file;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE, $USER;

// Parse expected params.
$filehandleid = required_param('filehandleid', PARAM_INT);
$contextid = required_param('contextid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Try to find file handle, job and context.
$filehandle = file_handle::get_by_id($filehandleid);
$job = archive_job::get_by_id($filehandle->jobid);
$ctx = $job->get_context();
if ($ctx->id != $contextid) {
    throw new \moodle_exception('invalidcontext', 'local_archiving');
}
[$course, $cm] = get_course_and_cm_from_cmid($ctx->instanceid);

// Check login and capabilities.
require_login($course);
require_capability('local/archiving:view', $ctx->get_course_context());

// Setup page.
$PAGE->set_context($ctx->get_course_context());
$PAGE->set_title(get_string('pluginname', 'local_archiving'));
$PAGE->set_heading($cm->name);
$PAGE->set_url(new moodle_url(
    '/local/archiving/fetch.php',
    ['filehandleid' => $filehandleid, 'contextid' => $contextid]
));

$overviewurl = new moodle_url('/local/archiving/download.php', ['jobid' => $job->get_id()]);

// Guard rails.
if ($filehandle->deleted) {
    throw new \moodle_exception('deleted_file_can_not_be_retrieved', 'local_archiving');
}
$store = $filehandle->archivingstore();

// Local files are retrieved synchronously instead. Send user back to overview.
if ($store::get_storage_tier() === storage_tier::LOCAL) {
    redirect($overviewurl);
}

// Redirect back to overview if file is already cached.
if ($filehandle->get_local_file()) {
    redirect($filehandle->get_local_download_url());
}

if (!$store::supports_retrieve()) {
    throw new \moodle_exception('retrieve_not_supported', 'local_archiving');
}

// Spawn a new file retrieval task.
if ($action === 'start') {
    require_sesskey();

    // Prevent spawning multiple tasks for the same file.
    if (!remote_file_fetcher::lock($filehandleid)) {
        redirect($overviewurl);
    }

    try {
        $record = remote_file_fetcher::get($filehandleid);

        if (!$record || !in_array($record['status'], [file_fetch_status::QUEUED->value, file_fetch_status::FETCHING->value])) {
            remote_file_fetcher::mark_queued($filehandleid);
            $task = retrieve_remote_file::create($filehandle, $USER->id);
            \core\task\manager::queue_adhoc_task($task, checkforexisting: true);
        }
    } finally {
        remote_file_fetcher::unlock($filehandleid);
    }

    redirect($overviewurl);
}

// Cancel a running retrieval task.
if ($action === 'cancel') {
    require_sesskey();
    retrieve_remote_file::cancel_and_purge($filehandleid);
    redirect($overviewurl);
}

// Redirect back to overview for all running tasks.
$record = remote_file_fetcher::get($filehandleid);
if (
    $record !== null
    && in_array($record['status'], [file_fetch_status::QUEUED->value, file_fetch_status::FETCHING->value], true)
) {
    redirect($overviewurl);
}

// Render confirmation dialog for starting a new retrieval task.
$continueurl = new moodle_url('/local/archiving/fetch.php', [
    'filehandleid' => $filehandleid,
    'contextid' => $contextid,
    'action' => 'start',
]);
$message = get_string('fetch_remote_file_prompt', 'local_archiving');

echo $OUTPUT->header();
echo $OUTPUT->confirm($message, $continueurl, $overviewurl, [
    'continuestr' => get_string('yes'),
    'cancelstr' => get_string('no'),
]);
echo $OUTPUT->footer();
