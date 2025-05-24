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
 * Different storage tiers for archive data stores
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Different storage tiers for archive data stores
 *
 * This types classify data stores for archived data based on their access
 * performance and availability.
 */
enum storage_tier {

    /**
     * Data is stored locally on the server or is accessible VERY fast
     * (e.g., mounted NFS, CephFS).
     *
     * This implies that data can be accessed / streamed without the need to
     * retrieve a full local copy of the data first.
     */
    case LOCAL;

    /**
     * Data is stored on a fast remote server and needs to be fetched before use.
     *
     * A local copy of the data must be retrieved before if can be accessed,
     * however, the data access is fast (e.g., local FTP server, local S3 bucket)
     */
    case REMOTE_FAST;

    /**
     * Data is stored on a slow remote server and needs to be fetched before use.
     *
     * A local copy of the data must be retrieved before it can be accessed and
     * accessing the data can take some time (e.g., tape backup, Amazon Glacier)
     */
    case REMOTE_SLOW;

    /**
     * Returns the localized string representation of this storage tier
     *
     * @return string Localized name
     * @throws \coding_exception
     */
    public function name(): string {
        return get_string('storage_tier_'.$this->name, 'local_archiving');
    }

    /**
     * Returns the localized help string for this storage tier
     *
     * @return string Localized help string
     * @throws \coding_exception
     */
    public function help(): string {
        return get_string('storage_tier_'.$this->name.'_help', 'local_archiving');
    }

    /**
     * Returns the color class for this storage tier
     *
     * @return string CSS color class name
     */
    public function color(): string {
        return match($this) {
            self::LOCAL => 'success',
            self::REMOTE_FAST => 'warning',
            self::REMOTE_SLOW => 'danger',
            default => 'secondary',
        };
    }

}
