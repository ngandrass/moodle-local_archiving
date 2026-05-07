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
 * Sections that can be included in a quiz attempt report
 *
 * @package     archivingmod_quiz
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\type;

use local_archiving\trait\enum_listable;


// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Sections that can be included in a quiz attempt report
 */
enum attempt_report_section: string {
    use enum_listable;

    /** @var string Quiz header containing various metadata */
    case HEADER = 'header';

    /** @var string Overall quiz feedback */
    case OVERALL_FEEDBACK = 'quiz_feedback';

    /** @var string Quiz questions */
    case QUESTION = 'question';

    /** @var string Feedback for individual questions */
    case QUESTION_FEEDBACK = 'question_feedback';

    /** @var string General question feedback */
    case GENERAL_FEEDBACK = 'general_feedback';

    /** @var string Correct answers for questions */
    case CORRECT_ANSWER = 'rightanswer';

    /** @var string History of given user answers to questions */
    case ANSWER_HISTORY = 'history';

    /** @var string File attachments */
    case ATTACHMENTS = 'attachments';

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
            self::OVERALL_FEEDBACK => [self::HEADER],
            self::QUESTION_FEEDBACK => [self::QUESTION],
            self::GENERAL_FEEDBACK => [self::QUESTION],
            self::CORRECT_ANSWER => [self::QUESTION],
            self::ANSWER_HISTORY => [self::QUESTION],
            self::ATTACHMENTS => [self::QUESTION],
            default => [],
        };
    }
}
