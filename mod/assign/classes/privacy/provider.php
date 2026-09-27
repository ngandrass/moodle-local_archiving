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
 * Privacy provider class for this plugin.
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\privacy;

use core_privacy\local\metadata\collection;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Privacy provider for archivingmod_assign
 *
 * This plugin does not store any personal data itself. All data that passes
 * through it is temporary and passed to the external worker service. Created
 * artifacts are stored by the core plugin and are not owned by this plugin.
 *
 * @codeCoverageIgnore This is handled by Moodle core tests
 */
class provider implements // phpcs:ignore
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\subplugin_provider {
    /**
     * Returns meta data about this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('archivingmod_assign_worker', [
            'submissionid' => 'privacy:metadata:worker:submissionid',
            'submissionreport' => 'privacy:metadata:worker:submissionreport',
            'assignment' => 'privacy:metadata:worker:assignment',
            'submission' => 'privacy:metadata:worker:submission',
            'feedback' => 'privacy:metadata:worker:feedback',
            'annotation' => 'privacy:metadata:worker:annotation',
        ], 'privacy:metadata:worker');

        return $collection;
    }
}
