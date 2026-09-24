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
 * Status values for an on-demand file retrieval from a (remote) storage
 *
 * @package     local_archiving
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\local\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Status values for an on-demand file retrieval from a (remote) storage
 */
enum file_fetch_status: string {
    /** @var string The retrieval task has been queued but has not started running yet */
    case QUEUED = 'queued';

    /** @var string The retrieval task is currently transferring data */
    case FETCHING = 'fetching';

    /** @var string The retrieval completed successfully */
    case COMPLETE = 'complete';

    /** @var string The retrieval failed */
    case FAILED = 'failed';
}
