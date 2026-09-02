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

/**
 * Web service function declarations for the archivingmod_assign plugin.
 *
 * @package     archivingmod_assign
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


// Web service function definitions.
$functions = [
    'archivingmod_assign_generate_submission_report' => [
        'classname' => 'archivingmod_assign\external\generate_submission_report',
        'description' => 'Generates a full HTML DOM of the specified submission and attachment files metadata',
        'type' => 'read',
        'ajax' => true,
        'services' => [],
    ],
    'archivingmod_assign_get_submissions_metadata' => [
        'classname' => 'archivingmod_assign\external\get_submissions_metadata',
        'description' => 'Returns metadata about submissions of an assignment',
        'type' => 'read',
        'ajax' => true,
        'services' => [],
    ],
    'archivingmod_assign_update_task_status' => [
        'classname' => 'archivingmod_assign\external\update_task_status',
        'description' => 'Update the status of an assignment activity archiving task',
        'type' => 'write',
        'ajax' => true,
        'services' => [],
    ],
    'archivingmod_assign_process_uploaded_artifact' => [
        'classname' => 'archivingmod_assign\external\process_uploaded_artifact',
        'description' => 'Process an uploaded artifact for assignment archiving',
        'type' => 'write',
        'ajax' => true,
        'services' => [],
    ],
];

// Web service definitions.
$services = [
    'archivingmod_assign_ws' => [
        'functions' => array_keys($functions),
        'shortname' => 'archivingmod_assign_ws',
        'requiredcapability' => '',
        'restrictedusers' => false,
        'enabled' => 1,
        'downloadfiles' => true,
        'uploadfiles'  => true,
    ],
];
