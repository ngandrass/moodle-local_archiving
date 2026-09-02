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
 * This file defines the get_submissions_metadata webservice function
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\external;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


use archivingmod_assign\assignment_manager;
use archivingmod_assign\local\type\webservice_status;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_archiving\activity_archiving_task;


/**
 * API endpoint to access assignment submission metadata in bulk
 */
class get_submissions_metadata extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'uuid' => new external_value(
                PARAM_TEXT,
                'UUID assigned to this task by the worker service',
                VALUE_REQUIRED
            ),
            'taskid' => new external_value(
                PARAM_INT,
                'ID of the activity archiving task this request belongs to',
                VALUE_REQUIRED
            ),
            'submissionids' => new external_multiple_structure(
                new external_value(
                    PARAM_INT,
                    'ID of the assignment submission',
                    VALUE_REQUIRED
                ),
                'List of assignment submission IDs to query',
                VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Returns description of return parameters
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(
                PARAM_TEXT,
                'Status of the executed wsfunction',
                VALUE_REQUIRED
            ),
            'courseid' => new external_value(
                PARAM_INT,
                'ID of course',
                VALUE_OPTIONAL
            ),
            'cmid' => new external_value(
                PARAM_INT,
                'ID of the course module',
                VALUE_OPTIONAL
            ),
            'assignmentid' => new external_value(
                PARAM_INT,
                'ID of the assignment',
                VALUE_OPTIONAL
            ),
            'submissions' => new external_multiple_structure(
                new external_single_structure([
                    'submissionid' => new external_value(
                        PARAM_INT,
                        'ID of the assignment submission',
                        VALUE_REQUIRED
                    ),
                    'userid' => new external_value(
                        PARAM_INT,
                        'ID of the user for this submission',
                        VALUE_REQUIRED
                    ),
                    'username' => new external_value(
                        PARAM_TEXT,
                        'Username for this submission',
                        VALUE_REQUIRED
                    ),
                    'firstname' => new external_value(
                        PARAM_TEXT,
                        'First name for this submission',
                        VALUE_REQUIRED
                    ),
                    'lastname' => new external_value(
                        PARAM_TEXT,
                        'Last name for this submission',
                        VALUE_REQUIRED
                    ),
                    'idnumber' => new external_value(
                        PARAM_TEXT,
                        'ID number of the user for this submission',
                        VALUE_REQUIRED
                    ),
                    'attemptnumber' => new external_value(
                        PARAM_INT,
                        'Sequential attempt number of this submission',
                        VALUE_REQUIRED
                    ),
                    'status' => new external_value(
                        PARAM_TEXT,
                        'Status of the submission',
                        VALUE_REQUIRED
                    ),
                    'timecreated' => new external_value(
                        PARAM_INT,
                        'Timestamp of when the submission was created',
                        VALUE_REQUIRED
                    ),
                    'timemodified' => new external_value(
                        PARAM_INT,
                        'Timestamp of when the submission was last modified',
                        VALUE_REQUIRED
                    ),
                    'timestarted' => new external_value(
                        PARAM_INT,
                        'Timestamp of when the submission was started',
                        VALUE_REQUIRED
                    ),
                ]),
                'Submission metadata for each submission ID',
                VALUE_OPTIONAL
            ),
        ]);
    }

    /**
     * Retrieves metadata for a list of assignment submissions
     *
     * @param string $uuidraw UUID assigned to this task by the worker service
     * @param int $taskidraw ID of the activity archiving task this request belongs to
     * @param array $submissionidsraw IDs of the assignment submissions
     *
     * @return array According to execute_returns()
     *
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     */
    public static function execute(
        string $uuidraw,
        int $taskidraw,
        array $submissionidsraw
    ): array {
        // Validate request.
        $params = self::validate_parameters(self::execute_parameters(), [
            'uuid' => $uuidraw,
            'taskid' => $taskidraw,
            'submissionids' => $submissionidsraw,
        ]);

        // Validate submissionids.
        if (empty($params['submissionids'])) {
            return ['status' => webservice_status::E_SUBMISSION_NOT_FOUND->name];
        }

        // Find the task.
        try {
            $task = activity_archiving_task::get_by_id($params['taskid']);
        } catch (\dml_exception $e) {
            return ['status' => webservice_status::E_TASK_NOT_FOUND->name];
        }

        // Check access rights.
        if ($task->get_webservice_token() !== optional_param('wstoken', null, PARAM_TEXT)) {
            return ['status' => webservice_status::E_ACCESS_DENIED->name];
        }

        // Ensure that we are supposed to handle this task.
        if ($task->get_archivingmodname() !== 'assign') {
            return ['status' => webservice_status::E_TASK_TYPE_INVALID->name];
        }

        // Get assignment manager and build response.
        $manager = assignment_manager::from_context($task->get_context());
        return [
            'courseid' => $manager->get_course()->id,
            'cmid' => $manager->get_cm()->id,
            'assignmentid' => $manager->get_assignment()->get_instance()->id,
            'submissions' => $manager->get_submissions_metadata($params['submissionids']),
            'status' => webservice_status::OK->name,
        ];
    }
}
