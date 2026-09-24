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
 * Custom admin setting that shows the live S3 connection/configuration status
 *
 * @package     archivingstore_s3
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingstore_s3\local\admin\setting;

use archivingstore_s3\archivingstore;
use archivingstore_s3\local\s3_client;
use archivingstore_s3\local\type\connection_status;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->libdir . '/adminlib.php'); // @codeCoverageIgnore


/**
 * Custom admin setting that shows the live S3 connection/configuration status
 */
class admin_setting_s3_connection_status extends \admin_setting_description {
    /**
     * Returns the HTML representation of the setting element
     *
     * @param string $data Unused here
     * @param string $query Unused here
     * @return string Returns an HTML string
     * @throws \moodle_exception
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        // Check if this plugin is even fully configured.
        $configured = archivingstore::is_configured();
        $rows = [
            ['label' => get_string('status_configured', 'archivingstore_s3'), 'ok' => $configured, 'detail' => null],
        ];

        if (!$configured) {
            // Plugin is unconfigured.
            $rows[] = [
                'label' => get_string('status_reachable', 'archivingstore_s3'),
                'ok' => false,
                'detail' => '<i>' . get_string('status_check_skipped', 'archivingstore_s3') . '</i>',
            ];
            $rows[] = [
                'label' => get_string('status_accessible', 'archivingstore_s3'),
                'ok' => false,
                'detail' => '<i>' . get_string('status_check_skipped', 'archivingstore_s3') . '</i>',
            ];
        } else {
            // Plugin is fully configured. Test S3 connectivity.
            $result = s3_client::instance()->check_connection();
            $reachable = ($result->status !== connection_status::CONNECTION_ERROR);

            $rows[] = [
                'label' => get_string('status_reachable', 'archivingstore_s3'),
                'ok' => $reachable,
                'detail' => $reachable ? null : $result->message,
            ];
            $rows[] = [
                'label' => get_string('status_accessible', 'archivingstore_s3'),
                'ok' => $result->is_ok(),
                'detail' => $result->is_ok() ? null : $result->message,
            ];
        }

        // Render output from template.
        return $OUTPUT->render_from_template('archivingstore_s3/setting_connection_status', [
            'title' => $this->visiblename,
            'description' => $this->description,
            'rows' => $rows,
        ]);
    }
}
