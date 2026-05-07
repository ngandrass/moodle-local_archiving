<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Cron-based archiving trigger plugin
 *
 * @package     archivingtrigger_cron
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingtrigger_cron;

use core_course_category;
use local_archiving\archive_job;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Cron-based archiving trigger plugin
 */
class archivingtrigger extends \local_archiving\driver\archivingtrigger {
    /**
     * Retrieves all course modules that should be archived
     *
     * @param bool $includeunchanged Whether to include unchanged activities as well
     * @return array List of course modules with metadata
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_cms_to_archive(bool $includeunchanged = false): array {
        $targetcatids = \local_archiving\util\course_util::get_archivable_course_category_ids();

        $res = [];
        foreach ($targetcatids as $catid) {
            $cat = core_course_category::get($catid);
            mtrace("↦ Course category: {$cat->get_formatted_name()} (ID: {$cat->id})");

            $courses = $cat->get_courses();
            foreach ($courses as $course) {
                mtrace(" ↳ Course: {$course->get_formatted_name()} (ID: {$course->id})");
                $cmsmeta = $this->get_course_cms_with_metadata($course->id);

                foreach ($cmsmeta as $cmmeta) {
                    $prettyname = "{$cmmeta->cm->name} (ID: {$cmmeta->cm->id})";
                    if ($cmmeta->supported && $cmmeta->enabled && $cmmeta->ready) {
                        if ($cmmeta->dirty) {
                            if (archive_job::get_incomplete_job_count_for_context($cmmeta->cm->context)) {
                                mtrace("   ↳ 🔃 [RUNNING] {$prettyname}");
                            } else {
                                mtrace("   ↳ ⏳ [ARCHIVE] {$prettyname}");
                                $res[] = $cmmeta;
                            }
                        } else if ($includeunchanged) {
                            mtrace("   ↳ ⚠️ [FORCE] {$prettyname}");
                            $res[] = $cmmeta;
                        } else {
                            mtrace("   ↳ ✅ [SKIP] {$prettyname}");
                        }
                    } else {
                        mtrace("   ↳ ❌ [IGNORE] {$prettyname}");
                    }
                }
            }
        }

        return $res;
    }

    /**
     * Retrieves all course modules of the given course with archiving subsystem
     * metadata attached.
     *
     * Note: This is primarily here to allow mocking in unit tests.
     *
     * @param int $courseid ID of the course to get CMs for
     * @return array List of course modules with metadata
     * @throws \moodle_exception
     */
    protected function get_course_cms_with_metadata(int $courseid): array {
        return \local_archiving\util\mod_util::get_cms_with_metadata($courseid);
    }

    /**
     * Initiates a new archive job for the given course module using default values.
     *
     * @param \cm_info $cm Course module to archive
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_archive_job(\cm_info $cm): void {
        global $PAGE;
        $PAGE->set_url('/');  // Set page URL to dummy value to prevent errors from mform.

        // Get appropriate job create form and retrieve default settings.
        $driver = \local_archiving\driver\factory::activity_archiving_driver($cm->modname, $cm->context);
        $form = $driver->get_job_create_form($cm->modname, $cm);
        $jobsettings = $form->export_raw_data();

        // Create new archive job for course module.
        $job = \local_archiving\archive_job::create($cm->context, get_admin()->id, 'cron', $jobsettings);
        $job->enqueue();
    }
}
