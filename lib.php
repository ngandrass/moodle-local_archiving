<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Legacy lib definitions
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Inject navigation nodes
 *
 * @param settings_navigation $settingsnav The root node of the settings navigation
 * @param context $ctx Current context
 * @return void
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 */
function local_archiving_extend_settings_navigation(settings_navigation $settingsnav, context $ctx) {
    // Inject archiving overview node into course navigation.
    if ($ctx && ($ctx instanceof context_course || $ctx instanceof context_module)) {
        if (has_capability('local/archiving:view', $ctx)) {
            // Construct new navigation node.
            $url = new moodle_url(
                '/local/archiving/index.php',
                ['courseid' => $ctx->get_course_context()->instanceid]
            );
            $node = navigation_node::create(
                get_string('pluginname', 'local_archiving'),
                $url,
                navigation_node::TYPE_SETTING,
                'local_archiving',
                'local_archiving',
                new pix_icon('i/settings', '')
            );

            // Append to course administration node.
            $parentnode = $settingsnav->find('courseadmin', null);
            if ($parentnode) {
                $parentnode->add_node($node);
            }
        }
    }
}
