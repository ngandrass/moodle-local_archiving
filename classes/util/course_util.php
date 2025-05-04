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
 * Utility class for courses
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\util;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Utility functions for working with courses
 */
class course_util {

    /**
     * Retrieves the groups a user is member of in a given course
     *
     * @param int $courseid ID of the course to check
     * @param int $userid ID of the user to retrieve groups for
     * @return array List of all groups the user is member of in this course
     * @throws \dml_exception
     */
    public static function get_user_groups(int $courseid, int $userid): array {
        global $DB;

        return $DB->get_records_sql("
            SELECT g.id, g.idnumber, g.name
            FROM {groups} g
            JOIN {groups_members} gm ON g.id = gm.groupid
            WHERE
                g.courseid = :courseid AND
                gm.userid = :userid;",
            [
                'courseid' => $courseid,
                'userid' => $userid,
            ]
        );
    }

}
