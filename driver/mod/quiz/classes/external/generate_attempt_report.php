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
 * This file defines the generate_attempt_report webservice function
 *
 * @package   archivingmod_quiz
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\external;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


use archivingmod_quiz\quiz_manager;
use archivingmod_quiz\type\attempt_filename_variable;
use archivingmod_quiz\type\attempt_report_section;
use archivingmod_quiz\type\webservice_status;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_archiving\activity_archiving_task;
use local_archiving\storage;

/**
 * API endpoint to generate a quiz attempt report as part of an activity
 * archiving task
 */
class generate_attempt_report extends external_api {
    /**
     * Returns description of method parameters
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
            'attemptid' => new external_value(
                PARAM_INT,
                'ID of the quiz attempt',
                VALUE_REQUIRED
            ),
            'foldernamepattern' => new external_value(
                PARAM_TEXT,
                'Folder name pattern to use for generating the attempt folder',
                VALUE_REQUIRED
            ),
            'filenamepattern' => new external_value(
                PARAM_TEXT,
                'Filename pattern to use for the generated attempt files',
                VALUE_REQUIRED
            ),
            'sections' => new external_single_structure(
                array_combine(
                    array_map(fn($section) => $section->value, attempt_report_section::cases()),
                    array_map(fn($section) => new external_value(
                        PARAM_BOOL,
                        'Whether to include the ' . $section->name . ' section',
                        VALUE_REQUIRED
                    ), attempt_report_section::cases())
                ),
                'Sections to include in the report',
                VALUE_REQUIRED,
            ),
            'attachments' => new external_value(
                PARAM_BOOL,
                'Whether to check for attempts and include metadata if present',
                VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Returns description of return parameters
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid' => new external_value(
                PARAM_INT,
                'ID of the quiz attempt',
                VALUE_OPTIONAL
            ),
            'foldername' => new external_value(
                PARAM_TEXT,
                'Desired name of the folder to store this quiz attempt report in',
                VALUE_OPTIONAL
            ),
            'filename' => new external_value(
                PARAM_TEXT,
                'Desired filename of this quiz attempt report',
                VALUE_OPTIONAL
            ),
            'report' => new external_value(
                PARAM_RAW,
                'HTML DOM of the generated quiz attempt report',
                VALUE_OPTIONAL
            ),
            'attachments' => new external_multiple_structure(
                new external_single_structure([
                    'slot' => new external_value(
                        PARAM_INT,
                        'Number of the quiz slot this file is attached to',
                        VALUE_REQUIRED
                    ),
                    'filename' => new external_value(
                        PARAM_TEXT,
                        'Filename of the attachment',
                        VALUE_REQUIRED
                    ),
                    'filesize' => new external_value(
                        PARAM_INT,
                        'Filesize of the attachment',
                        VALUE_REQUIRED
                    ),
                    'mimetype' => new external_value(
                        PARAM_TEXT,
                        'Mimetype of the attachment',
                        VALUE_REQUIRED
                    ),
                    'contenthash' => new external_value(
                        PARAM_TEXT,
                        'Contenthash (SHA-1) of the attachment',
                        VALUE_REQUIRED
                    ),
                    'downloadurl' => new external_value(
                        PARAM_TEXT,
                        'URL to download the attachment',
                        VALUE_REQUIRED
                    ),
                ]),
                'Files attached to the quiz attempt',
                VALUE_OPTIONAL
            ),
            'status' => new external_value(
                PARAM_TEXT,
                'Status of the executed wsfunction',
                VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Generate an quiz attempt report as HTML DOM
     *
     * @param string $uuidraw UUID assigned to this task by the worker service
     * @param int $taskidraw ID of the activity archiving task this request belongs to
     * @param int $attemptidraw ID of the quiz attempt
     * @param string $foldernamepatternraw Folder name pattern to use for report name generation
     * @param string $filenamepatternraw Filename pattern to use for report name generation
     * @param array $sectionsraw Sections to include in the report
     * @param bool $attachmentsraw Whether to check for attempts and include metadata if present
     *
     * @return array According to execute_returns()
     *
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     * @throws \DOMException
     */
    public static function execute(
        string $uuidraw,
        int $taskidraw,
        int $attemptidraw,
        string $foldernamepatternraw,
        string $filenamepatternraw,
        array $sectionsraw,
        bool $attachmentsraw
    ): array {
        global $PAGE;

        // Validate request.
        $params = self::validate_parameters(self::execute_parameters(), [
            'uuid' => $uuidraw,
            'taskid' => $taskidraw,
            'attemptid' => $attemptidraw,
            'foldernamepattern' => $foldernamepatternraw,
            'filenamepattern' => $filenamepatternraw,
            'sections' => $sectionsraw,
            'attachments' => $attachmentsraw,
        ]);

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

        // Validate folder and filename pattern.
        if (
            !storage::is_valid_filename_pattern(
                $params['foldernamepattern'],
                attempt_filename_variable::values(),
                storage::FOLDERNAME_FORBIDDEN_CHARACTERS
            )
        ) {
            return ['status' => webservice_status::E_INVALID_FOLDERNAME_PATTERN->name];
        }
        if (
            !storage::is_valid_filename_pattern(
                $params['filenamepattern'],
                attempt_filename_variable::values(),
                storage::FILENAME_FORBIDDEN_CHARACTERS
            )
        ) {
            return ['status' => webservice_status::E_INVALID_FILENAME_PATTERN->name];
        }

        // Ensure that requested attempt exists in quiz.
        $quizmanager = quiz_manager::from_context($task->get_context());
        if (!$quizmanager->attempt_exists($params['attemptid'])) {
            return ['status' => webservice_status::E_ATTEMPT_NOT_FOUND->name];
        }

        // The following code is tested covered by more specific tests.
        // phpcs:ignore
        // @codeCoverageIgnoreStart

        // Forcefully set URL in $PAGE to the webservice handler to prevent future warnings.
        $PAGE->set_url(new \moodle_url('/webservice/rest/server.php', [
            'wsfunction' => 'archivingmod_quiz_generate_attempt_report',
        ]));

        // Generate attempt report as HTML.
        $sections = [];
        foreach ($params['sections'] as $section => $enabled) {
            if ($enabled) {
                if (!$sectiontype = attempt_report_section::tryFrom($section)) {
                    return ['status' => webservice_status::E_INVALID_PARAM->name];
                }
                $sections[] = $sectiontype;
            }
        }
        $report = $quizmanager->attempt_report();
        $res = [
            'attemptid' => $params['attemptid'],
            'report' => $report->generate_full_page($params['attemptid'], $sections),
        ];

        // Check for attachments.
        if ($params['attachments']) {
            $res['attachments'] = $quizmanager->get_attempt_attachments_metadata($params['attemptid']);
        } else {
            $res['attachments'] = [];
        }

        // Generate folder- and filename.
        $res['foldername'] = $report->generate_attempt_filename(
            attemptid: $params['attemptid'],
            pattern: $params['foldernamepattern'],
            isfoldername: true
        );
        $res['filename'] = $report->generate_attempt_filename(
            attemptid: $params['attemptid'],
            pattern: $params['filenamepattern'],
            isfoldername: false
        );

        // Log and return response.
        $task->get_logger()->debug("Generated report for attempt {$params['attemptid']}");
        $res['status'] = webservice_status::OK->name;

        return $res;
        // phpcs:ignore
        // @codeCoverageIgnoreEnd
    }
}
