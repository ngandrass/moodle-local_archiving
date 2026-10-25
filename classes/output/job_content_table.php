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
 * This file defines the job content table renderer
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\output;

use local_archiving\activity_archiving_task;
use local_archiving\archive_job;
use local_archiving\external\get_archive_users;
use local_archiving\local\driver\archivingmod;
use local_archiving\local\driver\driver_factory;
use local_archiving\local\type\db_table;
use local_archiving\local\type\task_content_metadata;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

// @codeCoverageIgnoreStart
global $CFG;
require_once($CFG->libdir . '/tablelib.php');
// @codeCoverageIgnoreEnd


/**
 * Table renderer for all objects (e.g., quiz attempts) archived by a single archive job,
 * based on task_content_metadata rows.
 */
class job_content_table extends \table_sql {
    /** @var archive_job Archive job whose contents are shown */
    protected archive_job $job;

    /** @var \context_module Context of the archived activity */
    protected \context_module $ctx;

    /** @var archivingmod|null Activity archiving driver used to label archived objects, or null if unavailable */
    protected ?archivingmod $driver;

    /** @var \stdClass[] Labels and URLs of the archived objects on the current page, indexed by content ID */
    protected array $labels = [];

    /** @var string[] Identity fields to display for each user */
    protected array $extrafields;

    /** @var bool Whether the viewing user may see full names */
    protected bool $viewfullnames;

    /** @var string[] Names of all URL parameters used for searching and filtering */
    protected const FILTER_PARAMS = ['userid'];

    /** @var \stdClass|null Exported user to filter contents by, or null to show all */
    protected ?\stdClass $userfilter;

    /**
     * Constructor
     *
     * @param string $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param archive_job $job Archive job whose contents should be shown
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function __construct(string $uniqueid, archive_job $job) {
        parent::__construct($uniqueid);

        $this->job = $job;
        $this->ctx = $job->get_context();
        $this->viewfullnames = has_capability('moodle/site:viewfullnames', $this->ctx);

        // Load the activity archiving driver that created the archived contents. Fall back to the default labels if unavailable.
        $tasks = activity_archiving_task::get_by_jobid($job->get_id());
        $task = reset($tasks);
        try {
            $this->driver = $task ? driver_factory::activity_archiving_driver($task->get_archivingmodname(), $this->ctx) : null;
        } catch (\moodle_exception $e) {
            $this->driver = null;
        }

        // Build user filter. Only existing users are accepted.
        $userid = optional_param('userid', 0, PARAM_INT);
        $this->userfilter = $userid > 0 ? get_archive_users::get_exported_user($userid, $this->ctx) : null;

        // Setup table.
        $this->define_columns([
            'fullname',
            'refid',
            'details',
        ]);

        $this->define_headers([
            get_string('user'),
            get_string('archived_data', 'local_archiving'),
            get_string('details'),
        ]);

        $userfields = \core_user\fields::for_userpic()->with_identity($this->ctx, false);
        $this->extrafields = $userfields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]);
        $userfieldsql = $userfields->get_sql('u', true, '', 'userid');
        $this->useridfield = 'userid';

        $where = 't.jobid = :jobid';
        $params = ['jobid' => $job->get_id()];

        // Restrict to the selected user.
        if ($this->userfilter !== null) {
            $where .= ' AND c.userid = :contentuserid';
            $params['contentuserid'] = $this->userfilter->id;
        }

        $this->set_sql(
            "c.id, c.taskid, c.reftable, c.refid, c.summary, u.deleted {$userfieldsql->selects}",
            '{' . db_table::CONTENT->value . '} c ' .
                'JOIN {' . db_table::ACTIVITY_TASK->value . '} t ON t.id = c.taskid ' .
                "JOIN {user} u ON u.id = c.userid {$userfieldsql->joins}",
            $where,
            array_merge($userfieldsql->params, $params)
        );

        $this->sortable(true, 'lastname', SORT_ASC);
        $this->no_sorting('details');
        $this->collapsible(false);
    }

    /**
     * Defines the base URL of this table, retaining the current filter parameters
     *
     * @param string|\moodle_url $url Base URL of the table
     * @return void
     * @throws \moodle_exception
     */
    public function define_baseurl($url) {
        $url = new \moodle_url($url);
        $url->remove_params(...self::FILTER_PARAMS);
        if ($this->userfilter !== null) {
            $url->param('userid', $this->userfilter->id);
        }

        parent::define_baseurl($url);
    }

    /**
     * Renders the user filter form, followed by the table itself
     *
     * The filter form is printed here, instead of in wrap_html_start(), to
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
     * Queries the contents of the current page and resolves their labels at once
     *
     * @param int $pagesize Number of rows per page
     * @param bool $useinitialsbar Whether to calculate the initials bar
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        parent::query_db($pagesize, $useinitialsbar);

        $metadata = [];
        foreach ($this->rawdata as $row) {
            $metadata[] = new task_content_metadata(
                taskid: $row->taskid,
                userid: $row->userid,
                reftable: $row->reftable,
                refid: $row->refid,
                summary: $row->summary,
                id: $row->id
            );
        }

        if ($this->driver !== null) {
            $this->labels = $this->driver->get_task_content_metadata_labels($metadata);
        } else {
            // Fallback label.
            foreach ($metadata as $entry) {
                $this->labels[$entry->id] = (object) [
                    'label' => $entry->summary ?: "{$entry->reftable} #{$entry->refid}",
                    'url' => null,
                    'details' => null,
                ];
            }
        }
    }

    /**
     * Column renderer for the fullname / user column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_fullname($values) {
        global $OUTPUT;

        // Handle deleted users.
        if ($values->deleted) {
            return \html_writer::span(get_string('userdeleted', 'moodle'), 'text-muted fst-italic font-italic');
        }

        // Display basic user picture and name, linking to the user's profile.
        $html = $OUTPUT->user_picture(\user_picture::unalias($values, null, 'userid'), ['size' => 35, 'link' => false]);
        $html .= \html_writer::link(
            new \moodle_url('/user/view.php', ['id' => $values->userid, 'course' => $this->ctx->get_course_context()->instanceid]),
            fullname($values, $this->viewfullnames)
        );

        // Show identity fields (e.g., email address) if present.
        $identity = [];
        foreach ($this->extrafields as $extrafield) {
            if (!empty($values->$extrafield)) {
                $identity[] = s($values->$extrafield);
            }
        }
        if (!empty($identity)) {
            $html .= \html_writer::tag('small', '(' . implode(', ', $identity) . ')', ['class' => 'ml-1 text-muted']);
        }

        return $html;
    }

    /**
     * Column renderer for the refid / archived object column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     */
    public function col_refid($values) {
        $label = $this->labels[$values->id];
        if ($label->url === null) {
            return s($label->label);
        }

        return \html_writer::link($label->url, s($label->label));
    }

    /**
     * Column renderer for the details column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     */
    public function col_details($values) {
        // Details are trusted HTML provided by the activity archiving driver.
        return $this->labels[$values->id]->details ?? '';
    }

    /**
     * Renders the user filter form for this table
     *
     * @return string HTML code of the filter form
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function render_search_form(): string {
        global $OUTPUT;

        // Preserve all page parameters except filter and pagination.
        $hiddenparams = [];
        $excludedparams = [...self::FILTER_PARAMS, $this->request[TABLE_VAR_PAGE]];
        foreach ($this->baseurl->params() as $name => $value) {
            if (!in_array($name, $excludedparams, true)) {
                $hiddenparams[] = ['name' => $name, 'value' => $value];
            }
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

        return $OUTPUT->render_from_template('local_archiving/components/job_content_table_search', [
            'uniqueid' => $this->uniqueid,
            'contextid' => $this->ctx->id,
            'actionurl' => $this->baseurl->out_omit_querystring(),
            'hiddenparams' => $hiddenparams,
            'selecteduser' => $selecteduser,
            'filteractive' => $this->userfilter !== null,
            'reseturl' => $reseturl->out(false),
        ]);
    }
}
