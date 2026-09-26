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
 * Custom admin setting for S3 bucket paths with respective validation rules
 *
 * @package     archivingstore_s3
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_s3\local\admin\setting;

use archivingstore_s3\local\s3_client;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->libdir . '/adminlib.php'); // @codeCoverageIgnore


/**
 * Custom admin setting for S3 bucket paths
 */
class admin_setting_s3_bucket_path extends \admin_setting_configtext {
    /**
     * Creates a new instance of this setting
     *
     * @param string $name unique ascii name for setting
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting Default value
     * @param int|null $size default field size
     */
    public function __construct(
        $name,
        $visiblename,
        $description,
        $defaultsetting,
        $size = null
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW_TRIMMED, $size);
    }

    /**
     * Validate data before storing
     *
     * @param string $data data
     * @return mixed true if ok, string if error found
     */
    #[\Override]
    public function validate($data) {
        $parentvalidation = parent::validate($data);
        if ($parentvalidation !== true) {
            return $parentvalidation;
        }

        // Empty is allowed: the storage driver is simply not configured yet.
        $data = trim($data);
        if ($data === '') {
            return true;
        }

        try {
            s3_client::parse_bucket_path($data);
        } catch (\moodle_exception $e) {
            return $e->getMessage();
        }

        return true;
    }
}
