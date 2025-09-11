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

use local_archiving\util\course_util;
use local_archiving\util\mod_util;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE;

$courseid = required_param('courseid', PARAM_INT);
$excludedisabledcms = optional_param('edc', true, PARAM_BOOL);
$ctx = context_course::instance($courseid);
$course = get_course($courseid);

// Check login and capabilities.
require_login($courseid);
require_capability('local/archiving:view', $ctx);

// Setup page.
$PAGE->set_context($ctx);
$PAGE->set_title(get_string('pluginname', 'local_archiving'));
$PAGE->set_heading($course->fullname);
$PAGE->set_url(new \moodle_url(
    '/local/archiving/index.php',
    ['courseid' => $courseid]
));
$PAGE->set_pagelayout('incourse');

// Build context for page template.
$archivingenabled = course_util::archiving_enabled_for_course($courseid);
$archivingenableforced = false;
if (!$archivingenabled && has_capability('local/archiving:bypasscourserestrictions', $ctx)) {
    // Archiving is disabled for this course, but the user is allowed to bypass this restriction.
    $archivingenabled = true;
    $archivingenableforced = true;
}

$tplctx = [
    'archivingenabled' => $archivingenabled,
    'archivingenableforced' => $archivingenableforced,
    'hidedisabledcms' => $excludedisabledcms,
    'hidedisabledcmsurl' => new \moodle_url('/local/archiving/index.php', [
        'courseid' => $courseid,
        'edc' => (int) !$excludedisabledcms,
    ]),
];

if ($archivingenabled) {
    foreach (mod_util::get_cms_with_metadata($courseid, $excludedisabledcms) as $obj) {
        $tplctx['cms'][] = [
            'id' => $obj->cm->id,
            'modname' => $obj->cm->modname,
            'modpurpose' => plugin_supports('mod', $obj->cm->modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER) ?: '',
            'name' => $obj->cm->name,
            'archiveurl' => new \moodle_url("/local/archiving/archive.php", [
                'courseid' => $courseid,
                'cmid' => $obj->cm->id,
            ]),
            'iconurl' => $obj->cm->get_icon_url(),
            'supported' => $obj->supported,
            'enabled' => $obj->enabled,
            'ready' => $obj->ready,
            'canbearchived' => $obj->supported && $obj->enabled && $obj->ready,
            'lastarchived' => $obj->lastarchived ?: null,
            'dirty' => $obj->dirty,
        ];
    }
}

$jobtbl = new \local_archiving\output\job_overview_table('job_overview_table_'.$ctx->id, $ctx);
$jobtbl->define_baseurl($PAGE->url);
ob_start();
$jobtbl->out(20, true);
$tplctx['jobtablehtml'] = ob_get_contents();
ob_end_clean();

// Render output.
$renderer = $PAGE->get_renderer('local_archiving');
echo $OUTPUT->header();
echo $renderer->render_from_template('local_archiving/overview_course', $tplctx);
echo $OUTPUT->footer();
