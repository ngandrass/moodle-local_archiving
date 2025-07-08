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
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\archive_job;
use local_archiving\file_handle;
use local_archiving\form\file_delete_form;
use local_archiving\form\job_delete_form;
use local_archiving\util\plugin_util;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE;

// Parse expected params.
$contextid = required_param('contextid', PARAM_INT);
$action = required_param('action', PARAM_TEXT);
$wantsurl = optional_param('wantsurl', '', PARAM_URL);

// Validate context and check capabilities.
$ctx = context::instance_by_id($contextid);
if (!($ctx instanceof \context_course || $ctx instanceof \context_module)) {
    throw new \moodle_exception(get_string('invalidcontext', 'local_archiving'));
}

// Check login and capabilities.
$courseid = $ctx->get_course_context()->instanceid;
$course = get_course($courseid);
require_login($courseid);
require_capability('local/archiving:view', $ctx);

// Setup page.
$PAGE->set_context($ctx->get_course_context());
$PAGE->set_title(get_string('pluginname', 'local_archiving'));
$PAGE->set_heading($course->fullname);

// Handle POSTed data.
$outhtml = '';
if ($action === 'jobdelete') {
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

    $form = new job_delete_form($contextid, $jobid, $wantsurl);

    if ($form->is_cancelled()) {
        redirect($wantsurl);
    } else if ($form->is_submitted() && $form->is_validated()) {
        require_capability('local/archiving:delete', $ctx);

        // Perform deletion.
        $job = archive_job::get_by_id($jobid);
        $job->delete();

        redirect($wantsurl);
    } else {
        $outhtml .= $form->render();
    }
} else if ($action === 'filedelete') {
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

    $form = new file_delete_form($contextid, $filehandleid, $wantsurl);

    if ($form->is_cancelled()) {
        redirect($wantsurl);
    } else if ($form->is_submitted() && $form->is_validated()) {
        require_capability('local/archiving:delete', $ctx);

        // Perform deletion.
        $filehandle = file_handle::get_by_id($filehandleid);
        $filehandle->archivingstore()->delete($filehandle);
        $filehandle->mark_as_deleted();

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
