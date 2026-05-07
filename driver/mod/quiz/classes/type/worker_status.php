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

// phpcs:disable moodle.Commenting.InlineComment.DocBlock

/**
 * Status codes a webservice function can respond with
 *
 * @package     archivingmod_quiz
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\type;


// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Status values the worker service can report
 */
enum worker_status: string {
    /** @var self Worker service is idle (no jobs present) */
    case IDLE = 'IDLE';

    /** @var self Worker service is actively working on a job */
    case ACTIVE = 'ACTIVE';

    /** @var self Worker service is busy and can not accept new jobs at the moment */
    case BUSY = 'BUSY';

    /** @var self Worker service status is unknown */
    case UNKNOWN = 'UNKNOWN';
}
