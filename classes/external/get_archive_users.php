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
 * This file defines the get_archive_users webservice function
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\external;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use core\exception\coding_exception;
use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\restricted_context_exception;
use local_archiving\local\type\db_table;

/**
 * API endpoint to search for users that can be used to filter archive jobs
 *
 * Candidates are all users enrolled in the course of the given context as well
 * as all users that have archived data in jobs within the given context. This
 * allows to find users that were unenrolled after their data has been archived.
 *
 * The result structure is compatible with core_user\external\search_identity to
 * allow reuse of the core_user/form_user_selector_suggestion template.
 */
class get_archive_users extends external_api {
    /**
     * Returns the description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(
                PARAM_INT,
                'ID of the course or module context to search users in',
                VALUE_REQUIRED
            ),
            'query' => new external_value(
                PARAM_TEXT,
                'The search query',
                VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Returns the description of return parameters
     *
     * @return external_description
     * @throws coding_exception
     */
    public static function execute_returns(): external_description {
        return new external_single_structure([
            'list' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(\core_user::get_property_type('id'), 'ID of the user'),
                    'fullname' => new external_value(PARAM_RAW, 'The fullname of the user'),
                    'extrafields' => new external_multiple_structure(
                        new external_single_structure([
                            'name' => new external_value(PARAM_TEXT, 'Name of the extrafield'),
                            'value' => new external_value(PARAM_TEXT, 'Value of the extrafield'),
                        ]),
                        'List of extra fields',
                        VALUE_OPTIONAL
                    ),
                ])
            ),
            'maxusersperpage' => new external_value(PARAM_INT, 'Configured maximum users per page'),
            'overflow' => new external_value(PARAM_BOOL, 'Whether more users than maxusersperpage were found'),
        ]);
    }

    /**
     * Searches for users that are either enrolled in the course of the given
     * context or have archived data in jobs within the given context
     *
     * @param int $contextidraw ID of the course or module context to search users in
     * @param string $queryraw The search query
     * @return array Search result, see execute_returns()
     * @throws \coding_exception
     * @throws restricted_context_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \required_capability_exception
     */
    public static function execute(int $contextidraw, string $queryraw): array {
        global $CFG, $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextidraw,
            'query' => $queryraw,
        ]);

        // Validate context and capabilities.
        $ctx = \context::instance_by_id($params['contextid']);
        if (!($ctx instanceof \context_course || $ctx instanceof \context_module)) {
            throw new \invalid_parameter_exception(get_string('invalidcontext', 'error'));
        }
        self::validate_context($ctx);
        require_capability('local/archiving:view', $ctx);

        // Prepare user fields.
        [$extrafields, $userfieldsql] = self::get_user_fields_sql($ctx);
        $extrafieldmappings = array_map(fn ($field) => $userfieldsql->mappings[$field], $extrafields);

        // Build search query.
        [$candidatesql, $candidateparams] = self::get_candidates_sql($ctx);
        [$searchsql, $searchparams] = users_search_sql($params['query'], 'u', USER_SEARCH_CONTAINS, $extrafieldmappings);
        [$sortsql, $sortparams] = users_order_by_sql('u', $params['query'], $ctx, $userfieldsql->mappings);

        $sql = "SELECT u.id {$userfieldsql->selects}
                  FROM {user} u
                       {$userfieldsql->joins}
                 WHERE {$candidatesql}
                   AND {$searchsql}
              ORDER BY {$sortsql}";
        $sqlparams = array_merge($userfieldsql->params, $candidateparams, $searchparams, $sortparams);

        // Fetch one more record than allowed to detect overflows.
        $rs = $DB->get_recordset_sql($sql, $sqlparams, 0, $CFG->maxusersperpage + 1);
        $list = [];
        $count = 0;
        $viewfullnames = has_capability('moodle/site:viewfullnames', $ctx);
        foreach ($rs as $record) {
            $count++;
            if ($count <= $CFG->maxusersperpage) {
                $list[] = self::export_user($record, $extrafields, $viewfullnames);
            }
        }
        $rs->close();

        return [
            'list' => $list,
            'maxusersperpage' => $CFG->maxusersperpage,
            'overflow' => $count > $CFG->maxusersperpage,
        ];
    }

    /**
     * Retrieves the given user in the format expected by the
     * core_user/form_user_selector_suggestion template
     *
     * This is used to render the currently selected user identically to the
     * search suggestions. Only users that would also be returned by execute()
     * are considered.
     *
     * @param int $userid ID of the user to retrieve
     * @param \context $ctx Context the user is displayed in
     * @return \stdClass|null Object with properties id, fullname, and extrafields
     *      or null if the user is not a valid candidate within the given context
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_exported_user(int $userid, \context $ctx): ?\stdClass {
        global $DB;

        [$extrafields, $userfieldsql] = self::get_user_fields_sql($ctx);
        [$candidatesql, $candidateparams] = self::get_candidates_sql($ctx);
        $user = $DB->get_record_sql(
            "SELECT u.id {$userfieldsql->selects}
               FROM {user} u
                    {$userfieldsql->joins}
              WHERE u.id = :userid AND u.deleted = 0 AND {$candidatesql}",
            array_merge($userfieldsql->params, $candidateparams, ['userid' => $userid])
        );

        if (!$user) {
            return null;
        }

        return self::export_user($user, $extrafields, has_capability('moodle/site:viewfullnames', $ctx));
    }

    /**
     * Builds the SQL condition that restricts the user table alias 'u' to all
     * users that are either enrolled in the course of the given context or have
     * archived data in jobs within the given context or any of its children
     *
     * @param \context $ctx Context to determine candidates for
     * @return array Tuple of SQL condition and named parameters
     * @throws \coding_exception
     */
    protected static function get_candidates_sql(\context $ctx): array {
        global $DB;

        // All course participants, including suspended enrolments.
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($ctx->get_course_context(), '', 0, false);

        // All users with archived data inside the given context or its children. The path must have a
        // trailing slash to avoid matching contexts with a similar path (e.g., /1/3/25 vs. /1/3/250).
        $archivedsql = 'SELECT c.userid FROM {' . db_table::CONTENT->value . '} c ' .
            'JOIN {' . db_table::ACTIVITY_TASK->value . '} t ON t.id = c.taskid ' .
            'JOIN {' . db_table::JOB->value . '} j ON j.id = t.jobid ' .
            'JOIN {context} ctx ON ctx.id = j.contextid ' .
            'WHERE (ctx.id = :archivectxid OR ' . $DB->sql_like('ctx.path', ':archivectxpath') . ')';
        $archivedparams = [
            'archivectxid' => $ctx->id,
            'archivectxpath' => $ctx->path . '/%',
        ];

        return [
            "(u.id IN ({$enrolledsql}) OR u.id IN ({$archivedsql}))",
            array_merge($enrolledparams, $archivedparams),
        ];
    }

    /**
     * Determines the identity fields to display and builds the SQL to select
     * them alongside all name fields
     *
     * @param \context $ctx Context the users are displayed in
     * @return array Tuple of identity field names and the SQL object returned by
     *      \core_user\fields::get_sql() for the user table alias 'u'
     * @throws \coding_exception
     */
    protected static function get_user_fields_sql(\context $ctx): array {
        $fields = \core_user\fields::for_name()->with_identity($ctx, false);

        return [
            $fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]),
            $fields->get_sql('u', true),
        ];
    }

    /**
     * Exports the given user record in the format expected by the
     * core_user/form_user_selector_suggestion template
     *
     * @param \stdClass $user User record, containing all name and identity fields
     * @param string[] $extrafields Identity fields to export
     * @param bool $viewfullnames Whether the viewing user may see full names
     * @return \stdClass Object with properties id, fullname, and extrafields
     */
    protected static function export_user(\stdClass $user, array $extrafields, bool $viewfullnames): \stdClass {
        $exported = (object) [
            'id' => $user->id,
            'fullname' => fullname($user, $viewfullnames),
            'extrafields' => [],
        ];

        foreach ($extrafields as $extrafield) {
            if (!empty($user->$extrafield)) {
                // Sanitize the extra fields to prevent potential XSS exploit.
                $exported->extrafields[] = (object) [
                    'name' => $extrafield,
                    'value' => s($user->$extrafield),
                ];
            }
        }

        return $exported;
    }
}
