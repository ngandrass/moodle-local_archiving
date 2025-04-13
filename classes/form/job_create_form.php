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
 * Main form for creating a new archiving job. This will include archivingmod-
 * specific settings.
 *
 * @package    local_archiving
 * @copyright  2025 Niels Gandraß <niels@gandrass.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\form;

use local_archiving\storage;
use local_archiving\util\time_util;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->dirroot.'/lib/formslib.php'); // @codeCoverageIgnore


/**
 * Form to initiate an activity archiving job
 */
class job_create_form extends \moodleform {

    /** @var string Frankenstyle name of the archivingmod plugin that will handle this job */
    protected string $handler;

    /** @var \cm_info Info object for the targeted course module */
    protected \cm_info $cminfo;

    /** @var \stdClass Moodle admin settings values for 'core' (local_archiving) and the respective 'handler' (archivingmod_*) */
    protected \stdClass $config;

    /**
     * Creates a new form instance
     *
     * @param string $handler Name of the archivingmod sub-plugin that handles this job
     * @param \cm_info $cminfo Info object for the targeted course module
     * @throws \dml_exception
     */
    public function __construct(string $handler, \cm_info $cminfo) {
        global $PAGE;

        $this->handler = $handler;
        $this->cminfo = $cminfo;
        $this->config = (object) [
            'core' => get_config('local_archiving'),
            'handler' => get_config("archivingmod_{$this->handler}"),
        ];

        // Superclass constructor must be called after members are set to have $this->config populated.
        // Pass $PAGE-url explicitly to implicitly carry over existing GET params ;).
        parent::__construct($PAGE->url);
    }

    /**
     * Full form definition
     *
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public function definition() {
        $this->definition_header();

        // Basic settings.
        $this->_form->addElement('header', 'header_settings', get_string('settings'));
        $this->_form->setExpanded('header_settings', true);
        $this->definition_base_settings();

        // Advanced settings.
        $this->_form->addElement('header', 'header_advanced_settings', get_string('advancedsettings'));
        $this->_form->setExpanded('header_advanced_settings', false);
        $this->definition_advanced_settings();

        $this->definition_footer();
    }

    /**
     * Defines header elements in form
     *
     * @return void
     * @throws \coding_exception
     */
    protected function definition_header(): void {
        $this->_form->addElement('html', '<h1>'.get_string(
            'job_create_form_header_typed',
            'local_archiving',
            get_string('pluginname', "mod_{$this->handler}"),
        ).'</h1>');
        $this->_form->addElement('html', '<p>'.get_string('job_create_form_header_desc', 'local_archiving').'</p>');

        $this->_form->addElement(
            'static',
            'cm_modname',
            get_string('activitytype', 'local_archiving'),
            get_string('pluginname', "mod_{$this->cminfo->modname}")
        );
        $this->_form->addElement(
            'static',
            'cm_name',
            get_string('name'),
            "<p class=\"mb-5\">{$this->cminfo->name}</p>"
        );
    }

    /**
     * Defines basic setting elements in form.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function definition_base_settings(): void {
        // Backups: Export activity backup.
        $this->_form->addElement(
            'advcheckbox',
            'export_cm_backup',
            get_string('backups', 'admin'),
            get_string('export_cm_backup', 'local_archiving'),
            $this->config->core->job_preset_export_cm_backup_locked ? 'disabled' : null
        );
        $this->_form->addHelpButton('export_cm_backup', 'export_cm_backup', 'local_archiving');
        $this->_form->setDefault('export_cm_backup', $this->config->core->job_preset_export_cm_backup);

        // Backups: Export course backup.
        $this->_form->addElement(
            'advcheckbox',
            'export_course_backup',
            '&nbsp;',
            get_string('export_course_backup', 'local_archiving'),
            $this->config->core->job_preset_export_course_backup_locked ? 'disabled' : null
        );
        $this->_form->addHelpButton('export_course_backup', 'export_course_backup', 'local_archiving');
        $this->_form->setDefault('export_course_backup', $this->config->core->job_preset_export_course_backup);
    }

    /**
     * Defines advanced setting elements in form. Advanced settings are
     * hidden inside a collapsed menu by default.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function definition_advanced_settings(): void {
        global $CFG;

        // Archive filename pattern.
        $this->_form->addElement(
            'text',
            'archive_filename_pattern',
            get_string('archive_filename_pattern', 'local_archiving'),
            $this->config->core->job_preset_archive_filename_pattern_locked ? 'disabled' : null
        );
        if ($CFG->branch > 402) {
            $this->_form->addHelpButton(
                'archive_filename_pattern',
                'archive_filename_pattern',
                'local_archiving',
                '',
                false,
                [
                    'variables' => array_reduce(
                        storage::ARCHIVE_FILENAME_PATTERN_VARIABLES,
                        fn($res, $varname) => $res."<li>".
                                "<code>\${".$varname."}</code>: ".
                                get_string('archive_filename_pattern_variable_'.$varname, 'local_archiving').
                            "</li>",
                        ""
                    ),
                    'forbiddenchars' => implode('', storage::FILENAME_FORBIDDEN_CHARACTERS),
                ]
            );
        } else {
            // TODO (MDL-0): Remove after deprecation of Moodle 4.1 (LTS) on 08-12-2025.
            $this->_form->addHelpButton('archive_filename_pattern', 'archive_filename_pattern_moodle42', 'local_archiving');
        }
        $this->_form->setType('archive_filename_pattern', PARAM_TEXT);
        $this->_form->setDefault('archive_filename_pattern', $this->config->core->job_preset_archive_filename_pattern);
        $this->_form->addRule('archive_filename_pattern', null, 'maxlength', 255, 'client');

        // Autodelete: Enable.
        $this->_form->addElement(
            'advcheckbox',
            'archive_autodelete',
            get_string('archive_autodelete', 'local_archiving'),
            get_string('enable'),
            $this->config->core->job_preset_archive_autodelete_locked ? 'disabled' : null,
            ['0', '1']
        );
        $this->_form->addHelpButton('archive_autodelete', 'archive_autodelete', 'local_archiving');
        $this->_form->setDefault('archive_autodelete', $this->config->core->job_preset_archive_autodelete);

        // Autodelete: Retention time.
        $mformgroup = [];  // This is wrapped in a form group to make hideIf() work with static elements.
        if ($this->config->core->job_preset_archive_retention_time_locked) {
            $durationwithunit = time_util::duration_to_unit($this->config->core->job_preset_archive_retention_time);
            $mformgroup[] = $this->_form->createElement(
                'static',
                'archive_retention_time_static',
                '',
                $durationwithunit[0].' '.$durationwithunit[1]
            );
            $this->_form->addElement('hidden', 'archive_retention_time', $this->config->core->job_preset_archive_retention_time);
        } else {
            $mformgroup[] = $this->_form->createElement(
                'duration',
                'archive_retention_time',
                '',
                ['optional' => false, 'defaultunit' => DAYSECS],
            );
            $this->_form->setDefault('archive_retention_time', $this->config->core->job_preset_archive_retention_time);
        }
        $this->_form->setType('archive_retention_time', PARAM_INT);

        $this->_form->addGroup(
            $mformgroup,
            'archive_retention_time_group',
            get_string('archive_retention_time', 'local_archiving'),
            '',
            false
        );
        $this->_form->addHelpButton('archive_retention_time_group', 'archive_retention_time', 'local_archiving');
        $this->_form->hideIf('archive_retention_time_group', 'archive_autodelete', 'notchecked');
    }

    /**
     * Defines footer elements including submit buttons in form
     *
     * @return void
     * @throws \coding_exception
     */
    protected function definition_footer(): void {
        $this->_form->closeHeaderBefore('submitbutton');
        $this->_form->addElement('submit', 'submitbutton', get_string('create_archive', 'local_archiving'));
    }

    /**
     * Server-side form data validation
     *
     * @param mixed $data Submitted form data
     * @param mixed $files Uploaed files
     * @return array Associative array with error messages for invalid fields
     * @throws \coding_exception
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!\local_archiving\storage::is_valid_filename_pattern(
            $data['archive_filename_pattern'],
            storage::ARCHIVE_FILENAME_PATTERN_VARIABLES
        )) {
            $errors['archive_filename_pattern'] = get_string('error_invalid_archive_filename_pattern', 'local_archiving');
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
    public function get_data(): \stdClass {
        $data = parent::get_data();

        // Force locked fields to their preset values.
        foreach ($this->config->core as $key => $value) {
            if (strpos($key, 'job_preset_') === 0 && strrpos($key, '_locked') === strlen($key) - 7) {
                if ($value) {
                    $data->{substr($key, 11, -7)} = $this->config->core->{substr($key, 0, -7)};
                }
            }
        }

        return $data;
    }

}
