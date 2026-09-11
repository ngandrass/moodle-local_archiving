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
 * Types of files that can be attached to an assignment submission
 *
 * @package     archivingmod_assign
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\local\type;


// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Types of files that can be attached to an assignment submission
 */
enum attachment_type: string {
    /** @var string A file that was provided to students prior to assignment submission ("introattachment") */
    case PROVIDED_ASSIGNMENT_FILE = 'assignment';

    /** @var string A file that was submitted by a student as part of their assignment submission ("submission file") */
    case STUDENT_FILE_SUBMISSION = 'submission';

    /** @var string A file that was provided by a grader as feedback to a student */
    case GRADER_FEEDBACK_FILE = 'feedback';

    /** @var string A previously submitted file that was annotated by a grader */
    case GRADER_ANNOTATED_FILE = 'annotation';
}
