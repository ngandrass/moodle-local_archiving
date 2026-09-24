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
 * Wrapper for possible outcomes of an S3 connectivity/credentials check
 *
 * @package     archivingstore_s3
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Commenting.InlineComment.DocBlock

namespace archivingstore_s3\local\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Possible outcomes of an S3 connectivity/credentials check
 */
enum connection_status {
    /** The endpoint could not be reached at all (DNS failure, connection refused, TLS handshake/certificate error). */
    case CONNECTION_ERROR;

    /** The endpoint was reached but rejected the request credentials/signature (HTTP 403). */
    case AUTH_ERROR;

    /** The endpoint was reached and the request was authenticated, but the bucket does not exist (HTTP 404). */
    case BUCKET_NOT_FOUND;

    /** The endpoint was reached but responded in an unexpected way (any other non-2xx status). */
    case UNEXPECTED_ERROR;

    /** The endpoint was reached, the request was authenticated, and the bucket is accessible. */
    case OK;
}
