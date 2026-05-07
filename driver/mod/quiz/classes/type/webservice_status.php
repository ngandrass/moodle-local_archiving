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
 * @package     archivingmod_quiz
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\type;


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

    /** @var self Course could not be found */
    case E_COURSE_NOT_FOUND;

    /** @var self Course module could not be found */
    case E_CM_NOT_FOUND;

    /** @var self Quiz could not be found */
    case E_QUIZ_NOT_FOUND;

    /** @var self No attempt was found with the given ID */
    case E_ATTEMPT_NOT_FOUND;

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

    /** @var self Checksum validation failed */
    case E_CHECKSUM_MISMATCH;

    /** @var self Storing a file failed */
    case E_STORING_FAILED;
}
