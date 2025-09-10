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

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use local_archiving\activity_archiving_task;
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
     * @param bool $excludedisabled Whether to exclude unsupported and disabled activities
     * @return array An array of course modules with metadata
     * @throws \moodle_exception If the course does not exist
     */
    public static function get_cms_with_metadata(int $courseid, bool $excludedisabled = false): array {
        global $DB;

        // Get cms and supported activities.
        $cms = get_fast_modinfo($courseid)->cms;
        $drivers = plugin_util::get_activity_archiving_drivers();
        $supported = array_reduce($drivers, fn ($res, $driver) => array_merge($res, $driver['activities']), []);

        if (empty($cms)) {
            return [];
        }

        // Exclude unsupported and disabled activities if requested before we query the database.
        if ($excludedisabled) {
            $cms = array_filter($cms, function ($cm) use ($supported, $drivers) {
                return in_array($cm->modname, $supported) && ($drivers[$cm->modname]['enabled'] ?? false);
            });
        }

        // Get latest successfull archiving job for each cm.
        $cmcontextids = array_map(fn ($cm) => $cm->context->id, $cms);
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
        foreach ($cms as $cm) {
            $data = [
                'cm' => $cm,
                'supported' => in_array($cm->modname, $supported),
                'enabled' => $drivers[$cm->modname]['enabled'] ?? false,
                'ready' => $drivers[$cm->modname]['ready'] ?? false,
                'lastarchived' => ($lastarchivedcms[$cm->context->id] ?? null)?->lastarchived,
                'dirty' => true,  // By default we always assume new changes unless we know better as determined below.
            ];

            // Determine if cm is dirty (new changes since last archiving).
            if ($data['lastarchived']) {
                // Only calculate fingerprint if the activity is supported, enabled, and ready.
                if ($data['supported'] && $data['enabled'] && $data['ready']) {
                    // Check if this fingerprint was already seen.
                    $driver = \local_archiving\driver\factory::activity_archiving_driver(
                        plugin_util::get_archiving_driver_for_cm($cm->modname),
                        $cm->context
                    );

                    $data['dirty'] = activity_archiving_task::fingerprint_exists($cm->context, $driver->fingerprint()) === false;
                }
            }

            $res[$cm->id] = (object) $data;
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
