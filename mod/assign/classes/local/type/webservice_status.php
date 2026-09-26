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

// phpcs:disable moodle.Commenting.InlineComment.DocBlock

/**
 * Status codes a webservice function can respond with
 *
 * @package     archivingmod_assign
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\local\type;


// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Status codes a webservice function can respond with
 */
enum webservice_status {
    /** @var self Success */
    case OK;

    /** @var self Access to the requested resource was denied */
    case E_ACCESS_DENIED;

    /** @var self Invalid parameter received */
    case E_INVALID_PARAM;

    /** @var self Updating data failed */
    case E_UPDATE_FAILED;

    /** @var self No task with given taskid was found */
    case E_TASK_NOT_FOUND;

    /** @var self A task was found but had an invalid archivingmod associated with it */
    case E_TASK_TYPE_INVALID;

    /** @var self Course could not be found */
    case E_COURSE_NOT_FOUND;

    /** @var self Course module could not be found */
    case E_CM_NOT_FOUND;

    /** @var self Assignment could not be found */
    case E_ASSIGNMENT_NOT_FOUND;

    /** @var self No submission was found with the given ID */
    case E_SUBMISSION_NOT_FOUND;

    /** @var self Given foldername pattern was invalid */
    case E_INVALID_FOLDERNAME_PATTERN;

    /** @var self Given filename pattern was invalid */
    case E_INVALID_FILENAME_PATTERN;

    /** @var self Invalid status value given */
    case E_INVALID_STATUS;

    /** @var self Invalid progress value given */
    case E_INVALID_PROGRESS;

    /** @var self The task or job is already completed and can not be altered */
    case E_ALREADY_COMPLETED;

    /** @var self No file upload was expected */
    case E_NO_UPLOAD_EXPECTED;

    /** @var self File could not be found */
    case E_FILE_NOT_FOUND;

    /** @var self Failed to reassemble individually uploaded chunks to original file */
    case E_CHUNK_REASSEMBLY_FAILED;

    /** @var self Expected an artifact count of one or greater, got negative or zero value */
    case E_INVALID_ARTIFACT_COUNT;

    /** @var self Checksum validation failed */
    case E_CHECKSUM_MISMATCH;

    /** @var self Storing a file failed */
    case E_STORING_FAILED;
}
