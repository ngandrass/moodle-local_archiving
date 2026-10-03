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

/**
 * Privacy provider class for the archivingstore_s3 plugin.
 *
 * @package   archivingstore_s3
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_s3\privacy;

use core_privacy\local\metadata\collection;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Privacy provider for archivingstore_s3
 *
 * This plugin does not store any personal data inside Moodle. It stores all
 * files in a configurable S3 object store that is external to Moodle.
 *
 * @codeCoverageIgnore This is handled by Moodle core tests
 */
class provider implements // phpcs:ignore
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\subplugin_provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('archivingstore_s3_storage', [
            'filename' => 'privacy:metadata:storage:filename',
            'filecontent' => 'privacy:metadata:storage:filecontent',
        ], 'privacy:metadata:storage');

        return $collection;
    }
}
