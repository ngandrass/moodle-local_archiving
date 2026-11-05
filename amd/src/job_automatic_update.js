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
import {getString} from 'core/str';
import UserDate from 'core/user_date';
import Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * Polling interval for status updates.
 *
 * The archive worker typically updates the status every 15 seconds. To avoid
 * aliasing it polls at double that frequency. It is delibertly a multiple of
 * two seconds, since the spinner animation takes that long for one rotation.
 */
const JOB_STATUS_POLLING_MS = 6000;


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
 * Builds a CSS selector that targets a specific updatable element.
 *
 * @param {string} jobId The ID of the job the targeted element belongs to
 * @param {string} ref The reference of target updatable element
 * @returns {string} A CSS selector matching the targeted element
 */
const getElementSelector = (jobId, ref) => `[data-jobid="${jobId}"][data-ref="${ref}"]`;

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
        '[data-ref="status"]:not([data-finalized])',
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

            // Update all per-page elements of this job in parallel.
            await Promise.all([
                renderStatus(jobMetadata.job),
                renderActivity(jobMetadata.job),
                renderTimeModified(jobMetadata.job),
                renderLogs(jobMetadata.job),
            ]);

            // Queue any unfinished job for next polling cycle.
            if (!jobMetadata.job.complete) {
                pendingJobIds.push(jobId);
                continue;
            }

            // Job is complete, update job overview table action and metadata table.
            await Promise.all([
                renderDownloadAction(jobMetadata.job),
                renderMetadata(jobMetadata.job),
            ]);
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
    const elementSelector = getElementSelector(job.id, "status");
    const statusDisplayArgs = job.status_display_args;
    const layout = document.querySelector(elementSelector)?.dataset.layout ?? null;

    let templateContext = {
        "badge_text": statusDisplayArgs.text,
        "badge_color": statusDisplayArgs.color,
        "tooltip_text": statusDisplayArgs.help,
        "progress": job.progress ?? null,
        "has_progress": (job.progress ?? null) !== null && job.progress < 100,

        // Omit modified time when the vertical layout is used.
        // NOTE: This is ugly but wins a battle with the linting gods, that do
        //       neither accept bracket notation nor camel case identifiers.
        "layout_vertical": (layout == "vertical") ? true : null,
        "time_modified": (layout == "vertical") ? job.timemodified : null,
    };

    await Templates.renderForPromise(
        'local_archiving/components/job_status',
        templateContext,
    ).then(
        ({html, js}) => {
            Templates.replaceNodeContents(elementSelector, html, js,);
            return;
        }
    ).catch(
        (error) => {
            window.console.error("Template render failure for status colum:", error);
            Notification.exception(error);
        }
    );
};

/**
 * Re-renders the activity column for the given job with its freshly fetched
 * data.
 *
 * @param {object} job The job data as returned by the webservice to update
 * @returns {Promise<void>}
 */
const renderActivity = async(job) => {
    const elementSelector = getElementSelector(job.id, 'activity');

    // In case no such element exists, exit early.
    if (!document.querySelector(elementSelector)) {
        return;
    }

    await Templates.renderForPromise(
        'local_archiving/components/job_activity',
        {
            "cm_url": job.action_urls.cm,
            "cm_name": job.cm_name,
            "job_url": job.action_urls.job,
            "content_count": (job.content_count ?? 0) > 0 ? job.content_count : null,
            "content_is_multiple": job.content_count > 1,
            "files": job.files ?? [],
        },
    ).then(({html, js}) => {
        Templates.replaceNodeContents(
            elementSelector,
            html,
            js,
        );
        return;
    }).catch((error) => {
        window.console.error('Template render failure for activity column:', error);
        Notification.exception(error);
    });
};

/**
 * Re-renders any "last modified" element related to provided job with its
 * freshly fetched data.
 *
 * @param {object} job The job data as returned by the webservice to update
 * @returns {Promise<void>}
 */
const renderTimeModified = async(job) => {
    const timemodifiedElement = document.querySelector(
        getElementSelector(job.id, "timemodified")
    );
    // NOTE: In case no such element exists, exit early.
    if (!timemodifiedElement) {
        return;
    }
    getString('strftimedatetimeaccurate', 'core_langconfig').then(
        (formatString) => UserDate.get(
            [{'timestamp': job.timemodified, 'format': formatString}]
        )
    ).then((formattedDates) => {
        timemodifiedElement.textContent = formattedDates[0];
        return;
    }).catch((error) => {
        window.console.error("Could not format date:", error);
        Notification.exception(error);
    });
};

/**
 * Re-renders the logs element content related to provided job with its freshly
 * fetched data.
 *
 * @param {object} job The job data as returned by the webservice to update
 * @returns {Promise<void>}
 */
const renderLogs = async(job) => {
    const logsElement = document.querySelector(
        getElementSelector(job.id, "logs-content")
    );
    // In case no such element exists, exit early.
    if (!logsElement) {
        return;
    }

    logsElement.textContent = job.logs ?? "";

    // If job is complete, remove pending dots.
    if (job.complete) {
        const logsPendingElement = document.querySelector(
            getElementSelector(job.id, "logs-pending")
        );
        if (logsPendingElement) {
            logsPendingElement.innerHTML = "";
        }
    }

    return;
};

/**
 * Re-renders the download action button for the given job with its freshly
 * fetched data.
 *
 * @param {object} job The job data as returned by the webservice to update
 * @returns {Promise<void>}
 */
const renderDownloadAction = async(job) => {
    const elementSelector = getElementSelector(job.id, 'action-download');

    // In case no such element exists, exit early.
    if (!document.querySelector(elementSelector)) {
        return;
    }

    const hasurl = (job.action_urls?.download ?? null) !== null;

    // NOTE: The button class, icon and label are fixed; only the action URL is
    //       provided by the webservice and only when the download is available.
    const templateContext = {
        label: hasurl
            ? await getString('download')
            : await getString('files_require_successful_job', 'local_archiving'),
        btnclass: hasurl ? 'btn-success' : 'btn-outline-success',
        icon: 'fa-download',
    };
    if (hasurl) {
        templateContext.url = job.action_urls.download;
    }

    await Templates.renderForPromise(
        'local_archiving/components/action_button',
        templateContext,
    ).then(({html, js}) => {
        Templates.replaceNodeContents(elementSelector, html, js);
        return;
    }).catch((error) => {
        window.console.error('Template render failure for download action:', error);
        Notification.exception(error);
    });
};

/**
 * Re-renders a jobs metadata table for the given job with its freshly fetched
 * data.
 *
 * @param {object} job The job data as returned by the webservice to update
 * @returns {Promise<void>}
 */
const renderMetadata = async(job) => {
    const elementSelector = getElementSelector(job.id, 'metadata');

    // In case no such element exists, exit early.
    if (!document.querySelector(elementSelector)) {
        return;
    }

    await Templates.renderForPromise(
        'local_archiving/components/job_metadata',
        {
            job: {
                'metadata': job.metadata ?? [],
            }
        },
    ).then(({html, js}) => {
        Templates.replaceNodeContents(elementSelector, html, js);
        return;
    }).catch((error) => {
        window.console.error('Template render failure for metadata table:', error);
        Notification.exception(error);
    });
};
