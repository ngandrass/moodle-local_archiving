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

// Get course and course module.
$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

$coursectx = context_course::instance($courseid);
$ctx = context_module::instance($cmid);
list($course, $cm) = get_course_and_cm_from_cmid($cmid);

// Check login and capabilities.
require_login($courseid);
// TODO: Check capability

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
//$PAGE->set_pagelayout('incourse');

// Render output.
$renderer = $PAGE->get_renderer('local_archiving');
echo $OUTPUT->header();

// vvvvv DEBUG start vvvvv

$driverclass = plugin_util::get_archiving_driver_for_cm($cm->modname);
if (!$driverclass) {
    throw new \moodle_exception('supported_archive_driver_not_found', 'local_archiving');
}

/** @var \local_archiving\driver\archivingmod_base $driver */
$driver = new $driverclass();


$form = $driver->get_job_create_form($cm->modname, $cm);
$form->display();

$backurl = new moodle_url('/local/archiving/index.php', array('courseid' => $courseid));
echo "<a href=\"{$backurl}\">Go back to overview</a>";

// ^^^^^ DEBUG end ^^^^^

echo $OUTPUT->footer();
