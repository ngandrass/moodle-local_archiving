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
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\util;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use local_archiving\type\archive_job_status;
use local_archiving\type\db_table;

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
        global $DB;

        // Get cms and supported activities.
        $modinfo = get_fast_modinfo($courseid);
        $supported = plugin_util::get_supported_activities();

        if (empty($modinfo->cms)) {
            return [];
        }

        // Get latest successfull archiving job for each cm.
        $cmcontextids = array_map(fn ($cm) => $cm->context->id, $modinfo->cms);
        $cmcontextidssql = implode(',', array_map('intval', $cmcontextids));
        $lastarchivedcms = $DB->get_records_sql("
                SELECT contextid, MAX(timecreated) AS lastarchived
                FROM {".db_table::JOB->value."}
                WHERE
                    status = :status AND
                    contextid IN ({$cmcontextidssql})
                GROUP BY contextid
            ",
            ['status' => archive_job_status::COMPLETED->value]
        );

        // Build response.
        $res = [];
        foreach ($modinfo->cms as $cm) {
            $res[$cm->id] = (object) [
                'cm' => $cm,
                'supported' => array_key_exists($cm->modname, $supported),
                'lastarchived' => ($lastarchivedcms[$cm->context->id] ?? null)?->lastarchived,
            ];
        }

        return $res;
    }

    /**
     * Retrieves the cm_info object for the given module context object
     *
     * @param \context_module $ctx The module context to get the cm_info for
     * @return \cm_info The cm_info object for the given module context
     * @throws \moodle_exception
     */
    public static function get_cm_info(\context_module $ctx): \cm_info {
        $cinfo = get_fast_modinfo($ctx->get_course_context()->instanceid);
        return $cinfo->get_cm($ctx->instanceid);
    }

}
