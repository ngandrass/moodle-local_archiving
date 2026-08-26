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
 * Result of an S3 connectivity/credentials check
 *
 * @package     archivingstore_s3
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_s3\local;

use archivingstore_s3\local\type\connection_status;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Result of an S3 connectivity/credentials check, as performed by s3_client::check_connection()
 */
final class connection_check_result {
    /**
     * Creates a new instance of this result
     *
     * @param connection_status $status Classified outcome of the check
     * @param string|null $message Human-readable detail (S3 error message or curl error string),
     * null when $status is OK
     */
    public function __construct(
        /** @var connection_status $status Classified outcome of the check */
        public readonly connection_status $status,
        /** @var string|null $message Human-readable detail (S3 error message or curl error string), null when $status is OK */
        public readonly ?string $message = null
    ) {
    }

    /**
     * Determines if the connection check was successful
     *
     * @return bool True if the endpoint is reachable, authenticated, and the bucket is accessible
     */
    public function is_ok(): bool {
        return $this->status === connection_status::OK;
    }
}
