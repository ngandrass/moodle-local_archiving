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
 * Form for deleting single archiving job artifact files
 *
 * @package    local_archiving
 * @copyright  2025 Niels Gandraß <niels@gandrass.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\form;

use local_archiving\archive_job;
use local_archiving\file_handle;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->dirroot.'/lib/formslib.php'); // @codeCoverageIgnore


/**
 * Form to delete a single artifact file that's part of an archive job
 */
class file_delete_form extends \moodleform {

    /** @var file_handle The file handle to delete the referenced file from */
    protected file_handle $filehandle;

    /** @var archive_job Archive job this form is associated with */
    protected archive_job $job;

    /**
     * Creates a new form instance
     *
     * @param int $contextid ID of the context this form is associated with
     * @param int $filehandleid ID of the file handle to delete the referenced file from
     * @param string $wantsurl Desired redirect URL
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    #[\Override]
    public function __construct(
        /** @var int $contextid ID of the context this form is associated with */
        protected int $contextid,
        /** @var int $filehandleid ID of the file handle to delete the referenced file from */
        int $filehandleid,
        /** @var string $wantsurl Desired redirect URL */
        protected string $wantsurl
    ) {
        global $PAGE;

        // Get and validate file handle.
        $this->filehandle = file_handle::get_by_id($filehandleid);
        $this->job = archive_job::get_by_id($this->filehandle->jobid);
        if ($this->job->get_context()->id != $contextid) {
            throw new \moodle_exception('invalidcontext', 'local_archiving');
        }

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
        global $OUTPUT;

        // Print delete warning.
        $this->_form->addElement('html', $OUTPUT->notification(
            '<h4>'.get_string('delete_job_artifact_file', 'local_archiving').'</h4>'.
            '<p>'.get_string('delete_job_artifact_file_warning', 'local_archiving').'</p>'.
            '<code>'.
                get_string('filename', 'backup').': '.$this->filehandle->filename.'<br>'.
                get_string('size').': '.display_size($this->filehandle->filesize).'<br>'.
                get_string('timecreated').': '.userdate($this->filehandle->timecreated).
            '</code>',
            \core\output\notification::NOTIFY_WARNING,
            false
        ));

        // Form data.
        $this->_form->addElement('hidden', 'action', 'filedelete');
        $this->_form->setType('action', PARAM_TEXT);

        $this->_form->addElement('hidden', 'contextid', $this->contextid);
        $this->_form->setType('contextid', PARAM_INT);

        $this->_form->addElement('hidden', 'filehandleid', $this->filehandle->id);
        $this->_form->setType('filehandleid', PARAM_INT);

        $this->_form->addElement('hidden', 'wantsurl', $this->wantsurl);
        $this->_form->setType('wantsurl', PARAM_URL);

        // Action buttons.
        $this->add_action_buttons(true, get_string('delete'));
    }

}
