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
 * Job management endpoint. Primarily for handling POSTed data.
 *
 * @package     local_archiving
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\archive_job;
use local_archiving\file_handle;
use local_archiving\form\file_delete_form;
use local_archiving\form\job_delete_form;
use local_archiving\remote_file_fetcher;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE;

// Parse expected params.
$contextid = required_param('contextid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$wantsurl = optional_param('wantsurl', '', PARAM_LOCALURL);

// Validate context and check capabilities.
$ctx = context::instance_by_id($contextid);
if (!($ctx instanceof \context_course || $ctx instanceof \context_module)) {
    throw new \moodle_exception('invalidcontext', 'error');
}

// Check login and capabilities.
$courseid = $ctx->get_course_context()->instanceid;
$cm = null;
if ($ctx instanceof \context_module) {
    [$course, $cm] = get_course_and_cm_from_cmid($ctx->instanceid);
} else {
    $course = get_course($courseid);
}
require_login($course, false, $cm);
require_capability('local/archiving:view', $ctx);

// Setup page.
$PAGE->set_title(get_string('pluginname', 'local_archiving'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

// Handle POSTed data.
$outhtml = '';
if ($action === 'jobdelete') {
    // Check capability for file deletion but inject $wantsurl as a "continue" target.
    try {
        require_capability('local/archiving:delete', $ctx);
    } catch (\required_capability_exception $e) {
        if (!empty($wantsurl)) {
            $e->link = $wantsurl;
        }
        throw $e;
    }

    $jobid = required_param('jobid', PARAM_INT);
    $PAGE->set_url(new moodle_url(
        '/local/archiving/manage.php',
        [
            'contextid' => $contextid,
            'jobid' => $jobid,
            'action' => $action,
            'wantsurl' => $wantsurl,
        ]
    ));

    // Render and handle the job delete form.
    $form = new job_delete_form($contextid, $jobid, $wantsurl);

    if ($form->is_cancelled()) {
        redirect($wantsurl);
    } else if ($form->is_submitted() && $form->is_validated()) {
        $job = archive_job::get_by_id($jobid);
        $job->delete();

        redirect($wantsurl);
    } else {
        $outhtml .= $form->render();
    }
} else if ($action === 'filedelete') {
    // Check capability for file deletion but inject $wantsurl as a "continue" target.
    try {
        require_capability('local/archiving:delete', $ctx);
    } catch (\required_capability_exception $e) {
        if (!empty($wantsurl)) {
            $e->link = $wantsurl;
        }
        throw $e;
    }

    $filehandleid = required_param('filehandleid', PARAM_INT);
    $PAGE->set_url(new moodle_url(
        '/local/archiving/manage.php',
        [
            'contextid' => $contextid,
            'filehandleid' => $filehandleid,
            'action' => $action,
            'wantsurl' => $wantsurl,
        ]
    ));

    // Render and handle the file delete form.
    $form = new file_delete_form($contextid, $filehandleid, $wantsurl);

    if ($form->is_cancelled()) {
        redirect($wantsurl);
    } else if ($form->is_submitted() && $form->is_validated()) {
        $filehandle = file_handle::get_by_id($filehandleid);
        $filehandle->archivingstore()->delete($filehandle);
        $filehandle->mark_as_deleted();
        remote_file_fetcher::delete($filehandleid);

        redirect($wantsurl);
    } else {
        $outhtml .= $form->render();
    }
} else {
    throw new \coding_exception('invalidaction', 'local_archiving');
}

// Render output.
$renderer = $PAGE->get_renderer('local_archiving');
echo $OUTPUT->header();
echo $outhtml;
echo $OUTPUT->footer();
