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
 * This file defines the submission report renderer class
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;

use archivingmod_assign\local\type\submission_filename_variable;
use archivingmod_assign\local\type\submission_report_section;
use core_course\output\activity_icon;
use local_archiving\local\util\course_util;
use local_archiving\local\util\report_util;
use local_archiving\storage;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Assignment submission report renderer
 *
 * This class handles everything related to getting information for a specific
 * submission out of a given assignment and rendering it as HTML.
 */
class submission_report {
    /**
     * Creates a new assignment submission report renderer
     *
     * @param \stdClass $course Course this submission renderer is associated with
     * @param \cm_info $cm Course module this renderer is associated with
     * @param \assign $assignment Assignment this submission renderer is associated with
     * @throws \dml_exception If no valid assignment can be found for the given course module
     * @throws \moodle_exception If the given course module is not an assignment
     */
    public function __construct(
        /** @var \stdClass Course this submission renderer is associated with */
        protected \stdClass $course,
        /** @var \cm_info Course module this submission renderer is associated with */
        protected \cm_info $cm,
        /** @var \assign Assignment this submission renderer is associated with */
        protected \assign $assignment
    ) {
        // Check cm.
        if ($this->cm->course != $this->course->id) {
            throw new \moodle_exception('Course module not part of course');
        }
        if ($this->cm->modname !== 'assign') {
            throw new \moodle_exception('Invalid course module type');
        }

        if ($this->cm->instance != $this->assignment->get_instance()->id) {
            throw new \moodle_exception('Invalid assignment instance');
        }
    }

    /**
     * Generates a HTML representation of the assignment submission
     *
     * @param int $submissionid ID of the submission this report is for
     * @param submission_report_section[] $sections Sections to include in the report
     *
     * @return string HTML DOM of the rendered assignment submission report
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function generate(int $submissionid, array $sections): string {
        global $DB, $OUTPUT;

        // Get and validate submission.
        $submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
        if ($submission->assignment != $this->assignment->get_instance()->id) {
            throw new \moodle_exception('Submission must belong to the assignment');
        }

        // Gather further info.
        /** @var \mod_assign\output\renderer $renderer */
        $renderer = $this->assignment->get_renderer();
        $assigninstance = $this->assignment->get_instance();
        $submittinguser = \core_user::get_user($submission->userid, '*', MUST_EXIST);

        // Force rendered comment areas to become static.
        $_GET['nonjscomment'] = true;

        // Render output.
        return $OUTPUT->render_from_template('archivingmod_assign/submissionreport', [
            'sections' => [
                'header' => in_array(submission_report_section::ASSIGNMENT_HEADER, $sections),
                'instructions' => in_array(submission_report_section::ASSIGNMENT_INSTRUCTIONS, $sections),
            ],
            'course' => [
                'id' => $this->course->id,
                'name' => $this->course->fullname,
            ],
            'assignment' => [
                'id' => $assigninstance->id,
                'title' => $assigninstance->name,
                'description' => format_module_intro('assign', $assigninstance, $this->cm->id),
                'activityinstructions' => $renderer->format_activity_text(
                    assign: $assigninstance,
                    cmid: $this->cm->id,
                ),
                'introattachments' => $this->assignment->render_area_files('mod_assign', ASSIGN_INTROATTACHMENT_FILEAREA, 0),
                'icon' => $OUTPUT->render(activity_icon::from_modname('assign')),
                'dates' => [
                    'opened' => $assigninstance->allowsubmissionsfromdate,
                    'due' => $assigninstance->duedate,
                    'cutoff' => $assigninstance->cutoffdate,
                    'gradingdue' => $assigninstance->gradingduedate,
                ],
            ],
            'submission' => [
                'id' => $submissionid,
                'user' => [
                    'id' => $submittinguser->id,
                    'idnumber' => $submittinguser->idnumber,
                    'picture' => $OUTPUT->render(new \user_picture($submittinguser)),
                    'profilelink' => $OUTPUT->render(new \action_link(
                        new \moodle_url('/user/view.php', ['id' => $submittinguser->id]),
                        fullname($submittinguser, true) . " (#$submittinguser->id)",
                    )),
                ],
                'report' => $this->generate_submission_and_feedback_html($submittinguser, $sections),
            ],
            'archivingdate' => time(),
        ]);
    }

    /**
     * Generates the HTML representation of the submission.
     *
     * This replicates \assign::view_student_summary() but allows fine-grained
     * control over which parts are included, based on the given sections.
     *
     * @param \stdClass $submittinguser User the submission belongs to
     * @param submission_report_section[] $sections Sections to include in the report
     * @return string Rendered HTML
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function generate_submission_and_feedback_html(\stdClass $submittinguser, array $sections): string {
        /** @var \mod_assign\output\renderer $renderer */
        $renderer = $this->assignment->get_renderer();
        $html = '';

        // Submission section.
        if (in_array(submission_report_section::SUBMISSION, $sections)) {
            $submissionrenderable = $this->assignment->get_assign_submission_status_renderable($submittinguser, true);
            $submissionplugins = $submissionrenderable->submissionplugins;

            // Submission status table without plugin contents.
            if (in_array(submission_report_section::SUBMISSION_STATUS, $sections)) {
                $submissionrenderable->submissionplugins = [];
                $html .= $renderer->render($submissionrenderable);
            }

            // Restore submissionplugins for content sections.
            $submissionrenderable->submissionplugins = $submissionplugins;

            if (!in_array(submission_report_section::SUBMISSION_COMMENTS, $sections)) {
                // Drop submission comments plugin if desired.
                $submissionrenderable->submissionplugins = array_values(array_filter(
                    $submissionrenderable->submissionplugins,
                    fn ($plugin) => $plugin->get_type() !== 'comments'
                ));
            }

            // Submission content only, without the surrounding status table.
            $html .= '<h3>' . get_string('submission', 'assign') . '</h3>';
            $html .= $this->render_submission_plugin_content($renderer, $submissionrenderable);
            $html .= '<br>';
        }

        // Feedback section.
        if (in_array(submission_report_section::FEEDBACK, $sections)) {
            $feedbackstatusrenderable = $this->assignment->get_assign_feedback_status_renderable($submittinguser);
            if ($feedbackstatusrenderable) {
                // Drop the feedback comments plugin unless explicitly requested.
                if (!in_array(submission_report_section::FEEDBACK_COMMENTS, $sections)) {
                    $feedbackstatusrenderable->feedbackplugins = array_values(array_filter(
                        $feedbackstatusrenderable->feedbackplugins,
                        fn ($plugin) => $plugin->get_type() !== 'comments'
                    ));
                }

                // Hide the grade (and its breakdown) unless explicitly requested.
                if (!in_array(submission_report_section::GRADE, $sections)) {
                    unset($feedbackstatusrenderable->gradefordisplay);
                    $feedbackstatusrenderable->gradingcontrollergrade = '';
                }

                // Hide the grader identity unless explicitly requested.
                if (!in_array(submission_report_section::GRADING_DETAILS, $sections)) {
                    $feedbackstatusrenderable->grader = null;
                }

                $html .= $renderer->render($feedbackstatusrenderable);
            }
        }

        return $html;
    }

    /**
     * Renders the submission content of all given submission plugins without
     * the surrounding submission status table.
     *
     * This replicates the submission plugin content loop of
     * \mod_assign\output\renderer::render_assign_submission_status() so that
     * submission content can be shown independently of the submission status.
     *
     * @param \mod_assign\output\renderer $renderer Renderer to use
     * @param \mod_assign\output\assign_submission_status $status Submission status
     * renderable to extract plugin content from
     * @return string Rendered HTML
     * @throws \coding_exception
     */
    protected function render_submission_plugin_content(
        \mod_assign\output\renderer $renderer,
        \mod_assign\output\assign_submission_status $status
    ): string {
        $submission = $status->teamsubmission ?: $status->submission;

        // Fail early.
        if (!$submission) {
            return '';
        }

        if ($status->teamsubmission && $status->submissiongroup == false && $status->preventsubmissionnotingroup) {
            return '';
        }

        // Build table with all submission plugin contents.
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable table table-striped table-bordered table-hover';

        foreach ($status->submissionplugins as $plugin) {
            $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
            if ($plugin->is_enabled() && $plugin->is_visible() && $plugin->has_user_summary() && $pluginshowsummary) {
                $table->data[] = new \html_table_row([
                    new \html_table_cell($plugin->get_name()),
                    new \html_table_cell($renderer->render(new \assign_submission_plugin_submission(
                        $plugin,
                        $submission,
                        \assign_submission_plugin_submission::SUMMARY,
                        $status->coursemoduleid,
                        $status->returnaction,
                        $status->returnparams
                    ))),
                ]);
            }
        }

        if (empty($table->data)) {
            return '';
        }

        return \html_writer::table($table);
    }

    /**
     * Like generate() but includes a full page HTML DOM including header and
     * footer
     *
     * @param int $submissionid ID of the submission this report is for
     * @param submission_report_section[] $sections Sections to include in the report
     * @param bool $fixrelativeurls If true, all relative URLs will be
     * forcefully mapped to the Moodle base URL
     * @param bool $minimal If true, unneccessary elements (e.g. navbar) are
     * stripped from the generated HTML DOM
     * @param bool $inlineimages If true, all images will be inlined as base64
     * to prevent rendering issues on user side
     *
     * @return string HTML DOM of the rendered assignment submission report
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \DOMException
     */
    public function generate_full_page(
        int $submissionid,
        array $sections,
        bool $fixrelativeurls = true,
        bool $minimal = true,
        bool $inlineimages = true
    ): string {
        global $CFG, $OUTPUT, $PAGE;

        // Add a assignment archiver specific CSS class to provide a unique CSS selector.
        // This can be used to add additional styling to the submission report page accessed by the worker,
        // for example by specifying additional (s)css in the theme scss setting in the moodle administration.
        $PAGE->add_body_class('assign-archiver-report');

        // Build HTML tree.
        $html = "";
        $html .= $OUTPUT->header();
        $html .= self::generate($submissionid, $sections);
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

                /* Remove add comment section in submission summary */
                .commentscontainer > h3,
                .commentscontainer > form {
                    display: none !important;
                }

                /* Remove 'View annotated PDF' link on editpdf feedbacks */
                .feedback div[class*='summary_assignfeedback_editpdf_'] > div > a[id*='random'] {
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
            $wwwroot = get_config('archivingmod_assign')->internal_wwwroot ?: null;
            foreach ($dom->getElementsByTagName('img') as $img) {
                if (!report_util::convert_image_to_base64($img, $wwwroot)) {
                    $img->setAttribute('x-debug-inlining-failed', 'true');
                }
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Generates a submission file- or foldername based on the given pattern and
     * context information
     *
     * @param int $submissionid ID of the submission
     * @param string $pattern Filename pattern to use
     * @param bool $isfoldername If true, the filename will be treated as a folder name
     * @return string Filename with substituted variables
     * @throws \dml_exception If the submission or user could not be found in the database
     * @throws \invalid_parameter_exception If the pattern is invalid
     * @throws \coding_exception
     */
    public function generate_submission_filename(int $submissionid, string $pattern, bool $isfoldername = false): string {
        global $DB;

        // Validate pattern.
        $allowedvariables = submission_filename_variable::values();
        if ($isfoldername) {
            if (!storage::is_valid_filename_pattern($pattern, $allowedvariables, storage::FOLDERNAME_FORBIDDEN_CHARACTERS)) {
                throw new \invalid_parameter_exception(
                    get_string('error_invalid_submission_foldername_pattern', 'archivingmod_assign')
                );
            }
        } else {
            if (!storage::is_valid_filename_pattern($pattern, $allowedvariables, storage::FILENAME_FORBIDDEN_CHARACTERS)) {
                throw new \invalid_parameter_exception(
                    get_string('error_invalid_submission_filename_pattern', 'archivingmod_assign')
                );
            }
        }

        // Prepare data.
        $submissioninfo = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
        $userinfo = $DB->get_record('user', ['id' => $submissioninfo->userid], '*', MUST_EXIST);
        $usergroups = course_util::get_user_groups($this->course->id, $userinfo->id);
        $assigninstance = $this->assignment->get_instance();
        $data = [
            'assignmentid' => $assigninstance->id ?: 0,
            'assignmenttitle' => $assigninstance->name ?: 'null',
            'attemptnumber' => $submissioninfo->attemptnumber ?: 0,
            'cmid' => $this->cm->id ?: 0,
            'courseid' => $this->course->id ?: 0,
            'coursename' => $this->course->fullname ?: 'null',
            'courseshortname' => $this->course->shortname ?: 'null',
            'date' => date('Y-m-d'),
            'firstname' => $userinfo->firstname ?: 'null',
            'groupidnumbers' => join('-', array_map(fn($group) => $group->idnumber ?: 'null', $usergroups)) ?: 0,
            'groupids' => join('-', array_map(fn($group) => $group->id, $usergroups)) ?: 0,
            'groupnames' => join('-', array_map(fn($group) => $group->name, $usergroups)) ?: 'nogroup',
            'idnumber' => $userinfo->idnumber ?: 'null',
            'lastname' => $userinfo->lastname ?: 'null',
            'submissionid' => $submissionid ?: 0,
            'time' => date('H-i-s'),
            'timecreated' => $submissioninfo->timecreated ?: 0,
            'timemodified' => $submissioninfo->timemodified ?: 0,
            'timestamp' => time(),
            'timestart' => $submissioninfo->timestarted ?: 0,
            'username' => $userinfo->username ?: 'null',
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

        return $isfoldername ? storage::sanitize_foldername($filename) : storage::sanitize_filename($filename);
    }
}
