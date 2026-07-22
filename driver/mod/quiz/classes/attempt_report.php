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
 * This file defines the attempt report renderer class
 *
 * @package   archivingmod_quiz
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;

use archivingmod_quiz\type\attempt_filename_variable;
use archivingmod_quiz\type\attempt_report_section;
use local_archiving\storage;
use local_archiving\util\course_util;
use local_archiving\util\report_util;
use mod_quiz\output\attempt_summary_information;
use mod_quiz\quiz_attempt;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

// Required for legacy mod_quiz functions ...
require_once("$CFG->dirroot/mod/quiz/locallib.php");  // @codeCoverageIgnore


/**
 * Quiz attempt report renderer
 *
 * This class handles everything related to getting information for a specific
 * attempt out of a given quiz and rendering it as HTML.
 */
class attempt_report {
    /**
     * Creates a new attempt report renderer
     *
     * @param \stdClass $course Course this attempt renderer is associated with
     * @param \cm_info $cm Course module this renderer is associated with
     * @param \stdClass $quiz Quiz this attempt renderer is associated with
     * @throws \dml_exception If no valid quiz can be found for the given course module
     * @throws \moodle_exception If the given course module is not a quiz
     */
    public function __construct(
        /** @var \stdClass Course this attempt renderer is associated with */
        protected \stdClass $course,
        /** @var \cm_info Course module this attempt renderer is associated with */
        protected \cm_info $cm,
        /** @var \stdClass Quiz this attempt renderer is associated with */
        protected \stdClass $quiz
    ) {
        // Check cm.
        if ($this->cm->course != $this->course->id) {
            throw new \moodle_exception('Course module not part of course');
        }
        if ($this->cm->modname !== 'quiz') {
            throw new \moodle_exception('Invalid course module type');
        }

        if ($this->cm->instance != $this->quiz->id) {
            throw new \moodle_exception('Invalid quiz instance');
        }
    }

    /**
     * Generates a HTML representation of the quiz attempt
     *
     * @param int $attemptid ID of the attempt this report is for
     * @param attempt_report_section[] $sections Array of sections to include in the report
     *
     * @return string HTML DOM of the rendered quiz attempt report
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function generate(int $attemptid, array $sections): string {
        global $DB, $OUTPUT, $PAGE;

        $ctx = \context_module::instance($this->cm->id);
        $renderer = $PAGE->get_renderer('mod_quiz');
        $html = '';

        // Get quiz data and determine state / elapsed time.
        $attemptobj = quiz_create_attempt_handling_errors($attemptid, $this->cm->id);
        $attempt = $attemptobj->get_attempt();

        $quiz = $attemptobj->get_quiz();
        if ($quiz->id != $this->quiz->id) {
            // This should never happen and already be caught by quiz_create_attempt_handling_errors but let's be sure.
            throw new \moodle_exception('Quiz instance from attempt does not match quiz instance from course module');
        }

        $quba = \question_engine::load_questions_usage_by_activity($attemptobj->get_uniqueid());
        $quba->preload_all_step_users();
        $options = \mod_quiz\question\display_options::make_from_quiz($quiz, quiz_attempt_state($quiz, $attempt));
        $options->flags = quiz_get_flag_option($attempt, $ctx);
        $overtime = 0;

        if ($attempt->state == quiz_attempt::FINISHED) {
            if ($timetaken = ($attempt->timefinish - $attempt->timestart)) {
                if ($quiz->timelimit && $timetaken > ($quiz->timelimit + 60)) {
                    $overtime = $timetaken - $quiz->timelimit;
                    $overtime = format_time($overtime);
                }
                $timetaken = format_time($timetaken);
            } else {
                $timetaken = "-";
            }
        } else {
            $timetaken = get_string('unfinished', 'quiz');
        }

        // Section: Quiz header.
        if (in_array(attempt_report_section::HEADER, $sections)) {
            $summaryinfo = new attempt_summary_information();

            // User name and link.
            $attemptuser = $DB->get_record('user', ['id' => $attemptobj->get_userid()]);
            $userpicture = new \user_picture($attemptuser);
            $userpicture->courseid = $attemptobj->get_courseid();
            $userlink = new \action_link(
                new \moodle_url('/user/view.php', ['id' => $attemptuser->id, 'course' => $attemptobj->get_courseid()]),
                fullname($attemptuser, true)
            );
            $summaryinfo->add_item(
                'user',
                get_string('user'),
                $OUTPUT->render($userpicture) . '&nbsp;' . $OUTPUT->render($userlink)
            );

            // User ID number.
            $summaryinfo->add_item(
                'useridnumber',
                get_string('idnumber'),
                $attemptuser->idnumber ?: '<i>' . get_string('none') . '</i>'
            );

            // Quiz metadata.
            $summaryinfo->add_item(
                'course',
                get_string('course'),
                $this->course->fullname . ' (Course-ID: ' . $this->course->id . ')'
            );

            $summaryinfo->add_item(
                'quiz',
                get_string('modulename', 'quiz'),
                $this->quiz->name . ' (Quiz-ID: ' . $this->quiz->id . ')'
            );

            // Timing information.
            $summaryinfo->add_item(
                'startedon',
                get_string('startedon', 'quiz'),
                userdate($attempt->timestart)
            );

            $summaryinfo->add_item(
                'state',
                get_string('attemptstate', 'quiz'),
                quiz_attempt::state_name($attempt->state)
            );

            if ($attempt->state == quiz_attempt::FINISHED) {
                $summaryinfo->add_item('completedon', get_string('completedon', 'quiz'), userdate($attempt->timefinish));
                $summaryinfo->add_item('timetaken', get_string('attemptduration', 'quiz'), $timetaken);
            }

            if (!empty($overtime)) {
                $summaryinfo->add_item('overdue', get_string('overdue', 'quiz'), $overtime);
            }

            // Grades.
            $grade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);
            if (in_array(attempt_report_section::QUIZ_GRADE, $sections)) {
                if (quiz_has_grades($quiz)) {
                    if (is_null($grade)) {
                        $summaryinfo->add_item('grade', get_string('gradenoun'), get_string('notyetgraded', 'quiz'));
                    }

                    if ($attempt->state == quiz_attempt::FINISHED) {
                        // Show raw marks only if they are different from the grade (like on the view page).
                        if ($quiz->grade != $quiz->sumgrades) {
                            $a = new \stdClass();
                            $a->grade = quiz_format_grade($quiz, $attempt->sumgrades);
                            $a->maxgrade = quiz_format_grade($quiz, $quiz->sumgrades);
                            $summaryinfo->add_item('marks', get_string('marks', 'quiz'), get_string('outofshort', 'quiz', $a));
                        }

                        // Now the scaled grade.
                        $a = new \stdClass();
                        $a->grade = \html_writer::tag('b', quiz_format_grade($quiz, $grade));
                        $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                        if ($quiz->grade != 100) {
                            $a->percent = \html_writer::tag('b', format_float($attempt->sumgrades * 100 / $quiz->sumgrades, 0));
                            $formattedgrade = get_string('outofpercent', 'quiz', $a);
                        } else {
                            $formattedgrade = get_string('outof', 'quiz', $a);
                        }
                        $summaryinfo->add_item('grade', get_string('gradenoun'), $formattedgrade);
                    }
                }
            }

            // Any additional summary data from the behaviour.
            foreach ($attemptobj->get_additional_summary_data($options) as $shortname => $data) {
                $summaryinfo->add_item($shortname, $data['title'], $data['content']);
            }

            // Feedback if there is any, and the user is allowed to see it now.
            if (in_array(attempt_report_section::OVERALL_FEEDBACK, $sections)) {
                $feedback = $attemptobj->get_overall_feedback($grade);
                $summaryinfo->add_item(
                    'feedback',
                    get_string('feedback', 'quiz'),
                    $feedback ?: '<i>' . get_string('none') . '</i>'
                );
            }

            // Add export date.
            $summaryinfo->add_item('exportdate', get_string('archived', 'archivingmod_quiz'), userdate(time()));

            $html .= $renderer->review_attempt_summary($summaryinfo, 0);
        }

        // Section: Quiz questions.
        if (in_array(attempt_report_section::QUESTION, $sections)) {
            $slots = $attemptobj->get_slots();
            foreach ($slots as $slot) {
                // Define display options for this question.
                $originalslot = $attemptobj->get_original_slot($slot);
                $number = $attemptobj->get_question_number($originalslot);
                $displayoptions = $attemptobj->get_display_options(true);
                $displayoptions->readonly = true;
                $displayoptions->manualcomment = 1;
                $displayoptions->rightanswer = in_array(attempt_report_section::CORRECT_ANSWER, $sections);
                $displayoptions->feedback = in_array(attempt_report_section::QUESTION_FEEDBACK, $sections);
                $displayoptions->generalfeedback = in_array(attempt_report_section::GENERAL_FEEDBACK, $sections);
                $displayoptions->history = in_array(attempt_report_section::ANSWER_HISTORY, $sections);
                $displayoptions->flags = 1;
                $displayoptions->manualcommentlink = 0;

                // Handle question correctness.
                if (in_array(attempt_report_section::QUESTION_CORRECTNESS, $sections)) {
                    $displayoptions->correctness = \question_display_options::VISIBLE;
                    $displayoptions->numpartscorrect = \question_display_options::VISIBLE;
                } else {
                    $displayoptions->correctness = \question_display_options::HIDDEN;
                    $displayoptions->numpartscorrect = \question_display_options::HIDDEN;
                }

                // Handle question marks display option.
                if (in_array(attempt_report_section::QUESTION_MARKS, $sections)) {
                    $displayoptions->marks = \question_display_options::MARK_AND_MAX;
                } else {
                    $displayoptions->marks = \question_display_options::HIDDEN;
                }

                // Render question as HTML.
                if ($slot != $originalslot) {
                    $attemptobj->get_question_attempt($slot)->set_max_mark(
                        $attemptobj->get_question_attempt($originalslot)->get_max_mark()
                    );
                }
                $html .= $quba->render_question($slot, $displayoptions, $number);
            }
        }

        return $html;
    }

    /**
     * Like generate() but includes a full page HTML DOM including header and
     * footer
     *
     * @param int $attemptid ID of the attempt this report is for
     * @param attempt_report_section[] $sections List of sections to include in the report
     * @param bool $fixrelativeurls If true, all relative URLs will be
     * forcefully mapped to the Moodle base URL
     * @param bool $minimal If true, unneccessary elements (e.g. navbar) are
     * stripped from the generated HTML DOM
     * @param bool $inlineimages If true, all images will be inlined as base64
     * to prevent rendering issues on user side
     *
     * @return string HTML DOM of the rendered quiz attempt report
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \DOMException
     */
    public function generate_full_page(
        int $attemptid,
        array $sections,
        bool $fixrelativeurls = true,
        bool $minimal = true,
        bool $inlineimages = true
    ): string {
        global $CFG, $OUTPUT, $PAGE;

        // Add a quiz archiver specific CSS class to provide a unique CSS selector.
        // This can be used to add additional styling to the quiz report page accessed by the worker,
        // for example by specifying additional (s)css in the theme scss setting in the moodle administration.
        $PAGE->add_body_class('quiz-archiver-report');

        // Build HTML tree.
        $html = "";
        $html .= $OUTPUT->header();
        $html .= self::generate($attemptid, $sections);
        $html .= $OUTPUT->footer();

        // Parse HTML as DOMDocument but supress consistency check warnings.
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        // Patch relative URLs.
        if ($fixrelativeurls) {
            $basenode = $dom->createElement("base");
            $basenode->setAttribute("href", $CFG->wwwroot);
            $dom->getElementsByTagName('head')[0]->appendChild($basenode);
        }

        // Cleanup DOM if desired.
        if ($minimal) {
            // We need to inject custom CSS to hide elements since the DOM generated by.
            // Moodle can be corrupt which causes the PHP DOMDocument parser to die...
            $csshacksnode = $dom->createElement("style", "
                /* Hide everything except the main page region */
                @media print {
                    body * {
                        visibility: hidden;
                    }

                    #region-main,
                    #region-main * {
                        visibility: visible;
                    }
                }

                /* Ensure that parent container (invisible) does not cause additional margings or paddings */
                div#page,
                div.main-inner {
                    margin: 0 !important;
                    padding: 0 !important;
                    height: initial !important;
                }

                div#page-wrapper {
                    height: initial !important;
                }

                /* Prevent STACK input errors breaking the page */
                .stackinputerror {
                    display: none !important;
                }

                /* Force code boxes to reflow to page width */
                pre[class*='language-'] {
                    overflow: visible !important;
                    white-space: pre-wrap !important;
                }

                /* Remove padding from codebox comments to prevent them from drawing over student code */
                code .token.comment {
                    padding: 0.5rem !important;
                }
            ");
            $dom->getElementsByTagName('head')[0]->appendChild($csshacksnode);
        }

        // Convert all local images to base64 if desired.
        if ($inlineimages) {
            $wwwroot = get_config('archivingmod_quiz')->internal_wwwroot ?: null;
            foreach ($dom->getElementsByTagName('img') as $img) {
                if (!report_util::convert_image_to_base64($img, $wwwroot)) {
                    $img->setAttribute('x-debug-inlining-failed', 'true');
                }
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Generates an attempt file- or foldername based on the given pattern and
     * context information
     *
     * @param int $attemptid ID of the attempt
     * @param string $pattern Filename pattern to use
     * @param bool $isfoldername If true, the filename will be treated as a folder name
     * @return string Filename with substituted variables
     * @throws \dml_exception If the attempt or user could not be found in the database
     * @throws \invalid_parameter_exception If the pattern is invalid
     * @throws \coding_exception
     */
    public function generate_attempt_filename(int $attemptid, string $pattern, bool $isfoldername = false): string {
        global $DB;

        // Validate pattern.
        $allowedvariables = attempt_filename_variable::values();
        if ($isfoldername) {
            if (!storage::is_valid_filename_pattern($pattern, $allowedvariables, storage::FOLDERNAME_FORBIDDEN_CHARACTERS)) {
                throw new \invalid_parameter_exception(get_string('error_invalid_attempt_foldername_pattern', 'archivingmod_quiz'));
            }
        } else {
            if (!storage::is_valid_filename_pattern($pattern, $allowedvariables, storage::FILENAME_FORBIDDEN_CHARACTERS)) {
                throw new \invalid_parameter_exception(get_string('error_invalid_attempt_filename_pattern', 'archivingmod_quiz'));
            }
        }

        // Prepare data.
        // We query the DB directly to prevent a full question_attempt object from being created.
        $attemptinfo = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $userinfo = $DB->get_record('user', ['id' => $attemptinfo->userid], '*', MUST_EXIST);
        $usergroups = course_util::get_user_groups($this->course->id, $userinfo->id);
        $data = [
            'courseid' => $this->course->id ?: 0,
            'cmid' => $this->cm->id ?: 0,
            'quizid' => $this->quiz->id ?: 0,
            'attemptid' => $attemptid ?: 0,
            'coursename' => $this->course->fullname ?: 'null',
            'courseshortname' => $this->course->shortname ?: 'null',
            'groupids' => join('-', array_map(fn($group) => $group->id, $usergroups)) ?: 0,
            'groupidnumbers' => join('-', array_map(fn($group) => $group->idnumber ?: 'null', $usergroups)) ?: 0,
            'groupnames' => join('-', array_map(fn($group) => $group->name, $usergroups)) ?: 'nogroup',
            'quizname' => $this->quiz->name ?: 'null',
            'timestamp' => time(),
            'date' => date('Y-m-d'),
            'time' => date('H-i-s'),
            'timestart' => $attemptinfo->timestart ?: 0,
            'timefinish' => $attemptinfo->timefinish ?: 0,
            'username' => $userinfo->username ?: 'null',
            'firstname' => $userinfo->firstname ?: 'null',
            'lastname' => $userinfo->lastname ?: 'null',
            'idnumber' => $userinfo->idnumber ?: 'null',
        ];

        // Substitute variables.
        $filename = $pattern;
        foreach ($data as $key => $value) {
            $filename = preg_replace(
                '/\$\{\s*' . $key . '\s*\}/m',
                substr($value, 0, storage::FILENAME_VARIABLE_MAX_LENGTH),
                $filename
            );
        }

        return $isfoldername ? storage::sanitize_filename($filename) : storage::sanitize_foldername($filename);
    }
}
