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
 * @package     archivingstore_localdir
 * @category    string
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// @codingStandardsIgnoreFile

$string['pluginname'] = 'Local Directory';
$string['privacy:metadata:storage'] = 'Archive files are written to a directory on the server outside of the Moodle file system. Moodle cannot manage these files directly.';
$string['privacy:metadata:storage:filecontent'] = 'Content of the archive file, including all archived user data.';
$string['privacy:metadata:storage:filename'] = 'Name of the archive file. It can contain user details, depending on the configured filename patterns.';
$string['setting_enabled'] = 'Enabled';
$string['setting_enabled_desc'] = 'Enables or disables this storage driver. If disabled, no archives can be sent to or retrieved from this storage.';
$string['setting_storage_path'] = 'Storage path';
$string['setting_storage_path_desc'] = 'The absolute path to the directory where archives are stored. This directory must exist and be writable by the web server user.';
$string['storagepathdoesnotexist'] = 'Storage path does not exist.';
$string['storagepathnotconfigured'] = 'Storage path was not configured yet.';
