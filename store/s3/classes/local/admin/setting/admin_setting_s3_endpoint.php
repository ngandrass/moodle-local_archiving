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
 * Custom admin setting for S3 endpoint hostnames
 *
 * @package     archivingstore_s3
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_s3\local\admin\setting;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->libdir . '/adminlib.php'); // @codeCoverageIgnore


/**
 * Custom admin setting for S3 endpoint hostnames
 */
class admin_setting_s3_endpoint extends \admin_setting_configtext {
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
     * @throws \coding_exception
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

        // Reject a leading scheme, e.g. "https://".
        if (preg_match('|^[a-zA-Z][a-zA-Z0-9+.-]*://|', $data)) {
            return get_string('error_s3_endpoint_must_not_contain_scheme', 'archivingstore_s3');
        }

        // Split into host and optional port.
        $parts = explode(':', $data);
        if (count($parts) > 2) {
            return get_string('error_s3_endpoint_invalid_port', 'archivingstore_s3');
        }

        // Validate port number.
        $host = $parts[0];
        if (count($parts) === 2) {
            $port = $parts[1];
            if (!is_numeric($port) || (int) $port < 1 || (int) $port > 65535) {
                return get_string('error_s3_endpoint_invalid_port', 'archivingstore_s3');
            }
        }

        // Reject any remaining path component.
        if ($host === '' || str_contains($host, '/')) {
            return get_string('error_s3_endpoint_must_not_contain_path', 'archivingstore_s3');
        }

        return true;
    }
}
