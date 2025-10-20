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
 * Privacy provider class for the local_archiving plugin.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_archiving\type\db_table;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Privacy provider for local_archiving
 *
 * @codeCoverageIgnore This is handled by Moodle core tests
 */
class provider implements // phpcs:ignore
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // Subsystem links.
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        // Database tables.
        $collection->add_database_table(db_table::JOB->value, [
            'contextid' => 'privacy:metadata:' . db_table::JOB->value . ':contextid',
            'userid' => 'privacy:metadata:' . db_table::JOB->value . ':userid',
            'origin' => 'privacy:metadata:' . db_table::JOB->value . ':origin',
            'settings' => 'privacy:metadata:' . db_table::JOB->value . ':settings',
            'timecreated' => 'privacy:metadata:' . db_table::JOB->value . ':timecreated',
            'timemodified' => 'privacy:metadata:' . db_table::JOB->value . ':timemodified',
        ], 'privacy:metadata:' . db_table::JOB->value);

        $collection->add_database_table(db_table::ACTIVITY_TASK->value, [
            'archivingmod' => 'privacy:metadata:' . db_table::ACTIVITY_TASK->value . ':archivingmod',
            'contextid' => 'privacy:metadata:' . db_table::ACTIVITY_TASK->value . ':contextid',
            'userid' => 'privacy:metadata:' . db_table::ACTIVITY_TASK->value . ':userid',
            'settings' => 'privacy:metadata:' . db_table::ACTIVITY_TASK->value . ':settings',
            'timecreated' => 'privacy:metadata:' . db_table::ACTIVITY_TASK->value . ':timecreated',
            'timemodified' => 'privacy:metadata:' . db_table::ACTIVITY_TASK->value . ':timemodified',
        ], 'privacy:metadata:' . db_table::ACTIVITY_TASK->value);

        $collection->add_database_table(db_table::METADATA->value, [
            'datakey' => 'privacy:metadata:' . db_table::METADATA->value . ':datakey',
            'datavalue' => 'privacy:metadata:' . db_table::METADATA->value . ':datavalue',
        ], 'privacy:metadata:' . db_table::METADATA->value);

        $collection->add_database_table(db_table::CONTENT->value, [
            'taskid' => 'privacy:metadata:' . db_table::CONTENT->value . ':taskid',
            'userid' => 'privacy:metadata:' . db_table::CONTENT->value . ':userid',
            'summary' => 'privacy:metadata:' . db_table::CONTENT->value . ':summary',
            'reftable' => 'privacy:metadata:' . db_table::CONTENT->value . ':reftable',
            'refid' => 'privacy:metadata:' . db_table::CONTENT->value . ':refid',
        ], 'privacy:metadata:' . db_table::CONTENT->value);

        $collection->add_database_table(db_table::LOG->value, [
            'level' => 'privacy:metadata:' . db_table::LOG->value . ':level',
            'message' => 'privacy:metadata:' . db_table::LOG->value . ':message',
            'jobid' => 'privacy:metadata:' . db_table::LOG->value . ':jobid',
            'taskid' => 'privacy:metadata:' . db_table::LOG->value . ':taskid',
            'timecreated' => 'privacy:metadata:' . db_table::LOG->value . ':timecreated',
        ], 'privacy:metadata:' . db_table::LOG->value);

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Add archive jobs owned by the user.
        $contextlist->add_from_sql(
            '
                SELECT DISTINCT contextid
                FROM {' . db_table::JOB->value . '}
                WHERE userid = ?
            ',
            [$userid]
        );

        // Add activity archiving tasks owned by the user.
        $contextlist->add_from_sql(
            '
                SELECT DISTINCT contextid
                FROM {' . db_table::ACTIVITY_TASK->value . '}
                WHERE userid = ?
            ',
            [$userid]
        );

        // Add contexts from activity archiving task contents the user is associated with.
        $contextlist->add_from_sql(
            '
                SELECT DISTINCT contextid
                FROM {' . db_table::ACTIVITY_TASK->value . '} t
                JOIN {' . db_table::CONTENT->value . '} c ON c.taskid = t.id
                WHERE c.userid = ?
            ',
            [$userid]
        );

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        // Process all contexts.
        $subctxbase = get_string('pluginname', 'local_archiving');
        foreach ($contextlist->get_contexts() as $context) {
            // Export archive jobs owned by the user.
            $jobs = $DB->get_records(db_table::JOB->value, ['contextid' => $context->id, 'userid' => $userid]);
            $jobctxsuffix = get_string('archive_job', 'local_archiving');
            foreach ($jobs as $job) {
                // Fetch metadata associated with job.
                $jobmetadatarows = $DB->get_records(db_table::METADATA->value, ['jobid' => $job->id]);
                $metadata = array_map(fn ($row) => (object) [
                    'datakey' => $row->datakey,
                    'datavalue' => $row->datavalue,
                ], $jobmetadatarows);

                // Fetch logs associated with job.
                $joblogrows = $DB->get_records(db_table::LOG->value, ['jobid' => $job->id, 'taskid' => null]);
                $logs = array_map(fn ($row) => (object) [
                    'jobid' => $row->jobid,
                    'taskid' => $row->taskid,
                    'level' => $row->level,
                    'message' => $row->message,
                    'timecreated' => $row->timecreated,
                ], $joblogrows);

                // Export everything related to this job.
                writer::with_context($context)->export_data(
                    [$subctxbase, "{$jobctxsuffix}: {$job->id}"],
                    (object) [
                        'contextid' => $job->contextid,
                        'userid' => $job->userid,
                        'origin' => $job->origin,
                        'settings' => $job->settings,
                        'timecreated' => $job->timecreated,
                        'timemodified' => $job->timemodified,
                        'metadata' => $metadata,
                        'logs' => $logs,
                    ]
                );
            }

            // Export activity archiving tasks owned by the user.
            $tasks = $DB->get_records(db_table::ACTIVITY_TASK->value, ['contextid' => $context->id, 'userid' => $userid]);
            $taskctxsuffix = get_string('activity_archiving_task', 'local_archiving');
            foreach ($tasks as $task) {
                // Fetch logs associated with this task.
                $tasklogrows = $DB->get_records(db_table::LOG->value, ['taskid' => $task->id]);
                $logs = array_map(fn ($row) => (object) [
                    'jobid' => $row->jobid,
                    'taskid' => $row->taskid,
                    'level' => $row->level,
                    'message' => $row->message,
                    'timecreated' => $row->timecreated,
                ], $tasklogrows);

                // Export everything related to this task.
                writer::with_context($context)->export_data(
                    [$subctxbase, "{$taskctxsuffix}: {$task->id}"],
                    (object) [
                        'archivingmod' => $task->archivingmod,
                        'contextid' => $task->contextid,
                        'userid' => $task->userid,
                        'settings' => $task->settings,
                        'timecreated' => $task->timecreated,
                        'timemodified' => $task->timemodified,
                        'logs' => $logs,
                    ]
                );
            }

            // Export archive content links associated with this context and user.
            $contents = $DB->get_records_sql(
                '
                    SELECT c.*
                    FROM {' . db_table::CONTENT->value . '} c
                    JOIN {' . db_table::ACTIVITY_TASK->value . '} t ON c.taskid = t.id
                    WHERE t.contextid = ? AND c.userid = ?
                ',
                [$context->id, $userid]
            );
            $contentctxsuffix = get_string('archived_data', 'local_archiving');
            foreach ($contents as $content) {
                // Export everything related to this content link.
                writer::with_context($context)->export_data(
                    [$subctxbase, "{$contentctxsuffix}: {$content->id}"],
                    (object) [
                        'taskid' => $content->taskid,
                        'userid' => $content->userid,
                        'summary' => $content->summary,
                        'reftable' => $content->reftable,
                        'refid' => $content->refid,
                    ]
                );
            }
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        // Add archive job initiators.
        $userlist->add_from_sql(
            'userid',
            '
                SELECT DISTINCT userid
                FROM {' . db_table::JOB->value . '}
                WHERE contextid = ?
            ',
            [$context->id]
        );

        // Add activity archiving task initiators.
        $userlist->add_from_sql(
            'userid',
            '
                SELECT DISTINCT userid
                FROM {' . db_table::ACTIVITY_TASK->value . '}
                WHERE contextid = ?
            ',
            [$context->id]
        );

        // Add users associated with activity archiving task contents.
        $userlist->add_from_sql(
            'userid',
            '
                SELECT DISTINCT c.userid
                FROM {' . db_table::ACTIVITY_TASK->value . '} t
                JOIN {' . db_table::CONTENT->value . '} c ON c.taskid = t.id
                WHERE t.contextid = ?
            ',
            [$context->id]
        );
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // We cannot simply delete data that needs to be archived for a specified amount of time.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // We cannot simply delete data that needs to be archived for a specified amount of time.
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // We cannot simply delete data that needs to be archived for a specified amount of time.
    }
}
