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
use local_archiving\type\archive_filename_variable;
use local_archiving\util\course_util;
use local_archiving\util\plugin_util;
use local_archiving\util\time_util;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->dirroot.'/lib/formslib.php'); // @codeCoverageIgnore


/**
 * Form to initiate an activity archiving job
 */
class job_create_form extends \moodleform {

    /** @var \stdClass Moodle admin settings values for 'core' (local_archiving) and the respective 'handler' (archivingmod_*) */
    protected \stdClass $config;

    /**
     * Creates a new form instance
     *
     * @param string $handler Name of the archivingmod sub-plugin that handles this job
     * @param \cm_info $cminfo Info object for the targeted course module
     * @throws \dml_exception
     */
    public function __construct(
        /** @var string $handler Name of the archivingmod sub-plugin that handles this job */
        protected string $handler,
        /** @var \cm_info $cminfo Info object for the targeted course module */
        protected \cm_info $cminfo
    ) {
        global $PAGE;

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
    #[\Override]
    public function definition() {
        $this->definition_header();

        // Prevent form from being displayed if archiving is disabled for this course.
        if (!course_util::archiving_enabled_for_course($this->cminfo->get_course()->id)) {
            if (has_capability('local/archiving:bypasscourserestrictions', $this->cminfo->context)) {
                // User is allowed to bypass the course archiving restriction. But warn the user about it.
                $this->_form->addElement('html',
                    '<div class="alert alert-warning">'.
                        get_string('archiving_force_allowed_for_course', 'local_archiving').
                    '</div>'
                );
            } else {
                // User is not allowed to bypass this restriction. Display warning and abort.
                $this->_form->addElement('html',
                    '<div class="alert alert-danger">'.
                        get_string('archiving_disabled_for_this_course_by_category', 'local_archiving').
                    '</div>'
                );
                return;
            }
        }

        // Prevent form from being displayed if manual archiving is disabled.
        if (!\local_archiving\driver\factory::archiving_trigger('manual')->is_enabled()) {
            $this->_form->addElement('html',
                '<div class="alert alert-warning">'.
                    get_string('can_not_create_archive_manual_archiving_disabled', 'local_archiving').
                '</div>'
            );
            return;
        }

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

        $modpurpose = plugin_supports('mod', $this->cminfo->modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER) ?: '';
        $activityhtml = '<div class="mb-4">
            <h6>'.get_string('target_activity', 'local_archiving').'</h6>
            <ul class="list-group" style="max-width: 600px;">
                <a href="' . $this->cminfo->get_url() . '" class="list-group-item list-group-item-action text-primary">
                    <div class="d-inline activity-icon activityiconcontainer ' . $modpurpose . ' pl-0">
                        <img src="' . $this->cminfo->get_icon_url() . '" class="activityicon mr-1" alt=""/>
                    </div>
                    <div class="d-inline">' . $this->cminfo->name . '</div>
                </a>
            </ul>
        </div>';
        $this->_form->addElement('html', $activityhtml);
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

        // Storage location.
        $storagedriverselects = [];
        foreach (plugin_util::get_storage_drivers() as $name => $driver) {
            if ($driver['enabled']) {
                $storagedriverselects[$name] = $driver['displayname'];
            }
        }
        $this->_form->addElement(
            'select',
            'storage_driver',
            get_string('storage_location', 'local_archiving'),
            $storagedriverselects,
            $this->config->core->job_preset_storage_driver_locked ? 'disabled' : null
        );
        $this->_form->addHelpButton('storage_driver', 'storage_location', 'local_archiving');
        $this->_form->setDefault('storage_driver', $this->config->core->job_preset_storage_driver);
    }

    /**
     * Defines advanced setting elements in form. Advanced settings are
     * hidden inside a collapsed menu by default.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function definition_advanced_settings(): void {
        // Archive filename pattern.
        $this->_form->addElement(
            'text',
            'archive_filename_pattern',
            get_string('archive_filename_pattern', 'local_archiving'),
            $this->config->core->job_preset_archive_filename_pattern_locked ? 'disabled' : null
        );
        $this->_form->addHelpButton(
            'archive_filename_pattern',
            'archive_filename_pattern',
            'local_archiving',
            '',
            false,
            [
                'variables' => array_reduce(
                    archive_filename_variable::values(),
                    fn($res, $varname) => $res."<li>".
                            "<code>\${".$varname."}</code>: ".
                            get_string('archive_filename_pattern_variable_'.$varname, 'local_archiving').
                        "</li>",
                    ""
                ),
                'forbiddenchars' => implode('', storage::FILENAME_FORBIDDEN_CHARACTERS),
            ]
        );
        $this->_form->setType('archive_filename_pattern', PARAM_TEXT);
        $this->_form->setDefault('archive_filename_pattern', $this->config->core->job_preset_archive_filename_pattern);
        $this->_form->addRule('archive_filename_pattern', null, 'maxlength', 255, 'client');

        // Autodelete: Enable.
        // TODO (MDL-0): Implement automatic deletion.
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
        $this->add_action_buttons(
            cancel: true,
            submitlabel: get_string('create_archive', 'local_archiving'),
        );
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

        if (!storage::is_valid_filename_pattern(
            $data['archive_filename_pattern'],
            archive_filename_variable::values(),
            storage::FILENAME_FORBIDDEN_CHARACTERS
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
    #[\Override]
    public function get_data(): \stdClass {
        $data = parent::get_data();

        // Force locked fields to their preset values.
        foreach ($this->config->core as $key => $value) {
            if (str_starts_with($key, 'job_preset_') && strrpos($key, '_locked') === strlen($key) - 7) {
                if ($value) {
                    $data->{substr($key, 11, -7)} = $this->config->core->{substr($key, 0, -7)};
                }
            }
        }

        return $data;
    }

}
