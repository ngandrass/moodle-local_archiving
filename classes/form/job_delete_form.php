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
 * Form for deleting archive jobs
 *
 * @package    local_archiving
 * @copyright  2025 Niels Gandraß <niels@gandrass.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\form;

use local_archiving\archive_job;
use local_archiving\type\archive_job_status;

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->dirroot.'/lib/formslib.php'); // @codeCoverageIgnore


/**
 * Form to delete an archiving job
 */
class job_delete_form extends \moodleform {

    /** @var archive_job Archive job this form is associated with */
    protected archive_job $job;

    /**
     * Creates a new form instance
     *
     * @param int $contextid ID of the context this form is associated with
     * @param int $jobid ID of the archive job this form is associated with
     * @param string $wantsurl Desired redirect URL
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct(
        /** @var int $contextid ID of the context this form is associated with */
        protected int $contextid,
        int $jobid,
        /** @var string $wantsurl Desired redirect URL */
        protected string $wantsurl
    ) {
        global $PAGE;

        // Get and validate job.
        $this->job = archive_job::get_by_id($jobid);
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
            '<h4>'.get_string('delete_job', 'local_archiving').'</h4>'.
            '<p>'.get_string('delete_job_warning', 'local_archiving').'</p>'.
            '<code>'.
                get_string('jobid', 'local_archiving').': '.$this->job->get_id().'<br>'.
                get_string('status').': '.$this->job->get_status()->name.
            '</code>',
            \core\output\notification::NOTIFY_WARNING,
            false
        ));

        // Form data.
        $this->_form->addElement('hidden', 'action', 'jobdelete');
        $this->_form->setType('action', PARAM_TEXT);

        $this->_form->addElement('hidden', 'contextid', $this->contextid);
        $this->_form->setType('contextid', PARAM_INT);

        $this->_form->addElement('hidden', 'jobid', $this->job->get_id());
        $this->_form->setType('jobid', PARAM_INT);

        $this->_form->addElement('hidden', 'wantsurl', $this->wantsurl);
        $this->_form->setType('wantsurl', PARAM_URL);

        // Action buttons.
        $this->add_action_buttons(true, get_string('delete'));
    }

}
