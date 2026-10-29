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
 * This file defines the get_job_metadata webservice function
 *
 * @package   local_archiving
 * @author    Björn Dittmann <bjoern.dittmann@haw-hamburg.de>
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\external;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_archiving\archive_job;
use local_archiving\local\type\archive_job_status;

/**
 * API endpoint to get the metadata of an archive job for client-side polling
 *
 * This endpoint is meant to be polled by the client while a job is still
 * running. It returns a snapshot of the current job state that can be used to
 * realize dynamic UI updates.
 */
class get_job_metadata extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(
                PARAM_INT,
                'ID of the job',
                VALUE_REQUIRED,
            ),
        ]);
    }

    /**
     * Returns description of return parameters
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'status' => new external_value(
                    PARAM_TEXT,
                    'Status of the executed webservice call',
                    VALUE_REQUIRED,
                ),
                'job' => new external_single_structure(
                    [
                        'id' => new external_value(
                            PARAM_INT,
                            'ID of the job',
                            VALUE_REQUIRED,
                        ),
                        'complete' => new external_value(
                            PARAM_BOOL,
                            'Whether the job has reached a final state',
                            VALUE_REQUIRED,
                        ),
                        'status' => new external_value(
                            PARAM_INT,
                            'Raw job status enum value',
                            VALUE_REQUIRED,
                        ),
                        'status_display_args' => new external_single_structure(
                            [
                                'color' => new external_value(
                                    PARAM_TEXT,
                                    'CSS color variant for the job status badge',
                                    VALUE_REQUIRED,
                                ),
                                'text' => new external_value(
                                    PARAM_TEXT,
                                    'Localized human-readable status label',
                                    VALUE_REQUIRED,
                                ),
                                'help' => new external_value(
                                    PARAM_TEXT,
                                    'Localized help text for the status badge',
                                    VALUE_REQUIRED,
                                ),
                            ],
                            'Display arguments for the current job status',
                            VALUE_REQUIRED,
                        ),
                        'progress' => new external_value(
                            PARAM_INT,
                            'Progress of the job in percent (0 to 100)',
                            VALUE_OPTIONAL,
                        ),
                        'timecreated' => new external_value(
                            PARAM_INT,
                            'Timestamp of job creation',
                            VALUE_REQUIRED,
                        ),
                        'timemodified' => new external_value(
                            PARAM_INT,
                            'Timestamp of the job\'s last update',
                            VALUE_REQUIRED,
                        ),
                    ],
                    'Complete data about the job, suitable for any client-side consumption',
                    VALUE_OPTIONAL,
                ),
            ],
            'Job data for client-side polling',
            VALUE_REQUIRED,
        );
    }

    /**
     * Execute the webservice function
     *
     * @param int $jobidraw
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public static function execute(
        int $jobidraw,
    ): array {
        // Validate request.
        $params = self::validate_parameters(self::execute_parameters(), [
            'jobid' => $jobidraw,
        ]);

        // Validate that the job exists.
        try {
            $job = archive_job::get_by_id($params['jobid']);
        } catch (\dml_missing_record_exception $e) {
            return ['status' => 'E_JOB_NOT_FOUND'];
        }

        // Check capabilities.
        $context = $job->get_context();
        self::validate_context($context);
        require_capability('local/archiving:view', $context);

        // Housekeeping.
        if (!$job->is_completed() && $job->is_overdue()) {
            $job->set_status(archive_job_status::TIMEOUT);
        }

        // Prepare data.
        $status = $job->get_status();

        // Build response.
        $jobdata = [
            'id' => $job->get_id(),
            'complete' => $job->is_completed(),
            'status' => $status->value,
            'status_display_args' => (array) $status->status_display_args(),
            'timecreated' => $job->get_timecreated(),
            'timemodified' => $job->get_timemodified(),
        ];

        $progress = $job->get_progress();
        if ($progress !== null) {
            $jobdata['progress'] = $progress;
        }

        return [
            'status' => 'OK',
            'job' => $jobdata,
        ];
    }
}
