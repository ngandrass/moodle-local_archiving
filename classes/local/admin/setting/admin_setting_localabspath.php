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

namespace local_archiving\local\admin\setting;


// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Custom admin setting for local absolute paths
 *
 * @codeCoverageIgnore
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_localabspath extends \admin_setting_configtext {

    /**
     * Creates a new instance of this setting
     *
     * @param string $name unique ascii name for setting
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting Default value
     * @param int|null $size default field size
     */
    #[\Override]
    public function __construct(
        $name,
        $visiblename,
        $description,
        $defaultsetting,
        $size = null
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW, $size);
    }

    /**
     * Validate data before storing
     *
     * @param string $data data
     * @return mixed true if ok string if error found
     * @throws \coding_exception
     */
    #[\Override]
    public function validate($data) {
        global $CFG;

        // Basic data validation.
        $parentvalidation = parent::validate($data);
        if ($parentvalidation !== true) {
            return $parentvalidation;
        }

        // This is required.
        if (empty($data)) {
            return get_string('required');
        }

        // Ensure we have an absolute path without relative "double dots" in the middle or dots at the end.
        $data = trim($data);
        if (!preg_match('/^(?!\.)(?!.*\.\.)(?!.*\.$)(([A-Z]:\\\\)|(\/)).*/', $data)) {
            return get_string('error_localpath_must_be_absolute', 'local_archiving');
        }

        // Skip further path checks during installation / upgrades.
        if (defined('CLI_UPGRADE_RUNNING') || !empty($CFG->upgraderunning)) {
            return true;
        } else if ($data === $this->defaultsetting) {
            // If the default setting is used, we need to skip extended validation to allow default settings to be applied
            // automatically during installation or upgrades.
            return true;
        }

        // Ensure that the path is an existing (or parent-writable) local directory.
        if (!is_dir($data)) {
            // Check if the path is actually a file without an extension.
            if (is_file($data)) {
                return get_string('error_localpath_must_be_directory', 'local_archiving');
            }

            // If the directory does not exist, try to create it.
            if (!@mkdir($data)) {
                return get_string('error_localpath_cloud_not_be_created', 'local_archiving');
            }
        }

        return true;
    }

}
