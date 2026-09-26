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
 * This file defines the generate_submission_report webservice function
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\external;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


use archivingmod_assign\assignment_manager;
use archivingmod_assign\local\type\attachment_type;
use archivingmod_assign\local\type\submission_filename_variable;
use archivingmod_assign\local\type\submission_report_section;
use archivingmod_assign\local\type\webservice_status;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_archiving\activity_archiving_task;
use local_archiving\storage;

/**
 * API endpoint to generate an assignment submission report as part of an
 * activity archiving task
 */
class generate_submission_report extends external_api {
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
            'submissionid' => new external_value(
                PARAM_INT,
                'ID of the assignment submission',
                VALUE_REQUIRED
            ),
            'foldernamepattern' => new external_value(
                PARAM_TEXT,
                'Folder name pattern to use for generating the submission folder',
                VALUE_REQUIRED
            ),
            'filenamepattern' => new external_value(
                PARAM_TEXT,
                'Filename pattern to use for the generated submission files',
                VALUE_REQUIRED
            ),
            'sections' => new external_single_structure(
                array_combine(
                    array_map(fn($section) => $section->value, submission_report_section::cases()),
                    array_map(fn($section) => new external_value(
                        PARAM_BOOL,
                        'Whether to include the ' . $section->name . ' section',
                        VALUE_REQUIRED
                    ), submission_report_section::cases())
                ),
                'Sections to include in the report',
                VALUE_REQUIRED,
            ),
        ]);
    }

    /**
     * Returns description of return parameters
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'submissionid' => new external_value(
                PARAM_INT,
                'ID of the assignment submission',
                VALUE_OPTIONAL
            ),
            'foldername' => new external_value(
                PARAM_TEXT,
                'Desired name of the folder to store this submission report in',
                VALUE_OPTIONAL
            ),
            'filename' => new external_value(
                PARAM_TEXT,
                'Desired filename of this submission report',
                VALUE_OPTIONAL
            ),
            'report' => new external_value(
                PARAM_RAW,
                'HTML DOM of the generated assignment submission report',
                VALUE_OPTIONAL
            ),
            'attachments' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(
                        PARAM_ALPHA,
                        'Type of the attachment as per \archivingmod_assign\local\type\attachment_type',
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
                'Files attached to the assignment submission',
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
     * Generates an submission attempt report as HTML DOM and adds
     * metadata about all files that are attached to the submission.
     *
     * @param string $uuidraw UUID assigned to this task by the worker service
     * @param int $taskidraw ID of the activity archiving task this request belongs to
     * @param int $submissionidraw ID of the assignment submission
     * @param string $foldernamepatternraw Folder name pattern to use for report name generation
     * @param string $filenamepatternraw Filename pattern to use for report name generation
     * @param array $sectionsraw Sections to include in the report
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
        int $submissionidraw,
        string $foldernamepatternraw,
        string $filenamepatternraw,
        array $sectionsraw,
    ): array {
        global $PAGE;

        // Validate request.
        $params = self::validate_parameters(self::execute_parameters(), [
            'uuid' => $uuidraw,
            'taskid' => $taskidraw,
            'submissionid' => $submissionidraw,
            'foldernamepattern' => $foldernamepatternraw,
            'filenamepattern' => $filenamepatternraw,
            'sections' => $sectionsraw,
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

        // Ensure that we are supposed to handle this task.
        if ($task->get_archivingmodname() !== 'assign') {
            return ['status' => webservice_status::E_TASK_TYPE_INVALID->name];
        }

        // Validate folder and filename pattern.
        if (
            !storage::is_valid_filename_pattern(
                $params['foldernamepattern'],
                submission_filename_variable::values(),
                storage::FOLDERNAME_FORBIDDEN_CHARACTERS
            )
        ) {
            return ['status' => webservice_status::E_INVALID_FOLDERNAME_PATTERN->name];
        }
        if (
            !storage::is_valid_filename_pattern(
                $params['filenamepattern'],
                submission_filename_variable::values(),
                storage::FILENAME_FORBIDDEN_CHARACTERS
            )
        ) {
            return ['status' => webservice_status::E_INVALID_FILENAME_PATTERN->name];
        }

        $manager = assignment_manager::from_context($task->get_context());

        // Ensure that the submission exists.
        if (!$manager->submission_exists($params['submissionid'])) {
            return ['status' => webservice_status::E_SUBMISSION_NOT_FOUND->name];
        }

        // The following code is tested covered by more specific tests.
        // phpcs:ignore
        // @codeCoverageIgnoreStart

        // Forcefully set URL in $PAGE to the webservice handler to prevent future warnings.
        $PAGE->set_url(new \moodle_url('/webservice/rest/server.php', [
            'wsfunction' => 'archivingmod_assign_generate_submission_report',
        ]));

        // Parse requested sections.
        $sections = [];
        foreach ($params['sections'] as $section => $enabled) {
            if ($enabled) {
                if (!$sectiontype = submission_report_section::tryFrom($section)) {
                    return ['status' => webservice_status::E_INVALID_PARAM->name];
                }
                $sections[] = $sectiontype;
            }
        }

        // Generate submission report and attachments data.
        $report = $manager->submission_report();
        $res = [
            'submissionid' => $params['submissionid'],
            'report' => $report->generate_full_page($params['submissionid'], $sections),
            'attachments' => $manager->get_submission_attachments_metadata($params['submissionid']),
        ];

        // Generate folder- and filename.
        $res['foldername'] = $report->generate_submission_filename(
            submissionid: $params['submissionid'],
            pattern: $params['foldernamepattern'],
            isfoldername: true
        );
        $res['filename'] = $report->generate_submission_filename(
            submissionid: $params['submissionid'],
            pattern: $params['filenamepattern'],
            isfoldername: false
        );

        // Log and return response.
        $task->get_logger()->debug("Generated report for assignment submission {$params['submissionid']}");
        $res['status'] = webservice_status::OK->name;

        return $res;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Transforms a list of stored_file objects of the given attachment_type
     * into the representation that is expected by this external function return
     * specification.
     *
     * @param attachment_type $type The type of given attachments
     * @param array $files List of stored_file objects to create the
     * representation for
     * @return array Attachment representations as expected by this external
     * service
     * @throws \coding_exception On invalid input
     */
    public static function prepare_attachment_list_for_response(attachment_type $type, array $files): array {
        $res = [];

        foreach ($files as $file) {
            if (!($file instanceof \stored_file)) {
                throw new \coding_exception('Expected an array of stored_file objects, but got something else.');
            }

            $res[] = [
                'type' => $type->value,
                'filename' => $file->get_filename(),
                'filesize' => $file->get_filesize(),
                'mimetype' => $file->get_mimetype(),
                'contenthash' => $file->get_contenthash(),
                'downloadurl' => \moodle_url::make_webservice_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false),
            ];
        }

        return $res;
    }
}
