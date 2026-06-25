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
 * Defines the job creation form
 *
 * @package    archivingmod_quiz
 * @copyright  2025 Niels Gandraß <niels@gandrass.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz\form;

use archivingmod_quiz\type\attempt_filename_variable;
use archivingmod_quiz\type\attempt_report_section;
use local_archiving\storage;
use local_archiving\type\paper_format;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->dirroot . '/lib/formslib.php'); // @codeCoverageIgnore


/**
 * Form to initiate a new quiz archive job
 */
class job_create_form extends \local_archiving\form\job_create_form {
    #[\Override]
    protected function definition_base_settings(): void {
        // Options: Attempts.
        $this->_form->addElement(
            'advcheckbox',
            'export_attempts',
            get_string('attempts', 'mod_quiz'),
            get_string('task_export_attempts', 'archivingmod_quiz'),
            ['disabled' => 'disabled'],
            ['1', '1']
        );
        $this->_form->addHelpButton('export_attempts', 'task_export_attempts', 'archivingmod_quiz');
        $this->_form->setDefault('export_attempts', true);

        foreach (attempt_report_section::cases() as $section) {
            $this->_form->addElement(
                'advcheckbox',
                'report_section_' . $section->value,
                '&nbsp;',
                get_string('task_report_section_' . $section->value, 'archivingmod_quiz'),
                $this->config->handler->{'job_preset_report_section_' . $section->value . '_locked'} ? 'disabled' : null
            );
            $this->_form->addHelpButton(
                'report_section_' . $section->value,
                'task_report_section_' . $section->value,
                'archivingmod_quiz'
            );
            $this->_form->setDefault(
                'report_section_' . $section->value,
                $this->config->handler->{'job_preset_report_section_' . $section->value}
            );

            if (!$this->config->handler->{'job_preset_report_section_' . $section->value . '_locked'}) {
                foreach ($section->dependencies() as $dependency) {
                    $this->_form->disabledIf(
                        'report_section_' . $section->value,
                        'report_section_' . $dependency->value,
                        'notchecked'
                    );
                }
            }
        }

        parent::definition_base_settings();
    }

    #[\Override]
    protected function definition_advanced_settings(): void {
        // Advanced options: Paper format.
        $this->_form->addElement(
            'select',
            'paper_format',
            get_string('task_paper_format', 'archivingmod_quiz'),
            array_combine(paper_format::values(), paper_format::values()),
            $this->config->handler->job_preset_paper_format_locked ? 'disabled' : null
        );
        $this->_form->addHelpButton('paper_format', 'task_paper_format', 'archivingmod_quiz');
        $this->_form->setDefault('paper_format', $this->config->handler->job_preset_paper_format);

        // Advanced options: Keep HTML files.
        $this->_form->addElement(
            'advcheckbox',
            'keep_html_files',
            get_string('task_keep_html_files', 'archivingmod_quiz'),
            get_string('task_keep_html_files_desc', 'archivingmod_quiz'),
            $this->config->handler->job_preset_keep_html_files_locked ? 'disabled' : null
        );
        $this->_form->addHelpButton('keep_html_files', 'task_keep_html_files', 'archivingmod_quiz');
        $this->_form->setDefault('keep_html_files', $this->config->handler->job_preset_keep_html_files);

        // Advanced options: Image optimization.
        $this->_form->addElement(
            'advcheckbox',
            'image_optimize',
            get_string('task_image_optimize', 'archivingmod_quiz'),
            get_string('enable'),
            $this->config->handler->job_preset_image_optimize_locked ? 'disabled' : null,
            ['0', '1']
        );
        $this->_form->addHelpButton('image_optimize', 'task_image_optimize', 'archivingmod_quiz');
        $this->_form->setDefault('image_optimize', $this->config->handler->job_preset_image_optimize);

        // Image max width/height fields.
        $mformgroup = [];
        $mformgroupfieldseperator = 'x';
        if ($this->config->handler->job_preset_image_optimize_width_locked) {
            $mformgroup[] = $this->_form->createElement(
                'static',
                'image_optimize_width_static',
                '',
                $this->config->handler->job_preset_image_optimize_width
            );
            $this->_form->addElement(
                'hidden',
                'image_optimize_width',
                $this->config->handler->job_preset_image_optimize_width
            );
        } else {
            $mformgroup[] = $this->_form->createElement(
                'text',
                'image_optimize_width',
                get_string('task_image_optimize_width', 'archivingmod_quiz'),
                ['size' => 4]
            );
            $this->_form->setDefault('image_optimize_width', $this->config->handler->job_preset_image_optimize_width);
        }
        $this->_form->setType('image_optimize_width', PARAM_INT);

        if ($this->config->handler->job_preset_image_optimize_height_locked) {
            $mformgroup[] = $this->_form->createElement(
                'static',
                'optimize_height_static',
                '',
                $this->config->handler->job_preset_image_optimize_height
            );
            $this->_form->addElement(
                'hidden',
                'image_optimize_height',
                $this->config->handler->job_preset_image_optimize_height
            );
        } else {
            $mformgroup[] = $this->_form->createElement(
                'text',
                'image_optimize_height',
                get_string('task_image_optimize_height', 'archivingmod_quiz'),
                ['size' => 4]
            );
            $this->_form->setDefault('image_optimize_height', $this->config->handler->job_preset_image_optimize_height);
            $mformgroupfieldseperator .= '&nbsp;';
        }
        $this->_form->setType('image_optimize_height', PARAM_INT);

        $mformgroup[] = $this->_form->createElement('static', 'image_optimize_px', '', 'px');

        $this->_form->addGroup(
            $mformgroup,
            'image_optimize_group',
            get_string('task_image_optimize_group', 'archivingmod_quiz'),
            [$mformgroupfieldseperator, ''],
            false
        );
        $this->_form->addHelpButton('image_optimize_group', 'task_image_optimize_group', 'archivingmod_quiz');
        $this->_form->hideIf('image_optimize_group', 'image_optimize', 'notchecked');

        // Image quality field.
        $mformgroup = [];
        if ($this->config->handler->job_preset_image_optimize_quality_locked) {
            $mformgroup[] = $this->_form->createElement(
                'static',
                'image_optimize_quality_static',
                '',
                $this->config->handler->job_preset_image_optimize_quality
            );
            $this->_form->addElement(
                'hidden',
                'image_optimize_quality',
                $this->config->handler->job_preset_image_optimize_quality
            );
        } else {
            $mformgroup[] = $this->_form->createElement(
                'text',
                'image_optimize_quality',
                get_string('task_image_optimize_quality', 'archivingmod_quiz'),
                ['size' => 2]
            );
            $this->_form->setDefault(
                'image_optimize_quality',
                $this->config->handler->job_preset_image_optimize_quality
            );
        }
        $this->_form->setType('image_optimize_quality', PARAM_INT);

        $mformgroup[] = $this->_form->createElement('static', 'image_optimize_quality_percent', '', '%');
        $this->_form->addGroup(
            $mformgroup,
            'image_optimize_quality_group',
            get_string('task_image_optimize_quality', 'archivingmod_quiz'),
            '',
            false
        );
        $this->_form->addHelpButton(
            'image_optimize_quality_group',
            'task_image_optimize_quality',
            'archivingmod_quiz'
        );
        $this->_form->hideIf('image_optimize_quality_group', 'image_optimize', 'notchecked');

        // Advanced options: Attempt folder name pattern.
        $this->_form->addElement(
            'text',
            'attempt_foldername_pattern',
            get_string('task_attempt_foldername_pattern', 'archivingmod_quiz'),
            $this->config->handler->job_preset_attempt_foldername_pattern_locked ? 'disabled' : null
        );
        $this->_form->addHelpButton(
            'attempt_foldername_pattern',
            'task_attempt_foldername_pattern',
            'archivingmod_quiz',
            '',
            false,
            [
                'variables' => array_reduce(
                    attempt_filename_variable::values(),
                    fn($res, $varname) => $res . "<li>" .
                        "<code>\${" . $varname . "}</code>: " .
                        get_string('task_attempt_filename_pattern_variable_' . $varname, 'archivingmod_quiz') .
                        "</li>",
                    ""
                ),
                'forbiddenchars' => htmlspecialchars(implode('', storage::FOLDERNAME_FORBIDDEN_CHARACTERS)),
            ]
        );
        $this->_form->setType('attempt_foldername_pattern', PARAM_TEXT);
        $this->_form->setDefault('attempt_foldername_pattern', $this->config->handler->job_preset_attempt_foldername_pattern);
        $this->_form->addRule('attempt_foldername_pattern', null, 'maxlength', 255, 'client');

        // Advanced options: Attempts filename pattern.
        $this->_form->addElement(
            'text',
            'attempt_filename_pattern',
            get_string('task_attempt_filename_pattern', 'archivingmod_quiz'),
            $this->config->handler->job_preset_attempt_filename_pattern_locked ? 'disabled' : null
        );
        $this->_form->addHelpButton(
            'attempt_filename_pattern',
            'task_attempt_filename_pattern',
            'archivingmod_quiz',
            '',
            false,
            [
                'variables' => array_reduce(
                    attempt_filename_variable::values(),
                    fn($res, $varname) => $res . "<li>" .
                        "<code>\${" . $varname . "}</code>: " .
                        get_string('task_attempt_filename_pattern_variable_' . $varname, 'archivingmod_quiz') .
                        "</li>",
                    ""
                ),
                'forbiddenchars' => implode('', storage::FILENAME_FORBIDDEN_CHARACTERS),
            ]
        );
        $this->_form->setType('attempt_filename_pattern', PARAM_TEXT);
        $this->_form->setDefault('attempt_filename_pattern', $this->config->handler->job_preset_attempt_filename_pattern);
        $this->_form->addRule('attempt_filename_pattern', null, 'maxlength', 255, 'client');

        parent::definition_advanced_settings();
    }

    /**
     * Server-side form data validation
     *
     * @param mixed $data Submitted form data
     * @param mixed $files Uploaed files
     * @return array Associative array with error messages for invalid fields
     * @throws \coding_exception
     */
    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (
            !storage::is_valid_filename_pattern(
                $data['attempt_foldername_pattern'],
                attempt_filename_variable::values(),
                storage::FOLDERNAME_FORBIDDEN_CHARACTERS
            )
        ) {
            $errors['attempt_foldername_pattern'] = get_string('error_invalid_attempt_foldername_pattern', 'archivingmod_quiz');
        }

        if (
            !storage::is_valid_filename_pattern(
                $data['attempt_filename_pattern'],
                attempt_filename_variable::values(),
                storage::FILENAME_FORBIDDEN_CHARACTERS
            )
        ) {
            $errors['attempt_filename_pattern'] = get_string('error_invalid_attempt_filename_pattern', 'archivingmod_quiz');
        }

        return $errors;
    }

    /**
     * Returns the data submitted by the user but forces all locked fields to
     * their preset values
     *
     * @return \stdClass Cleared, submitted form data
     * @throws \dml_exception
     */
    #[\Override]
    public function get_data(): \stdClass {
        $data = parent::get_data();

        // Force locked fields to their preset values.
        foreach ($this->config->handler as $key => $value) {
            if (str_starts_with($key, 'job_preset_') && strrpos($key, '_locked') === strlen($key) - 7) {
                if ($value) {
                    $data->{substr($key, 11, -7)} = $this->config->handler->{substr($key, 0, -7)};
                }
            }
        }

        return $data;
    }
}
