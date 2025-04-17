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
 * This file defines the job overview table renderer
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\output;

use local_archiving\archive_job;
use local_archiving\type\archive_job_status;
use local_archiving\type\db_table;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

// @codeCoverageIgnoreStart
global $CFG;
require_once($CFG->libdir.'/tablelib.php');
// @codeCoverageIgnoreEnd


/**
 * Table renderer for the job overview table
 */
class job_overview_table extends \table_sql {

    /** @var \context_course Course context this table is associated with */
    protected \context_course $coursectx;

    /** @var \course_modinfo Cached course_modinfo object */
    protected \course_modinfo $coursemodinfo;

    /**
     * Constructor
     *
     * @param string $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param \context_course|\context_module $ctx Context for which archive jobs should be shown
     *
     * @throws \coding_exception
     */
    #[\Override]
    public function __construct(string $uniqueid, \context $ctx) {
        parent::__construct($uniqueid);

        // Validate context and pre-cache modinfo.
        if (!($ctx instanceof \context_course || $ctx instanceof \context_module)) {
            throw new \coding_exception(get_string('invalidcontext', 'local_archiving'));
        }

        $this->coursectx = $ctx->get_course_context();
        $this->coursemodinfo = get_fast_modinfo($this->coursectx->instanceid);

        // Setup table.
        $this->define_columns([
            'id',
            'contextid',
            'timecreated',
            'user',
            'status',
            'actions',
        ]);

        $this->define_headers([
            get_string('id', 'local_archiving'),
            get_string('activity'),
            get_string('task_starttime', 'admin'),
            get_string('user'),
            get_string('status'),
            '',
        ]);

        $this->set_sql(
            'j.id, j.status, j.timecreated, j.timemodified, j.contextid, j.userid, u.username',
            '{'.db_table::JOB->value.'} j '.
                'JOIN {user} u ON j.userid = u.id '.
                'JOIN {context} ctx ON ctx.id = j.contextid',
            "ctx.path LIKE :ctxpath",
            [
                'ctxpath' => $ctx->path.'%',
            ]
        );

        $this->sortable(true, 'id', SORT_DESC);
        $this->no_sorting('actions');
        $this->collapsible(false);
    }

    /**
     * Column renderer for the timecreated column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     */
    public function col_timecreated($values) {
        return date('Y-m-d\<\b\r\\>H:i:s', $values->timecreated);
    }

    /**
     * Column renderer for the contextid column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     */
    public function col_contextid($values) {
        $modctx = \context::instance_by_id($values->contextid);
        $cm = $this->coursemodinfo->get_cm($modctx->instanceid);

        return '<a href="'.$cm->get_url().'">'.$cm->name.'</a>';
    }

    /**
     * Column renderer for the user column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \moodle_exception
     */
    public function col_user($values) {
        return '<a href="'.new \moodle_url('/user/profile.php', ['id' => $values->userid]).'">'.$values->username.'</a>';
    }

    /**
     * Column renderer for the status column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function col_status($values) {
        $job = archive_job::get_by_id($values->id);
        $status = archive_job_status::from($values->status)->status_display_args();

        $statustooltiphtml = 'data-toggle="tooltip" data-placement="top" title="'.$status->help.'"';
        $html = '<span class="badge badge-'.$status->color.'" '.$statustooltiphtml.'>'.$status->text.'</span><br/>';

        $progress = $job->get_progress();
        if ($progress !== null && $progress < 100) {
            // @codingStandardsIgnoreLine
            $html .= '<span title="'.get_string('progress', 'local_archiving').' alt="'.get_string('progress', 'local_archiving').'" data-toggle="tooltip" data-placement="top">';
            $html .= '<i class="fa fa-spinner"></i>&nbsp;'.$progress.'%';
            $html .= '</span><br/>';
        }

        $html .= '<small>'.date('H:i:s', $values->timemodified).'</small>';

        return $html;
    }

    /**
     * Column renderer for the actions column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_actions($values) {
        global $PAGE;
        $html = '';

        // Action: Delete.
        $deleteurl = new \moodle_url('/local/archiving/manage.php', [
            'action' => 'jobdelete',
            'contextid' => $values->contextid,
            'jobid' => $values->id,
            'wantsurl' => $PAGE->url->out(true),
        ]);
        // @codingStandardsIgnoreLine
        $html .= '<a href="'.$deleteurl.'" class="btn btn-danger mx-1" role="button" data-toggle="tooltip" data-placement="top" title="'.get_string('delete').'" alt="'.get_string('delete').'"><i class="fa fa-trash"></i></a>';

        return $html;
    }

}
