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
 * Web service function declarations for the archivingmod_quiz plugin.
 *
 * @package     archivingmod_quiz
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


// Web service function definitions.
$functions = [
    'archivingmod_quiz_generate_attempt_report' => [
        'classname' => 'archivingmod_quiz\external\generate_attempt_report',
        'description' => 'Generates a full HTML DOM containing all report data on the specified attempt',
        'type' => 'read',
        'ajax' => true,
        'services' => [],
    ],
    'archivingmod_quiz_get_attempts_metadata' => [
        'classname' => 'archivingmod_quiz\external\get_attempts_metadata',
        'description' => 'Returns metadata about attempts of a quiz',
        'type' => 'read',
        'ajax' => true,
        'services' => [],
    ],
    'archivingmod_quiz_update_task_status' => [
        'classname' => 'archivingmod_quiz\external\update_task_status',
        'description' => 'Updates the status of a quiz archiving task',
        'type' => 'write',
        'ajax' => true,
        'services' => [],
    ],
    'archivingmod_quiz_process_uploaded_artifact' => [
        'classname' => 'archivingmod_quiz\external\process_uploaded_artifact',
        'description' => 'Called by the archive worker once an artifact has been uploaded and is ready for processing.',
        'type' => 'write',
        'ajax' => true,
        'services' => [],
    ],
];

// Web service definitions.
$services = [
    'archivingmod_quiz_ws' => [
        'functions' => array_keys($functions),
        'shortname' => 'archivingmod_quiz_ws',
        'requiredcapability' => '',
        'restrictedusers' => false,
        'enabled' => 1,
        'downloadfiles' => true,
        'uploadfiles'  => true,
    ],
];
