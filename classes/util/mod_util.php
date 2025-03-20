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
 * Utility class for activities
 *
 * @package     local_archiving
 * @category    util
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\util;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Utility functions for working with activities
 */
class mod_util {

    /**
     * Retrieves the course modules inside a given course and enriches them with
     * metadata of this plugin
     *
     * @param int $courseid The course id
     * @return array An array of course modules with metadata
     * @throws \moodle_exception If the course does not exist
     */
    public static function get_cms_with_metadata(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $supported_activities = plugin_util::get_supported_activities();

        $res = [];
        foreach ($modinfo->cms as $cm) {
            $res[$cm->id] = (object) [
                'cm' => $cm,
                'supported' => array_key_exists('mod_'.$cm->modname, $supported_activities),
            ];
        }

        return $res;
    }

}
