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
 * Interactive behavior for any job related UI with changing informationn over
 * time.
 *
 * @module     local_archiving/job_automatic_update
 * @author     Björn Dittmann <bjoern.dittmann@haw-hamburg.de>
 * @copyright  2026 Niels Gandraß <niels@gandrass.de>
 * @license    http://www.gnu.org/licenses/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * Polling interval for status updates.
 *
 * The archive worker typically updates the status every 15 seconds. To avoid
 * aliasing it polls at double that frequency. It is delibertly a multiple of
 * two seconds, since the spinner animation takes that long for one rotation.
 */
let JOB_STATUS_POLLING_MS = 6000;


/**
 * Initializes automatic job status polling for the quiz archiver report page.
 *
 * Scans the job overview table for jobs that have not reached a final state
 * yet and, if any are found, schedules the first status update.
 *
 * @returns {void}
 */
export const init = () => {
    const jobIds = getUnfinishedJobIds();

    if (jobIds) {
        scheduleJobUpdate(jobIds);
    }
};

/**
 * Builds a CSS selector that targets a specific updatable span element.
 *
 * @param {string} jobId The ID of the job the targeted element belongs to
 * @param {string} ref The reference of target updatable span element
 * @returns {string} A CSS selector matching the targeted span
 */
const getSpanSelector = (jobId, ref) => `span[data-jobid="${jobId}"][data-ref="${ref}"]`;

/**
 * Collects the IDs of all jobs whose status is not yet finalized.
 *
 * A job is considered unfinished as long as its status cell does not carry
 * the "data-finalized" marker, which is set server-side on initial rendering.
 *
 * @returns {string[]|null} Array of unfinished job IDs, or null if none are found
 */
const getUnfinishedJobIds = () => {
    const statusElements = document.querySelectorAll(
        'span[data-ref="status"]:not([data-finalized])',
    );

    let unfinishedJobIds = [];
    statusElements.forEach(statusElement => {
        const jobId = statusElement.dataset.jobid;
        unfinishedJobIds.push(jobId);
    });

    if (unfinishedJobIds.length == 0) {
        unfinishedJobIds = null;
    }

    return unfinishedJobIds;
};

/**
 * Schedules a status update for the given jobs to run after the polling interval.
 *
 * @param {string[]} jobIds The IDs of the jobs to update
 * @returns {void}
 */
const scheduleJobUpdate = (jobIds) => {
    setTimeout(updateJobs, JOB_STATUS_POLLING_MS, jobIds);
};

/**
 * Performs one polling round for all given jobs.
 *
 * For each job the current status is fetched and the status cell is re-rendered.
 * As soon as a job completed, its filesize and action cells are updated as well.
 * If any jobs are still running, another polling round is scheduled. Jobs whose
 * status could not be retrieved are dropped from polling to prevent repeated
 * error notifications.
 *
 * @param {string[]} jobIds The IDs of the jobs to update
 * @returns {Promise<void>}
 */
const updateJobs = async(jobIds) => {
    // Jobs that have not reached a final state yet and need to be polled again.
    const pendingJobIds = [];

    for (const jobId of jobIds) {
        try {
            // Get latest job data. Stop polling this job if it could not be retrieved.
            const jobMetadata = await getJobMetadata(jobId);
            if (!jobMetadata) {
                continue;
            }

            // Update any per page status element of this job.
            await renderStatus(jobMetadata.job);

            // Queue any unfinished job for next polling cycle.
            if (!jobMetadata.job.complete) {
                pendingJobIds.push(jobId);
                continue;
            }
        } catch (error) {
            window.console.error(`Failed to update job ${jobId}:`, error);
            Notification.exception(error);
        }
    }

    // Stop polling when no unfinished jobs are left.
    if (pendingJobIds.length > 0) {
        scheduleJobUpdate(pendingJobIds);
    }
};

/**
 * Fetches the current status of a single job from the "local_archiving_get_job_metadata"
 * webservice.
 *
 * Failures are reported to the user via a notification.
 *
 * @param {string} job The ID of the job to fetch the status for
 * @returns {Promise<object|null>} The webservice response containing "status"
 * and "job", or null if the webservice call failed or returned a non-"OK" status
 */
const getJobMetadata = async(job) => {
    try {
        // Background polling must not refresh the session. Otherwise, an open
        // report page would keep the user logged in until all jobs finished.
        const data = await Ajax.call(
            [{
                methodname: 'local_archiving_get_job_metadata',
                args: {jobid: job},
            }],
            true, // Async.
            true, // Login required.
            true, // No session update.
        )[0];

        if (data.status !== 'OK' || !data.job) {
            throw Error(`Failed to retrieve job status: ${data.status}`);
        }

        return data;
    } catch (error) {
        window.console.error('Ajax call failed with error:', error);
        Notification.exception(error);
        return null;
    }
};

/**
 * Re-renders any status element related to provided job with its freshly
 * fetched data.
 *
 * @param {object} job The job data as returned by the webservice to update
 * @returns {Promise<void>}
 */
const renderStatus = async(job) => {
    const spanSelector = getSpanSelector(job.id, "status");
    const statusDisplayArgs = job.status_display_args;

    let templateContext = {
        "badge_text": statusDisplayArgs.text,
        "badge_color": statusDisplayArgs.color,
        "tooltip_text": statusDisplayArgs.help,
        "progress": job.progress ?? null,
        "has_progress": (job.progress ?? null) !== null && job.progress < 100,
    };

    // Omit modified time when the vertical layout is used.
    const statusElement = document.querySelector(spanSelector);
    if ((statusElement.dataset.layout ?? null) == "vertical"){
        templateContext["layout_vertical"] = true;
        templateContext["time_modified"] = job.timemodified;
    }

    await Templates.renderForPromise(
        'local_archiving/components/job_status',
        templateContext,
    ).then(
        ({html, js}) => {
            Templates.replaceNodeContents(spanSelector, html, js,);
            return;
        }
    ).catch(
        (error) => {
            window.console.error("Template render failure for status colum:", error);
            Notification.exception(error);
        }
    );
};
