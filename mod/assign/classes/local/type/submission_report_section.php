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
 * Sections that can be included in a submission report
 *
 * @package     archivingmod_assign
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign\local\type;

use local_archiving\local\trait\enum_listable;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Sections that can be included in a submission report
 */
enum submission_report_section: string {
    use enum_listable;

    /** @var string Assignment metadata header section */
    case ASSIGNMENT_HEADER = 'header';

    /** @var string Assignment instructions */
    case ASSIGNMENT_INSTRUCTIONS = 'instructions';

    /** @var string Submission section */
    case SUBMISSION = 'submission';

    /** @var string Status of the submission and grading */
    case SUBMISSION_STATUS = 'submissionstatus';

    /** @var string Comments about the submission */
    case SUBMISSION_COMMENTS = 'submissioncomments';

    /** @var string Feedback section */
    case FEEDBACK = 'feedback';

    /** @var string Comments about the feedback */
    case FEEDBACK_COMMENTS = 'feedbackcomments';

    /** @var string Assigned grade */
    case GRADE = 'grade';

    /** @var string Details about the grading (grader and time) */
    case GRADING_DETAILS = 'gradedetails';

    /**
     * Retrieves the list of dependencies for this section.
     *
     * If this section depends on other sections, this section can only be
     * active if all of its dependencies are also active.
     *
     * @return self[] List of sections this section depends on
     */
    public function dependencies(): array {
        return match ($this) {
            self::SUBMISSION_STATUS => [self::SUBMISSION],
            self::SUBMISSION_COMMENTS => [self::SUBMISSION],
            self::FEEDBACK_COMMENTS => [self::FEEDBACK],
            self::GRADE => [self::FEEDBACK],
            self::GRADING_DETAILS => [self::FEEDBACK, self::GRADE],
            default => [],
        };
    }
}
