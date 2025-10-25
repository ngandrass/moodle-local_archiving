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

use core\exception\moodle_exception;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Custom admin setting that checks whether webservices are properly enabled and
 * provides shortcuts to enable them if needed.
 *
 * @codeCoverageIgnore
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_webservice_enabler extends \admin_setting_description {
    /**
     * Returns an HTML string
     *
     * @param string $data
     * @param string $query
     * @return string Returns an HTML string
     * @throws moodle_exception
     * @throws \dml_exception
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        return $OUTPUT->render_from_template('local_archiving/components/setting_webservice_enabler', [
            'title' => $this->visiblename,
            'description' => $this->description,
            'webservicesenabled' => get_config('core', 'enablewebservices') == true,
            'webserviceprotocolrestenabled' => stripos(get_config('core', 'webserviceprotocols'), 'rest') !== false,
            'webserviceenableurl' => new \moodle_url('/admin/search.php', ['query' => 'enablewebservices']),
            'webserviceprotocolenableurl' => new \moodle_url('/admin/settings.php', ['section' => 'webserviceprotocols']),
        ]);
    }
}
