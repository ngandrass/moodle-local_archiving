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
 * Plugin strings are defined here
 *
 * @package     archivingtrigger_cron
 * @category    string
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// @codingStandardsIgnoreFile

// Common
$string['pluginname'] = 'Scheduled';
$string['privacy:metadata'] = 'This archiving trigger plugin does not store any personal data.';

// Settings.
$string['setting_enabled'] = 'Enabled';
$string['setting_enabled_desc'] = 'Enables or disables this archiving trigger.';
$string['setting_archive_unchanged'] = 'Archive unchanged';
$string['setting_archive_unchanged_desc'] = 'If enabled, activities will be archived again, even if they have not changed since the last archiving run.';
$string['setting_cronschedule'] = 'Archiving schedule';
$string['setting_cronschedule_desc'] = 'Defines when targeted activities are archived automatically.<br>Next archiving run starts in {$a}.';
$string['setting_cronschedule_button'] = 'Edit schedule';
$string['setting_dryrun'] = 'Dry run';
$string['setting_dryrun_desc'] = 'If enabled, no actual archive jobs are created. This can be used to see which activities would be archived and to validate the configuration.';

// Tasks.
$string['task_trigger_archiving'] = 'Trigger archiving';
