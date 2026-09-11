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
 * This file defines the process_uploaded_artifact webservice function
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\external;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use archivingmod_assign\local\type\webservice_status;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_archiving\activity_archiving_task;
use local_archiving\file_reassembler;
use local_archiving\local\type\activity_archiving_task_status;
use local_archiving\storage;


/**
 * API endpoint to process an artifact that was previously uploaded by the
 * assignment archiver worker service
 */
class process_uploaded_artifact extends external_api {
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
                'ID of the task this artifact is associated with',
                VALUE_REQUIRED
            ),
            'artifact_component' => new external_value(
                PARAM_TEXT,
                'File API component',
                VALUE_REQUIRED
            ),
            'artifact_contextid' => new external_value(
                PARAM_INT,
                'File API contextid',
                VALUE_REQUIRED
            ),
            'artifact_userid' => new external_value(
                PARAM_INT,
                'File API userid',
                VALUE_REQUIRED
            ),
            'artifact_filearea' => new external_value(
                PARAM_TEXT,
                'File API filearea',
                VALUE_REQUIRED
            ),
            'artifact_filename' => new external_value(
                PARAM_TEXT,
                'File API filename',
                VALUE_REQUIRED
            ),
            'artifact_filepath' => new external_value(
                PARAM_TEXT,
                'File API filepath',
                VALUE_REQUIRED
            ),
            'artifact_itemid' => new external_value(
                PARAM_INT,
                'File API itemid',
                VALUE_REQUIRED
            ),
            'artifact_sha256sum' => new external_value(
                PARAM_TEXT,
                'SHA256 checksum of the file',
                VALUE_REQUIRED
            ),
            'artifact_count' => new external_value(
                PARAM_INT,
                'Number of individually uploaded files to process',
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
        ]);
    }

    /**
     * Execute the webservice function
     *
     * @param string $uuidraw
     * @param int $taskidraw
     * @param string $artifactcomponentraw
     * @param int $artifactcontextidraw
     * @param int $artifactuseridraw
     * @param string $artifactfilearearaw
     * @param string $artifactfilenameraw
     * @param string $artifactfilepathraw
     * @param int $artifactitemidraw
     * @param string $artifactsha256sumraw
     * @param int $artifactcountraw
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     */
    public static function execute(
        string $uuidraw,
        int $taskidraw,
        string $artifactcomponentraw,
        int $artifactcontextidraw,
        int $artifactuseridraw,
        string $artifactfilearearaw,
        string $artifactfilenameraw,
        string $artifactfilepathraw,
        int $artifactitemidraw,
        string $artifactsha256sumraw,
        int $artifactcountraw,
    ): array {
        // Validate request.
        $params = self::validate_parameters(self::execute_parameters(), [
            'uuid' => $uuidraw,
            'taskid' => $taskidraw,
            'artifact_component' => $artifactcomponentraw,
            'artifact_contextid' => $artifactcontextidraw,
            'artifact_userid' => $artifactuseridraw,
            'artifact_filearea' => $artifactfilearearaw,
            'artifact_filename' => $artifactfilenameraw,
            'artifact_filepath' => $artifactfilepathraw,
            'artifact_itemid' => $artifactitemidraw,
            'artifact_sha256sum' => $artifactsha256sumraw,
            'artifact_count' => $artifactcountraw,
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

        // Ensure that the task type matches.
        if ($task->get_archivingmodname() !== 'assign') {
            return ['status' => webservice_status::E_TASK_TYPE_INVALID->name];
        }

        // Do not allow uploading of artifacts for finished jobs.
        if ($task->is_completed()) {
            // This is just a safeguard since web service tokens should be invalidated once a task completes.
            return ['status' => webservice_status::E_NO_UPLOAD_EXPECTED->name]; // @codeCoverageIgnore
        }

        // Get or reconstruct uploaded file/-s.
        $draftfile = null;
        if ($params['artifact_count'] == 1) {
            // Find uploaded file (draftfile).
            $draftfile = get_file_storage()->get_file(
                contextid: $params['artifact_contextid'],
                component: 'user',
                filearea: 'draft',
                itemid: $params['artifact_itemid'],
                filepath: $params['artifact_filepath'],
                filename: $params['artifact_filename']
            );
            if (!$draftfile) {
                $task->set_status(activity_archiving_task_status::FAILED);
                return ['status' => webservice_status::E_FILE_NOT_FOUND->name];
            }
        } else if ($params['artifact_count'] > 1) {
            // Reassebmle orgininal file.
            $draftfile = file_reassembler::reassemble_chunked_file(
                $params['artifact_contextid'],
                $params['artifact_itemid'],
                $params['artifact_filepath'],
                $params['artifact_filename'],
                $params['artifact_count']
            );
            if (!$draftfile) {
                $task->set_status(activity_archiving_task_status::FAILED);
                return ['status' => webservice_status::E_CHUNK_REASSEMBLY_FAILED->name];
            }
        } else {
            $task->set_status(activity_archiving_task_status::FAILED);
            return ['status' => webservice_status::E_INVALID_ARTIFACT_COUNT->name];
        }

        // Validate uploaded file.
        // Note: We use SHA256 instead of Moodle sha1, since SHA1 is prone to hash collisions!
        if ($params['artifact_sha256sum'] != storage::hash_file($draftfile)) {
            $task->set_status(activity_archiving_task_status::FAILED);
            $draftfile->delete();
            return ['status' => webservice_status::E_CHECKSUM_MISMATCH->name];
        }

        // @codeCoverageIgnoreStart
        // The following code is tested covered by more specific tests.

        // Store uploaded file.
        try {
            $task->link_artifact(
                artifactfile: $draftfile,
                sha256sum: $params['artifact_sha256sum'],
                takeownership: true
            );
        } catch (\Exception $e) {
            $task->set_status(activity_archiving_task_status::FAILED);
            $draftfile->delete();
            return [
                'status' => webservice_status::E_STORING_FAILED->name,
            ];
        }

        // Report success.
        $task->set_status(activity_archiving_task_status::FINISHED);
        return [
            'status' => 'OK',
        ];
        // @codeCoverageIgnoreEnd
    }
}
