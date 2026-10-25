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
use local_archiving\external\get_archive_users;
use local_archiving\file_handle;
use local_archiving\local\type\archive_job_status;
use local_archiving\local\type\archive_job_status_filter;
use local_archiving\local\type\db_table;
use local_archiving\local\util\plugin_util;

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
    /** @var \context Context for which archive jobs are shown */
    protected \context $ctx;

    /** @var \course_modinfo Cached course_modinfo object */
    protected \course_modinfo $coursemodinfo;

    /** @var bool Stores whether the viewing user has the capability to delete archive jobs */
    protected bool $usercandelete = false;

    /** @var array<string, string> Display names of all installed archivingtrigger plugins, indexed by plugin name */
    protected array $triggernames = [];

    /** @var array<int, file_handle[]> Cached file handles, indexed by job ID */
    protected array $filehandlecache = [];

    /** @var string[] Names of all URL parameters used for searching and filtering */
    protected const FILTER_PARAMS = ['search', 'status', 'origin', 'userid'];

    /** @var string Free-text search term */
    protected string $search;

    /** @var archive_job_status_filter|null Status category to filter jobs by, or null to show all */
    protected ?archive_job_status_filter $statusfilter;

    /** @var string|null Name of the archivingtrigger plugin to filter jobs by, or null to show all */
    protected ?string $originfilter;

    /** @var \stdClass|null Exported user to filter jobs by archived content, or null to show all */
    protected ?\stdClass $userfilter;

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
        global $DB, $OUTPUT, $PAGE;

        parent::__construct($uniqueid);

        // Validate context and pre-cache modinfo.
        if (!($ctx instanceof \context_course || $ctx instanceof \context_module)) {
            throw new \coding_exception(get_string('invalidcontext', 'error'));
        }

        $this->ctx = $ctx;
        $this->coursemodinfo = get_fast_modinfo($ctx->get_course_context()->instanceid);
        $this->usercandelete = has_capability('local/archiving:delete', $ctx);

        // Read search and filter parameters.
        $this->search = trim(optional_param('search', '', PARAM_TEXT));
        $this->statusfilter = archive_job_status_filter::tryFrom(optional_param('status', '', PARAM_ALPHA));

        // Build trigger / origin filter.
        foreach (plugin_util::get_archiving_triggers() as $name => $trigger) {
            $this->triggernames[$name] = $trigger['displayname'];
        }
        \core_collator::asort($this->triggernames);
        $origin = optional_param('origin', '', PARAM_PLUGIN);
        $this->originfilter = array_key_exists($origin, $this->triggernames) ? $origin : null;

        // Build user filter. Only existing users are accepted.
        $userid = optional_param('userid', 0, PARAM_INT);
        $this->userfilter = $userid > 0 ? get_archive_users::get_exported_user($userid, $ctx) : null;

        $refreshhtml = $OUTPUT->render_from_template('local_archiving/components/refresh_button', [
            'lastupdated' => time(),
            'refreshurl' => $this->add_filter_params($PAGE->url),
        ]);

        // Setup table.
        $this->define_columns([
            'id',
            'timecreated',
            'contextid',
            'origin',
            'status',
            'actions',
        ]);

        $this->define_headers([
            get_string('id', 'local_archiving'),
            get_string('task_starttime', 'admin'),
            get_string('activity'),
            get_string('trigger', 'local_archiving'),
            get_string('status'),
            $refreshhtml,
        ]);

        $this->column_class('actions', 'text-center');

        // We match the context directly by id, or any of its children by path. The seperation is required because
        // the path must have a trailing slash to avoid matching contexts with a similar path (e.g., /1/3/25 vs. /1/3/250).
        $where = '(ctx.id = :ctxid OR ' . $DB->sql_like('ctx.path', ':ctxpath') . ')';
        $params = [
            'ctxid' => $ctx->id,
            'ctxpath' => $ctx->path . '/%',
        ];

        // Restrict to the selected status category.
        if ($this->statusfilter !== null) {
            [$statussql, $statusparams] = $DB->get_in_or_equal(
                array_map(fn ($s) => $s->value, $this->statusfilter->states()),
                SQL_PARAMS_NAMED,
                'js'
            );
            $where .= " AND j.status {$statussql}";
            $params += $statusparams;
        }

        // Restrict to the selected trigger.
        if ($this->originfilter !== null) {
            $where .= ' AND j.origin = :joborigin';
            $params['joborigin'] = $this->originfilter;
        }

        // Restrict to jobs that contain archived data of the selected user.
        if ($this->userfilter !== null) {
            $where .= ' AND EXISTS (SELECT 1 FROM {' . db_table::CONTENT->value . '} uc ' .
                'JOIN {' . db_table::ACTIVITY_TASK->value . '} ut ON ut.id = uc.taskid ' .
                'WHERE ut.jobid = j.id AND uc.userid = :jobcontentuser)';
            $params['jobcontentuser'] = $this->userfilter->id;
        }

        // Match search term against names of non-deleted files and activity names.
        if ($this->search !== '') {
            $searchconditions = [
                'EXISTS (SELECT 1 FROM {' . db_table::FILE_HANDLE->value . '} f ' .
                    'WHERE f.jobid = j.id AND f.deleted = 0 AND ' .
                    $DB->sql_like('f.filename', ':jobsearchfile', false) . ')',
            ];
            $params['jobsearchfile'] = '%' . $DB->sql_like_escape($this->search) . '%';

            $matchingctxids = $this->get_contextids_of_matching_activities($this->search);
            if (!empty($matchingctxids)) {
                [$cmsql, $cmparams] = $DB->get_in_or_equal($matchingctxids, SQL_PARAMS_NAMED, 'jobsearchctx');
                $searchconditions[] = "j.contextid {$cmsql}";
                $params += $cmparams;
            }

            $where .= ' AND (' . implode(' OR ', $searchconditions) . ')';
        }

        $this->set_sql(
            'j.id, j.status, j.timecreated, j.timemodified, j.contextid, j.userid, j.origin, u.username, ctx.instanceid AS cmid, ' .
                '(SELECT COUNT(c.id) FROM {' . db_table::CONTENT->value . '} c ' .
                    'JOIN {' . db_table::ACTIVITY_TASK->value . '} t ON t.id = c.taskid ' .
                    'WHERE t.jobid = j.id) AS contentcount',
            '{' . db_table::JOB->value . '} j ' .
                'JOIN {user} u ON j.userid = u.id ' .
                'JOIN {context} ctx ON ctx.id = j.contextid',
            $where,
            $params
        );

        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('actions');
        $this->collapsible(false);
    }

    /**
     * Defines the base URL of this table, retaining the current search and filter parameters
     *
     * @param string|\moodle_url $url Base URL of the table
     * @return void
     * @throws \moodle_exception
     */
    public function define_baseurl($url) {
        parent::define_baseurl($this->add_filter_params(new \moodle_url($url)));
    }

    /**
     * Renders the search form, followed by the table itself
     *
     * The search form is printed here, instead of in wrap_html_start(), to
     * keep it visible even if the current filter yields no results.
     *
     * @param int $pagesize Number of rows per page
     * @param bool $useinitialsbar Whether to show the initials bar
     * @param string $downloadhelpbutton Download help button HTML
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        echo $this->render_search_form();
        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }

    /**
     * Starts the table HTML output without rendering the paging bar above the table
     *
     * The paging bar below the table is unaffected, since use_pages is
     * restored before finish_html() is called.
     *
     * @return void
     */
    public function start_html() {
        $usepages = $this->use_pages;
        $this->use_pages = false;
        try {
            parent::start_html();
        } finally {
            $this->use_pages = $usepages;
        }
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
        $cm = $this->coursemodinfo->get_cm($values->cmid);

        $html = \html_writer::link($cm->get_url(), $cm->get_formatted_name());

        // Show number of archived objects, if known.
        if ($values->contentcount > 0) {
            $contenticon = $values->contentcount > 1 ? 'fa-solid fa-cubes' : 'fa-solid fa-cube fa-xs mr-1';
            $html .= \html_writer::link(
                new \moodle_url('/local/archiving/job.php', ['id' => $values->id]),
                \html_writer::tag(
                    'small',
                    s($values->contentcount) . '<i class="' . $contenticon . ' ml-2" aria-hidden="true"></i>'
                ),
                ['class' => 'text-muted ml-1 float-right'] +
                    $this->tooltip_attributes(get_string('number_of_archived_objects', 'local_archiving'))
            );
        }

        // List names of all files that exist in storage.
        $filenames = [];
        foreach ($this->get_file_handles($values->id) as $file) {
            if (!$file->deleted) {
                $filenames[] = \html_writer::tag(
                    'li',
                    '<i class="fa-regular fa-file-zipper" aria-hidden="true"></i>&nbsp;' . s($file->filename),
                    ['class' => 'list-group-item bg-transparent px-0 py-1 text-break']
                );
            }
        }

        if (empty($filenames)) {
            $filenames[] = \html_writer::tag(
                'li',
                get_string('no_files_attached', 'local_archiving'),
                ['class' => 'list-group-item bg-transparent px-0 py-1 text-break text-muted']
            );
        }

        $html .= \html_writer::tag(
            'ul',
            implode('', $filenames),
            ['class' => 'list-group list-group-flush small mb-0 mt-1']
        );

        return $html;
    }

    /**
     * Column renderer for the origin / user column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \moodle_exception
     */
    public function col_origin($values) {
        // Add initiating user if the job was triggered manually.
        if ($values->origin === 'manual') {
            $badgecolor = 'primary';
            $posthtml = \html_writer::link(
                new \moodle_url('/user/profile.php', ['id' => $values->userid]),
                s($values->username)
            );
        } else {
            $badgecolor = 'secondary';
            $posthtml = \html_writer::tag(
                'small',
                get_string('coresystem', 'moodle'),
                ['class' => 'text-muted fst-italic font-italic']
            );
        }

        // Render origin pill.
        $html = \html_writer::span(
            s($this->triggernames[$values->origin] ?? $values->origin),
            'badge badge-' . $badgecolor
        );
        $html .= \html_writer::empty_tag('br');
        $html .= $posthtml;

        return $html;
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

        $html = \html_writer::span($status->text, 'badge badge-' . $status->color, $this->tooltip_attributes($status->help));
        $html .= \html_writer::empty_tag('br');

        $progress = $job->get_progress();
        if ($progress !== null && $progress < 100) {
            $progresslabel = get_string('progress', 'local_archiving');
            $html .= \html_writer::span(
                \html_writer::tag('i', '', ['class' => 'fa fa-spinner', 'aria-hidden' => 'true']) . '&nbsp;' . $progress . '%',
                '',
                ['aria-label' => $progresslabel] + $this->tooltip_attributes($progresslabel)
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

        // First button group.
        $groupclass = 'd-block d-lg-inline-block text-nowrap';
        $html = \html_writer::start_span($groupclass);

        // Action: Download.
        $files = $this->get_file_handles($values->id);
        if (archive_job_status::from($values->status)->is_final() && count($files) > 0) {
            $downloadurl = new \moodle_url('/local/archiving/download.php', ['jobid' => $values->id]);
            $html .= $this->action_button($downloadurl, 'btn-success', get_string('download'), 'fa-download');
        } else {
            $html .= $this->action_button(
                null,
                'btn-outline-success',
                get_string('files_require_successful_job', 'local_archiving'),
                'fa-download'
            );
        }

        // Action: Show job details.
        $html .= $this->action_button(
            new \moodle_url('/local/archiving/job.php', ['id' => $values->id]),
            'btn-primary',
            get_string('details', 'moodle'),
            'fa-magnifying-glass'
        );

        // Second button gruop.
        $html .= \html_writer::end_span();
        $html .= \html_writer::start_span($groupclass);

        // Action: Show logs.
        $logurl = new \moodle_url('/local/archiving/logs.php', ['jobid' => $values->id]);
        $html .= $this->action_button($logurl, 'btn-info', get_string('logs'), 'fa-file-waveform');

        // Action: Delete.
        if ($this->usercandelete) {
            $deleteurl = new \moodle_url('/local/archiving/manage.php', [
                'action' => 'jobdelete',
                'contextid' => $values->contextid,
                'jobid' => $values->id,
                'wantsurl' => $PAGE->url->out(false),
            ]);
            $html .= $this->action_button($deleteurl, 'btn-danger', get_string('delete'), 'fa-trash');
        } else {
            $html .= $this->action_button(
                null,
                'btn-outline-danger',
                get_string('deletion_prohibited_by_capability', 'local_archiving'),
                'fa-trash'
            );
        }

        $html .= \html_writer::end_span();

        return $html;
    }

    /**
     * Retrieves all file handles of the given job, caching them for subsequent column renderers
     *
     * @param int $jobid ID of the job to retrieve file handles for
     * @return file_handle[] List of file handles associated with the job
     * @throws \dml_exception
     */
    protected function get_file_handles(int $jobid): array {
        if (!array_key_exists($jobid, $this->filehandlecache)) {
            $this->filehandlecache[$jobid] = file_handle::get_by_jobid($jobid);
        }

        return $this->filehandlecache[$jobid];
    }

    /**
     * Renders an icon-only action button.
     *
     * We create a wrapper span around the button to ensure that the tooltip is
     * displayed even when the button is disabled. Otherwise pointer events will
     * be ignored by the browser.
     *
     * @param \moodle_url|null $url Target of the button, or null to render a disabled button
     * @param string $btnclass Bootstrap button class (e.g. 'btn-success')
     * @param string $label Accessible label and tooltip of the button
     * @param string $icon FontAwesome icon class (e.g. 'fa-download')
     * @return string HTML code of the button
     */
    protected function action_button(?\moodle_url $url, string $btnclass, string $label, string $icon): string {
        $btnattributes = [
            'class' => "btn {$btnclass} m-1",
            'role' => 'button',
            'title' => $label,
            'aria-label' => $label,
        ];

        // Render buttons with null URLs as disabled.
        if ($url === null) {
            $btnattributes['class'] .= ' disabled';
            $btnattributes['aria-disabled'] = 'true';
            $btnattributes['tabindex'] = '-1';
        }

        $buttonhtml = \html_writer::link(
            $url ?? '#',
            \html_writer::tag('i', '', ['class' => "fa {$icon}", 'aria-hidden' => 'true']),
            $btnattributes
        );

        return \html_writer::span(
            $buttonhtml,
            'd-inline-block',
            $this->tooltip_attributes($label)
        );
    }

    /**
     * Renders the search and status filter form for this table
     *
     * @return string HTML code of the search form
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function render_search_form(): string {
        global $OUTPUT;

        // Preserve all page parameters except search, filter, and pagination.
        $hiddenparams = [];
        $excludedparams = [...self::FILTER_PARAMS, $this->request[TABLE_VAR_PAGE]];
        foreach ($this->baseurl->params() as $name => $value) {
            if (!in_array($name, $excludedparams, true)) {
                $hiddenparams[] = ['name' => $name, 'value' => $value];
            }
        }

        $statusoptions = [];
        foreach (archive_job_status_filter::cases() as $filter) {
            $statusoptions[] = [
                'value' => $filter->value,
                'name' => $filter->name(),
                'selected' => $filter === $this->statusfilter,
            ];
        }

        $originoptions = [];
        foreach ($this->triggernames as $name => $displayname) {
            $originoptions[] = [
                'value' => $name,
                'name' => $displayname,
                'selected' => $name === $this->originfilter,
            ];
        }

        $reseturl = new \moodle_url($this->baseurl);
        $reseturl->remove_params(...self::FILTER_PARAMS);

        $selecteduser = null;
        if ($this->userfilter !== null) {
            $selecteduser = [
                'id' => $this->userfilter->id,
                'fullname' => $this->userfilter->fullname,
                'label' => $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $this->userfilter),
            ];
        }

        return $OUTPUT->render_from_template('local_archiving/components/job_table_search', [
            'uniqueid' => $this->uniqueid,
            'contextid' => $this->ctx->id,
            'actionurl' => $this->baseurl->out_omit_querystring(),
            'hiddenparams' => $hiddenparams,
            'search' => $this->search,
            'statusoptions' => $statusoptions,
            'originoptions' => $originoptions,
            'selecteduser' => $selecteduser,
            'filteractive' => $this->search !== '' ||
                $this->statusfilter !== null ||
                $this->originfilter !== null ||
                $this->userfilter !== null,
            'reseturl' => $reseturl->out(false),
        ]);
    }

    /**
     * Returns a copy of the given URL with the current search and filter parameters added
     *
     * @param \moodle_url $url URL to add the parameters to
     * @return \moodle_url New URL with the search and filter parameters set
     * @throws \moodle_exception
     */
    protected function add_filter_params(\moodle_url $url): \moodle_url {
        $url = new \moodle_url($url);
        $url->remove_params(...self::FILTER_PARAMS);

        if ($this->search !== '') {
            $url->param('search', $this->search);
        }
        if ($this->statusfilter !== null) {
            $url->param('status', $this->statusfilter->value);
        }
        if ($this->originfilter !== null) {
            $url->param('origin', $this->originfilter);
        }
        if ($this->userfilter !== null) {
            $url->param('userid', $this->userfilter->id);
        }

        return $url;
    }

    /**
     * Determines the context IDs of all activities within the current course
     * whose name contains the given search term (case-insensitive)
     *
     * @param string $search Search term
     * @return int[] Context IDs of matching activities
     */
    protected function get_contextids_of_matching_activities(string $search): array {
        $search = \core_text::strtolower($search);
        $ctxids = [];

        foreach ($this->coursemodinfo->get_cms() as $cm) {
            if (\core_text::strpos(\core_text::strtolower($cm->get_formatted_name()), $search) !== false) {
                $ctxids[] = $cm->context->id;
            }
        }

        return $ctxids;
    }

    /**
     * Builds the HTML attributes required to show a tooltip on an element
     *
     * Both Bootstrap 4 and Bootstrap 5 data attributes are set to support all current Moodle versions.
     *
     * @param string $title Text of the tooltip
     * @return array<string, string> HTML attributes
     */
    protected function tooltip_attributes(string $title): array {
        return [
            'title' => $title,
            'data-toggle' => 'tooltip',
            'data-bs-toggle' => 'tooltip',
            'data-placement' => 'top',
            'data-bs-placement' => 'top',
        ];
    }
}
