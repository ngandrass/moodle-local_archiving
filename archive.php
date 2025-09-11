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
 * Activity archiving overview
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\util\plugin_util;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE, $USER;

// Get course and course module.
$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

$coursectx = context_course::instance($courseid);
$ctx = context_module::instance($cmid);
list($course, $cm) = get_course_and_cm_from_cmid($cmid);

// Check login and capabilities.
require_login($courseid);
require_capability('local/archiving:view', $ctx);

// Setup page.
$PAGE->set_context($coursectx);
$PAGE->set_title(get_string('pluginname', 'local_archiving'));
$PAGE->set_heading($cm->name);
$PAGE->set_url(new moodle_url(
    '/local/archiving/archive.php',
    [
        'courseid' => $courseid,
        'cmid' => $cmid,
    ]
));
$PAGE->set_pagelayout('incourse');

$html = '';

// Get job create form for this activity.
$driver = \local_archiving\driver\factory::activity_archiving_driver($cm->modname, $ctx);
$form = $driver->get_job_create_form($cm->modname, $cm);

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new \moodle_url('/local/archiving/index.php', ['courseid' => $courseid]));
}

if ($form->is_submitted() && $form->is_validated()) {
    require_capability('local/archiving:create', $ctx);

    // Ensure that manual archive job creation is enabled.
    if (!\local_archiving\driver\factory::archiving_trigger('manual')->is_enabled()) {
        // We should never get here if nobody messes with the form. But who knows how creative people might get ;) ...
        throw new \moodle_exception('manual_job_creation_disabled', 'local_archiving');
    }

    $jobsettings = $form->get_data();
    if (!$jobsettings) {
        throw new \moodle_exception('job_create_form_data_empty', 'local_archiving');
    }
    $job = \local_archiving\archive_job::create($ctx, $USER->id, $jobsettings);
    $job->enqueue();

    $html .= $OUTPUT->notification(
        get_string('archive_job_created_details', 'local_archiving', [
            'jobid' => $job->get_id(),
            'cmname' => $cm->name,
        ]),
        'success'
    );
}

// Prepare template context for page.
$jobtbl = new \local_archiving\output\job_overview_table('job_overview_table_'.$ctx->id, $ctx);
$jobtbl->define_baseurl($PAGE->url);
ob_start();
$jobtbl->out(20, true);
$jobtablehtml = ob_get_contents();
ob_end_clean();

$tplctx = [
    'jobcreateformhtml' => $form->render(),
    'jobtablehtml' => $jobtablehtml,
    'modfullname' => $cm->modfullname,
    'urls' => [
        'back' => new \moodle_url('/local/archiving/index.php', ['courseid' => $courseid]),
    ],
];
$html .= $OUTPUT->render_from_template('local_archiving/archive', $tplctx);

// Render output.
$renderer = $PAGE->get_renderer('local_archiving');
echo $OUTPUT->header();
echo $html;
echo $OUTPUT->footer();
