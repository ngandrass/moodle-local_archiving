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
 * Main entry point for archiving manager
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\util\mod_util;
use local_archiving\util\plugin_util;

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$ctx = context_course::instance($courseid);
$course = get_course($courseid);

// Setup page.
$PAGE->set_context($ctx);
$PAGE->set_title(get_string('pluginname', 'local_archiving'));
$PAGE->set_heading($course->fullname);
$PAGE->set_url(new moodle_url(
    '/local/archiving/index.php',
    ['courseid' => $courseid]
));
//$PAGE->set_pagelayout('incourse');

// Check login and capabilities.
require_login($courseid);
// TODO: Check capability

// Render output.
$renderer = $PAGE->get_renderer('local_archiving');
echo $OUTPUT->header();
echo $renderer->index();

// DEBUG start
echo "<h3>Activity Archiving Drivers</h3>";
echo "<pre>";
print_r(plugin_util::get_activity_archiving_drivers());
echo "</pre>";

echo "<h5>Supported Activities</h5>";
echo "<pre>";
print_r(plugin_util::get_supported_activities());
echo "</pre>";

echo "<h3>Storage Drivers</h3>";
echo "<pre>";
print_r(plugin_util::get_storage_drivers());
echo "</pre>";

echo "<h3>Event Connectors</h3>";
echo "<pre>";
print_r(plugin_util::get_event_connectors());
echo "</pre>";

echo "<h1>Activities</h1>";
echo "<pre>";
foreach (mod_util::get_cms_with_metadata($courseid) as $obj) {
    $supstr = $obj->supported ? 'Yes' : 'No';
    echo "[{$obj->cm->modname}]: {$obj->cm->name} (Supported: {$supstr})<br>";
}
echo "</pre>";

/** @var \local_archiving\driver\archivingmod_base $driver */
$driver = new (plugin_util::get_activity_archiving_drivers()['quiz']['class'])();
$cm = mod_util::get_cms_with_metadata($courseid)[86]->cm;
$form = $driver->get_task_settings_form('quiz', $cm);
$form->display();

// DEBUG end

echo $OUTPUT->footer();
