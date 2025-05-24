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
 * Main entry point for archiving manager. Course archiving overview page.
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\util\mod_util;
use local_archiving\util\plugin_util;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE;

$courseid = required_param('courseid', PARAM_INT);
$ctx = context_course::instance($courseid);
$course = get_course($courseid);

// Check login and capabilities.
require_login($courseid);
require_capability('local/archiving:view', $ctx);

// Setup page.
$PAGE->set_context($ctx);
$PAGE->set_title(get_string('pluginname', 'local_archiving'));
$PAGE->set_heading($course->fullname);
$PAGE->set_url(new moodle_url(
    '/local/archiving/index.php',
    ['courseid' => $courseid]
));
//$PAGE->set_pagelayout('incourse');


// Render output.
$renderer = $PAGE->get_renderer('local_archiving');
echo $OUTPUT->header();
echo $renderer->index();

// DEBUG start
echo "<h1>Activities</h1>";
echo "<pre>";
foreach (mod_util::get_cms_with_metadata($courseid) as $obj) {
    if ($obj->supported) {
        if ($obj->enabled) {
            $enablehtml = '<span class="badge badge-success px-2">Enabled</span>';
        } else {
            $enablehtml = '<span class="badge badge-warning px-2">Disabled</span>';
        }
    } else {
        $enablehtml = '<span class="badge badge-danger px-2">Not supported</span>';
    }

    if ($obj->lastarchived) {
        $lastarchivedhtml = '<span class="badge badge-success px-2">Archived on '.date('Y-m-d H:i:s', $obj->lastarchived).'</span>';
    } else {
        $lastarchivedhtml = '<span class="badge badge-warning px-2">Never archived</span>';
    }

    if ($obj->supported && $obj->enabled) {
        $url = new moodle_url('/local/archiving/archive.php', [
            'courseid' => $courseid,
            'cmid' => $obj->cm->id,
        ]);
        echo "<a href=\"{$url}\">[{$obj->cm->modname}]: {$obj->cm->name} {$enablehtml} {$lastarchivedhtml}</a><br>";
    } else {
        echo "[{$obj->cm->modname}]: {$obj->cm->name} {$enablehtml} {$lastarchivedhtml}<br>";
    }
}
echo "</pre>";

$jobtbl = new \local_archiving\output\job_overview_table('job_overview_table_'.$ctx->id, $ctx);
$jobtbl->define_baseurl($PAGE->url);
$jobtbl->out(20, true);

// DEBUG end

echo $OUTPUT->footer();
