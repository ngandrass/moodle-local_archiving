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
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\output;

use core\exception\moodle_exception;
use local_archiving\archive_job;
use local_archiving\file_handle;
use local_archiving\local\type\archive_job_status;
use local_archiving\local\type\db_table;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

// @codeCoverageIgnoreStart
global $CFG;
require_once($CFG->libdir . '/tablelib.php');
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
     * @param \context $ctx Context for which archive jobs should be shown
     * @throws \coding_exception
     * @throws moodle_exception
     * @throws \moodle_exception
     */
    public function __construct(string $uniqueid, \context $ctx) {
        global $OUTPUT, $PAGE;

        parent::__construct($uniqueid);

        // Validate context and pre-cache modinfo.
        if (!($ctx instanceof \context_course || $ctx instanceof \context_module)) {
            throw new \coding_exception(get_string('invalidcontext', 'local_archiving'));
        }

        $this->coursectx = $ctx->get_course_context();
        $this->coursemodinfo = get_fast_modinfo($this->coursectx->instanceid);

        $refreshhtml = $OUTPUT->render_from_template('local_archiving/components/refresh_button', [
            'lastupdated' => time(),
            'refreshurl' => $PAGE->url,
        ]);

        // Setup table.
        $this->define_columns([
            'timecreated',
            'id',
            'contextid',
            'user',
            'status',
            'actions',
        ]);

        $this->define_headers([
            get_string('task_starttime', 'admin'),
            get_string('id', 'local_archiving'),
            get_string('activity'),
            get_string('user'),
            get_string('status'),
            $refreshhtml,
        ]);

        $this->column_class('actions', 'text-center');

        $this->set_sql(
            'j.id, j.status, j.timecreated, j.timemodified, j.contextid, j.userid, u.username',
            '{' . db_table::JOB->value . '} j ' .
                'JOIN {user} u ON j.userid = u.id ' .
                'JOIN {context} ctx ON ctx.id = j.contextid',
            "ctx.path LIKE :ctxpath",
            [
                'ctxpath' => $ctx->path . '%',
            ]
        );

        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('actions');
        $this->collapsible(false);
    }

    /**
     * Column renderer for the timecreated column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     */
    public function col_timecreated($values) {
        return userdate($values->timecreated, '%Y-%m-%d') .
            \html_writer::empty_tag('br') .
            userdate($values->timecreated, '%H:%M:%S');
    }

    /**
     * Column renderer for the contextid column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_contextid($values) {
        $modctx = \context::instance_by_id($values->contextid);
        $cm = $this->coursemodinfo->get_cm($modctx->instanceid);

        return \html_writer::link($cm->get_url(), $cm->get_formatted_name());
    }

    /**
     * Column renderer for the user column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \moodle_exception
     */
    public function col_user($values) {
        return \html_writer::link(new \moodle_url('/user/profile.php', ['id' => $values->userid]), s($values->username));
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

        $html = \html_writer::span($status->text, 'badge badge-' . $status->color, [
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
            'title' => $status->help,
        ]);
        $html .= \html_writer::empty_tag('br');

        $progress = $job->get_progress();
        if ($progress !== null && $progress < 100) {
            $progresslabel = get_string('progress', 'local_archiving');
            $html .= \html_writer::span(
                \html_writer::tag('i', '', ['class' => 'fa fa-spinner', 'aria-hidden' => 'true']) . '&nbsp;' . $progress . '%',
                '',
                [
                    'title' => $progresslabel,
                    'aria-label' => $progresslabel,
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                ]
            );
            $html .= \html_writer::empty_tag('br');
        }

        $html .= \html_writer::tag('small', userdate($values->timemodified, '%H:%M:%S'));

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

        // Action: Download.
        $job = archive_job::get_by_id($values->id);
        $files = file_handle::get_by_jobid($values->id);
        if ($job->is_completed() && count($files) > 0) {
            $downloadurl = new \moodle_url('/local/archiving/download.php', ['jobid' => $values->id]);
            $html .= $this->action_button($downloadurl, 'btn-success', get_string('download'), 'fa-download');
        } else {
            $html .= $this->action_button(null, 'btn-outline-success', get_string('download'), 'fa-download');
        }

        // Action: Show logs.
        $logurl = new \moodle_url('/local/archiving/logs.php', ['jobid' => $values->id]);
        $html .= $this->action_button($logurl, 'btn-info', get_string('logs'), 'fa-file-waveform');

        // Action: Delete.
        if (has_capability('local/archiving:delete', \context::instance_by_id($values->contextid))) {
            $deleteurl = new \moodle_url('/local/archiving/manage.php', [
                'action' => 'jobdelete',
                'contextid' => $values->contextid,
                'jobid' => $values->id,
                'wantsurl' => $PAGE->url->out(false),
            ]);
            $html .= $this->action_button($deleteurl, 'btn-danger', get_string('delete'), 'fa-trash');
        }

        return $html;
    }

    /**
     * Renders an icon-only action button
     *
     * @param \moodle_url|null $url Target of the button, or null to render a disabled button
     * @param string $btnclass Bootstrap button class (e.g. 'btn-success')
     * @param string $label Accessible label and tooltip of the button
     * @param string $icon FontAwesome icon class (e.g. 'fa-download')
     * @return string HTML code of the button
     */
    protected function action_button(?\moodle_url $url, string $btnclass, string $label, string $icon): string {
        $attributes = [
            'class' => "btn {$btnclass} mx-1",
            'role' => 'button',
            'title' => $label,
            'aria-label' => $label,
        ];

        if ($url === null) {
            $attributes['class'] .= ' disabled';
            $attributes['aria-disabled'] = 'true';
            $attributes['tabindex'] = '-1';
        } else {
            $attributes['data-toggle'] = 'tooltip';
            $attributes['data-bs-toggle'] = 'tooltip';
            $attributes['data-placement'] = 'top';
            $attributes['data-bs-placement'] = 'top';
        }

        return \html_writer::link(
            $url ?? '#',
            \html_writer::tag('i', '', ['class' => "fa {$icon}", 'aria-hidden' => 'true']),
            $attributes
        );
    }
}
