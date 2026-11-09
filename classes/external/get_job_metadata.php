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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_archiving\archive_job;
use local_archiving\file_handle;
use local_archiving\local\logging\logger;

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
                        'logs' => new external_value(
                            PARAM_RAW,
                            'Log lines related to the archiving activity of the job',
                            VALUE_OPTIONAL,
                        ),
                        'action_urls' => new external_single_structure(
                            [
                                'download' => new external_value(
                                    PARAM_RAW,
                                    'URL to download the job artifacts, present only when the download is available',
                                    VALUE_OPTIONAL,
                                ),
                                'cm' => new external_value(
                                    PARAM_RAW,
                                    'URL to the related activity',
                                    VALUE_REQUIRED,
                                ),
                                'job' => new external_value(
                                    PARAM_RAW,
                                    'URL to the job details page',
                                    VALUE_REQUIRED,
                                ),
                            ],
                            'Action URLs for this job',
                            VALUE_REQUIRED,
                        ),
                        'cm_name' => new external_value(
                            PARAM_TEXT,
                            'Display name of the related activity',
                            VALUE_REQUIRED,
                        ),
                        'content_count' => new external_value(
                            PARAM_INT,
                            'Number of archived objects (0 if none)',
                            VALUE_REQUIRED,
                        ),
                        'files' => new external_multiple_structure(
                            new external_value(
                                PARAM_TEXT,
                                'Name of an artifact file',
                                VALUE_REQUIRED,
                            ),
                            'Names of the artifact files',
                            VALUE_REQUIRED,
                        ),
                        'metadata' => new external_multiple_structure(
                            new external_single_structure(
                                [
                                    'key' => new external_value(
                                        PARAM_TEXT,
                                        'Key of the metadata',
                                        VALUE_REQUIRED,
                                    ),
                                    'humankey' => new external_value(
                                        PARAM_TEXT,
                                        'Human readable key of the metadata',
                                        VALUE_REQUIRED,
                                    ),
                                    'value' => new external_value(
                                        PARAM_TEXT,
                                        'Value of the metadata',
                                        VALUE_REQUIRED,
                                    ),
                                ],
                                'Metadata elements',
                                VALUE_OPTIONAL,
                            ),
                            'Metadata of the job',
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

        // Build response.
        $status = $job->get_status();
        $jobmeta = $job->get_metadata_entries();

        $jobdata = [
            'id' => $job->get_id(),
            'complete' => $job->is_completed(),
            'status' => $status->value,
            'status_display_args' => (array) $status->status_display_args(),
            'timecreated' => $job->get_timecreated(),
            'timemodified' => $job->get_timemodified(includetasks: true),
            'action_urls' => [
                'job' => (
                    new \moodle_url('/local/archiving/job.php', ['id' => $job->get_id()])
                )->out(false),
            ],
            'content_count' => $job->get_content_count(),
            'metadata' => array_map(
                fn($key, $value): array => [
                    'key' => $key,
                    'humankey' => get_string("job_metadata_{$key}", 'local_archiving'),
                    'value' => $value,
                ],
                array_keys($jobmeta),
                array_values($jobmeta)
            ),
        ];

        // ... Jobs progress, if any.
        $progress = $job->get_progress();
        if ($progress !== null) {
            $jobdata['progress'] = $progress;
        }

        // ... Formatted logs blob, if any.
        $logs = array_reduce(
            $job->get_logger()->get_logs(limitnum: 99999),
            fn ($log, $entry) => $log . logger::format_log_entry($entry) . "\r\n",
            ""
        );
        if ($logs !== '') {
            $jobdata['logs'] = $logs;
        }

        $filehandles = file_handle::get_by_jobid($job->get_id());
        [, $cm] = get_course_and_cm_from_cmid($job->get_cmid());

        // ... Action URLs.
        $jobdata['action_urls']['cm'] = $cm->get_url()->out(false);
        if ($status->is_final() && count($filehandles) > 0) {
            $jobdata['action_urls']['download'] = (
                new \moodle_url(
                    '/local/archiving/download.php',
                    ['jobid' => $job->get_id()],
                )
            )->out(false);
        }

        // ... Course module name.
        $jobdata['cm_name'] = $cm->get_formatted_name(['escape' => false]);

        // ... Associated (not deleted) artifact file names, if any.
        $jobdata['files'] = array_values(array_map(
            fn ($handle) => $handle->filename,
            array_filter($filehandles, fn ($handle) => !$handle->deleted)
        ));

        // Send successfull response.
        return [
            'status' => 'OK',
            'job' => $jobdata,
        ];
    }
}
