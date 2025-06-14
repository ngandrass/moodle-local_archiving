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
 * This file defines the tsp_manager class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use local_archiving\type\db_table;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Manages all Time-Stamp Protocol (TSP) related tasks.
 */
class tsp_manager {

    /** @var file_handle Handle of the file this TSP manager instance is for */
    protected file_handle $filehandle;

    /** @var \stdClass Moodle config object for this plugin */
    protected \stdClass $config;

    /**
     * Creates a new tsp_manager instance.
     *
     * @throws \dml_exception If the plugin config could not be loaded
     */
    public function __construct(file_handle $filehandle) {
        $this->config = get_config('local_archiving');
        $this->filehandle = $filehandle;
    }

    /**
     * Determines if automatic TSP signing is enabled globally.
     *
     * @return bool True if automatic TSP signing is enabled, false otherwise
     * @throws \dml_exception
     */
    public static function is_automatic_tsp_signing_enabled(): bool {
        $config = get_config('local_archiving');
        return $config->tsp_enable && $config->tsp_automatic_signing;
    }

    /**
     * Provides a tsp_client instance for this tsp_manager.
     *
     * @return tsp_client A fresh Timestamp-Protocol client instance
     */
    protected function get_tsp_client(): tsp_client {
        return new tsp_client($this->config->tsp_server_url);
    }

    /**
     * Checks if the associated file wants an automatically generated TSP
     * timestamp.
     *
     * @return bool True if the associated file wants a TSP timestamp to be
     *              automatically generated, false otherwise
     * @throws \dml_exception On database error
     */
    public function wants_tsp_timestamp(): bool {
        if ($this->config->tsp_enable &&
            $this->config->tsp_automatic_signing &&
            $this->has_tsp_timestamp() === false
        ) {
                return true;
        }

        return false;
    }

    /**
     * Checks if the associated file already has a TSP timestamp.
     *
     * @return bool True if the associated file already has a TSP timestamp
     * @throws \dml_exception On database error
     */
    public function has_tsp_timestamp(): bool {
        global $DB;

        $numtsprecords = $DB->count_records(db_table::TSP->value, [
            'filehandleid' => $this->filehandle->id,
        ]);

        return $numtsprecords > 0;
    }

    /**
     * Returns the TSP data for the associated file.
     *
     * @return ?\stdClass TSP data for the associated file or null if no TSP
     * data was found
     * @throws \dml_exception On database error
     */
    public function get_tsp_data(): ?\stdClass {
        global $DB;

        $tspdata = $DB->get_record(db_table::TSP->value, [
            'filehandleid' => $this->filehandle->id,
        ]);

        return ($tspdata !== false) ? (object) [
            'server' => $tspdata->server,
            'timecreated' => $tspdata->timecreated,
            'query' => $tspdata->timestampquery,
            'reply' => $tspdata->timestampreply,
        ] : null;
    }

    /**
     * Deletes all stored TSP data for the associated file.
     *
     * @return void
     * @throws \dml_exception On database error
     */
    public function delete_tsp_data(): void {
        global $DB;

        $DB->delete_records(db_table::TSP->value, [
            'filehandleid' => $this->filehandle->id,
        ]);
    }

    /**
     * Issues a TSP timestamp for the associated file
     *
     * @return void
     * @throws \dml_exception On database error
     * @throws \Exception On TSP error
     * @throws \RuntimeException If the associated file has no valid artifact
     */
    public function timestamp(): void {
        global $DB;

        // Check if TSP signing globally is enabled.
        if (!$this->config->tsp_enable) {
            throw new \Exception(get_string('artifact_signing_failed_tsp_disabled', 'local_archiving'));
        }

        // Issue TSP timestamp.
        $tspclient = $this->get_tsp_client();
        $tspdata = $tspclient->sign($this->filehandle->sha256sum);

        // Store TSP data.
        $DB->insert_record(db_table::TSP->value, [
            'filehandleid' => $this->filehandle->id,
            'timecreated' => time(),
            'server' => $tspclient->get_serverurl(),
            'timestampquery' => $tspdata['query'],
            'timestampreply' => $tspdata['reply'],
        ]);
    }

}
