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
 * Event-based archiving trigger plugin
 *
 * @package     archivingtrigger_event
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingtrigger_event;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use core\event\base;
use local_archiving\archive_job;
use local_archiving\local\driver\driver_factory;
use local_archiving\local\type\archive_job_fingerprint;
use local_archiving\local\util\course_util;
use local_archiving\local\util\plugin_util;


/**
 * Event-based archiving trigger plugin
 */
class archivingtrigger extends \local_archiving\local\driver\archivingtrigger {
    /**
     * Builds a list of all available events that can be configured to trigger archiving.
     *
     * The list will only contain events from activity archiving drivers that
     * are enabled and expose at least one event.
     *
     * @return array{string, \core\event\base[]} A list of events grouped by
     * activity archiving driver name.
     * @throws \coding_exception
     */
    public static function get_eventlist(): array {
        $res = [];

        foreach (plugin_util::get_activity_archiving_drivers() as $name => $metadata) {
            if ($metadata['enabled'] && !empty($metadata['events'])) {
                $res[$name] = $metadata['events'];
            }
        }

        return $res;
    }

    /**
     * Returns a mapping of enabled events to their corresponding activity archiving driver.
     *
     * @return array<string, string> A mapping of event names to activity archiving driver names.
     * @throws \dml_exception
     */
    public static function get_enabled_events_mapping(): array {
        $res = [];

        $config = get_config('archivingtrigger_event');
        foreach ($config as $key => $value) {
            if (str_starts_with($key, 'sensitivity_') && !empty($value)) {
                $drivername = substr($key, strlen('sensitivity_'));
                foreach (explode(',', $value) as $eventname) {
                    $res[$eventname] = $drivername;
                }
            }
        }

        return $res;
    }

    /**
     * Handles an event and triggers archiving if necessary.
     *
     * This method is called by the Moodle event system.
     *
     * @param base $event The event to handle.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \JsonException On fingerprint calculation problems
     */
    public static function handle_event(\core\event\base $event): void {
        $eventname = $event->eventname;

        // We need to manually ensure that this trigger is enabled because Moodle does dispatch
        // events to all registered observers regardless of their enabled state.
        if (!get_config('archivingtrigger_event', 'enabled')) {
            return;
        }

        // Ignore events that we are not sensitive to.
        $eventmap = self::get_enabled_events_mapping();
        if (!isset($eventmap[$eventname])) {
            return;
        }

        // Validate event context.
        $ctx = $event->get_context();
        if (!$ctx instanceof \context_module) {
            return;
        }

        // Only archive activities inside whitelisted course categories.
        if (!course_util::archiving_enabled_for_course($ctx->get_course_context()->instanceid)) {
            return;
        }

        // Ensure that the activity can be archived.
        $drivername = $eventmap[$eventname];
        $driver = driver_factory::activity_archiving_driver($drivername, $ctx);

        if (!$driver->is_enabled() || !$driver::is_ready() || !$driver->can_be_archived()) {
            return;
        }

        // Resolve targetted archive objects for event.
        $refids = $driver->get_refids_for_event($event);
        if ($refids !== null && empty($refids)) {
            return;
        }

        // Build archive job settings object and determine fingerprint.
        [$course, $cm] = get_course_and_cm_from_cmid($ctx->instanceid);
        $form = $driver->get_job_create_form($drivername, $cm);
        $jobsettings = $form->export_raw_data();
        $fingerprint = archive_job_fingerprint::generate($course->id, $cm->id, $jobsettings, $refids);

        // Do not create a new job if an identical one is still pending.
        if (archive_job::get_incomplete_job_count_for_fingerprint($fingerprint) > 0) {
            return;
        }

        // Trigger archive job.
        $job = archive_job::create($cm->context, get_admin()->id, 'event', $jobsettings, refids: $refids);
        $job->get_logger()->info(
            'This job was created from the following Moodle event: ' . $event::get_name() . " ({$eventname})"
        );
        $job->enqueue();
    }
}
