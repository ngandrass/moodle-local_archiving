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
 * Event observers for the archivingtrigger_event plugin.
 *
 * @package     archivingtrigger_event
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

$observers = [];
try {
    foreach (\archivingtrigger_event\archivingtrigger::get_eventlist() as $archivingmod => $eventlist) {
        foreach ($eventlist as $eventclass) {
            $observers[] = [
                'eventname' => $eventclass,
                'callback' => '\archivingtrigger_event\archivingtrigger::handle_event',
                'priority' => 0,
                'internal' => false,
            ];
        }
    }
} catch (\Throwable $e) { // phpcs:ignore
    // Archivingmod plugins may not be usable during install/upgrade. The observer cache is flushed
    // at the end of install/upgrade, so this will get re-evaluated.
}
