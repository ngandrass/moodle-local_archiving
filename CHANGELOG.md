# Changelog

## Version X.Y.Z (YYYYMMDDNN)

- Ensure compatibility with Moodle 5.3
- Fix archive job file attributes list styles in Moodle 5.3
- Renovate moodle-plugin-ci GitHub action workflow
- Add job fingerprints to identify archive jobs with identical course, activity, and settings


## Version 1.1.0 (2026100300)

This is the first big release after the v1.0.0. It features support for archiving assignment activities, a lot of
improvements under the hood, a simplified repository structure, and a largely extended documentation 🎉

Listed changes are split into categories, reflecting the affected component / (sub-) plugin.

### Archiving Core (`local_archiving`)

- Add an option to flatten the archive structure, placing all files directly in the root directory of the archive.
- Add support for receiving chunked uploads of archive data from worker services
- Asynchronously retrieve files from remote storages (e.g., S3 object store) to allow downloading via the Moodle UI
- Add callback hooks to store and retrieve functions of storage drivers to allow for progress tracking
- Harden file retrieval logic
- Validate file contents against stored checksum when retrieving files from storage to ensure data integrity
- Let timed out archive jobs remain marked as "Timeout" instead of "Failed" (both are final job states)
- Fix error reporting when forcefully accessing the archive job artifacts download page of an unfinished job
- Improve handling of duplicate files in archives
- Fix display of archiving overview pages for courses that have no supported activities
- Support course module / activity level archiving capability assignments
- Deny archive job / file deletion by default (capability: `local/archiving:delete`)
- Disable delete button in job overview table and on file download page for users that do not possess the `local/archiving:delete` capability
- Hide archive job creation form for users that don't have the capability to create new archive jobs (`local/archiving:create`)
- Display missing job / file delete permission errors early in forms and redirect back to the correct page
- Provide proper info message when a course contains no supported activities for archiving
- Move sub-plugin directories to plugin root to align with Moodle core conventions
- Restructure repository to meet Moodle coding style level 2 namespace suggestions
- Improve Moodle "pluginfile image" inlining logic in report generators
- Improve archive job logging in storing stage
- Prevent log messages of long-running archive jobs from being displayed truncated
- Clear local artifact cache right away during artifact deletion instead of waiting for the next housekeeping task
- Ensure proper cleanup of temporary files and data on archive job failure at every stage
- Gracefully terminate job processing ad-hoc tasks when an archive job is deleted before it has finished
- Prevent archive job creation with negative retention time values
- Fix race condition between job initialization and ad-hoc cron task execution
- Enable archiving jobs to be rescheduled for immediate execution
- Gracefully fail during chunked-upload reassembly if chunks are missing
- Provide common paper-format based scaling suggestions for correction margins inside activity reports
- Apply enabled filters to course module names during rendering
- Localize activity names in archive job creation form titles
- Display timestamps in archive job overview table and job log in the timezone of the user viewing the page
- Improve descriptions on archiving overview and job creation pages
- Fix job overview table action button tooltips for Moodle 5.x
- Fix activity list status pills display for Moodle 5.x
- Protect sub-plugin enable / disable endpoint from CSRF
- Fix type confusion in default archive job settings exports
- Remove TSP data on file handle deletion
- Fix archive jobs from other courses / activities being listed on the archive job overview on context path collisions
- Prevent archiving path admin settings from accepting paths inside the Moodle web root directory
- Resolve symlinks during archiving path admin setting validation to always validate the real path
- Add all used subsystems to privacy provider
- Add archive file metadata table to privacy provider
- Update Moodle plugin CI to include Moodle 5.2 and all supported PHP versions
- Add sub-plugin PHPUnit test execution stage to Moodle plugin CI pipeline
- Include core sub-plugins in PHPUnit coverage reports
- Adapt unit tests to Moodle upstream permission checks
- Make unit tests ready for PHPUnit 12 (honor current deprecations)

### Sub-Plugins

#### Activity Archiving Driver: Assign (`archivingmod_assign`)

- Implement full assignment submission archiving pipeline: submission report generation, metadata retrieval, and status reporting
- Make submission reports configurable with various sections (header, instructions, submission, comments, feedback, grading details, ...)
- Allow configurable file attachment handling (assignment, submission, feedback, annotation files) with per-type selection
- Provide machine-readable assignment submissions metadata export in CSV format
- Add folder name and file name pattern generation for archived submissions
- Allow to decide between flat and hierarchical archive folder structure
- Re-use existing Moodle archiving worker service for report generation
- Finalize Moodle privacy API provider
- Rename dependency from moodle-quiz-archive-worker to moodle-archiving-worker

#### Activity Archiving Driver: Quiz (`archivingmod_quiz`)

- Allow exporting only the latest quiz attempt of each user in the generated archive
- Add support for receiving chunked uploads to enable the transfer of large quiz archives independent of the upload limit
- Add support for question type [JACK](https://github.com/Wunderbyte-GmbH/moodle_qtype_jack).
- Add "Question internals" attempt report section, showing question ID, question bank version, ID number, and tags above each question
- Add attempt report setting for showing / hiding overall quiz grade
- Add attempt report setting for showing / hiding question correctness indicators
- Add attempt report setting for showing / hiding raw marks for questions
- List all defined grade items individually below the overall quiz grade in attempt report headers
- Create advanced job option to add correction margins to the right side of generated attempt reports
- Display the attempting user's email address inside the attempt report header
- Add `${email}` variable for attempt file- and folder name patterns
- Add human-readable date and time variables (`YYYY-MM-DD_HH-MM-SS`) for attempt file- and folder name patterns:
    - `${opendatetime}`: Quiz opening date and time
    - `${closedatetime}`: Quiz closing date and time
    - `${startdatetime}`: Attempt start date and time
    - `${finishdatetime}`: Attempt finish date and time
- Include user email address in attempt metadata queries and the `get_attempts_metadata` web service response
- Add an option to include or exclude the quiz attempts metadata CSV file
- Automatically reschedule archive job for immediate execution if the worker service finished successfully
- Fix rendering of overall quiz feedback
- Force wrapping of long lines in code boxes to prevent overflowing out of page boundaries
- Reduce padding of comment boxes within code boxes to prevent them from overlapping student code
- Optimize main report container spacing to reduce the amount of whitespace in the generated PDF
- Prevent instance-specific modifications to Moodle header and footer from leaking into printed PDFs (thanks to @abias !)
- Fix bug in dynamic file and folder name validation
- Fix `taskid` parameter type (was string, now int) in `process_uploaded_artifact` web service function
- Setup course and module in `$PAGE` object during `generate_attempt_report` web service function
- Forcefully disable unlocked attempt report sections that depend on another disabled section
- Migrate quiz attempt renderer to new quiz attempt summary API
- Adapt to archiving core refactoring. Now requires `local_archiving` version `2026100300` or higher
- Ensure Moodle 5.2 compatibility
- Add archive worker service link to privacy provide

#### Storage Driver: Local Directory (`archivingstore_localdir`)

- Install as disabled by default since this plugin requires configuration prior to use.
- Implement store and retrieve callback hooks.
- Honor `$CFG->directorypermissions` Moodle config value on target directory creation
- Adapt to archiving core refactoring. Now requires `local_archiving` version `2026082900` or higher.
- Ensure Moodle 5.2 compatibility
- Describe files stored by this plugin in privacy provider

#### Storage Driver: Moodle Filestore (`archivingstore_moodle`)

- Implement store and retrieve callback hooks.
- Fix missing language string on file storage failure.
- Adapt to archiving core refactoring. Now requires `local_archiving` version `2026082900` or higher.
- Ensure Moodle 5.2 compatibility
- Describe files stored by this plugin in privacy provider

#### Storage Driver: S3 Obejct Store (`archivingstore_s3`)

- Initial release of the S3 storage driver for the Moodle archiving subsystem 🎉
- Implements store, retrieve, and delete functionality.
- Supports asynchronous file retrieval from object storage.
- Periodically report upload progress to job log during processing.
- Automatically test S3 connection and bucket access rights during plugin configuration.
- Detection and handling of stalled up- and downloads

#### Archiving Trigger: Manual (`archivingtrigger_manual`)

- Adapt to archiving core refactoring. Now requires `local_archiving` version `2026082900` or higher.
- Ensure Moodle 5.2 compatibility

#### Archiving Trigger: Scheduled (`archivingtrigger_cron`)

- Install trigger in a disabled state
- Enable dry-run mode at installation
- Adapt to archiving core refactoring. Now requires `local_archiving` version `2026082900` or higher.
- Adapt unit test to Moodle upstream permission check changes
- Ensure Moodle 5.2 compatibility


## Version 1.0.0 (2025112300)

This is the first stable release of the archiving subsystem including all shipped sub-plugins 🎉

Listed changes are split into categories, reflecting the affected component / (sub-) plugin.

### Archiving Core (`local_archiving`)

- Display warning message when sub-plugins are configured in a way that no archiving can be performed (e.g., no storage
  driver is enabled)
- Display info message when no sup-plugins of a certain type are installed on manage components admin page
- Show archiving core plugin version on manage components admin page
- Remove alpha sub-plugins from core distribution
- Adapt unit tests to detect optional sub-plugins


## Version 0.5.0 (2025102700)

Listed changes are split into categories, reflecting the affected component / (sub-) plugin.

### Archiving Core (`local_archiving`)

- Automatic deletion of archive job artifacts after a configurable retention period.
- Display of file-specific retention information on the archive job artifacts download page.
- Implement Moodle privacy API provider
- Provide admin setting component that checks and helps with setting up the Moodle web service component for worker service communication
- Clean up issued web service tokens prior to timeout once an activity archiving task reached a final state


### Sub-Plugins

#### Activity Archiving Driver: Quiz (`archivingmod_quiz`)

- First stable release 🎉
- Simplify web service setup process
    - Bundle web service functions for worker communication inside a statically provided web service
    - Remove superfluous admin settings for manual web service setups
    - Remove superfluous autoinstall feature that was superseded by the statically provided web service
- Finalize task flow logic for activity archiving tasks
- Finalize Moodle privacy API provider
- Adapt web service unit tests to latest activity archiving task access token invalidation behavior
- Fix language strings in job creation form validator
- Create unit tests for various miscellaneous components

#### Activity Archiving Driver: Assign (`archivingmod_assign`)

- Add Moodle privacy API stub provider

#### External Event Connector: API Stub (`archivingevent_apistub`)

- Add Moodle privacy API provider


## Version 0.4.0 (2025101300)

Listed changes are split into categories, reflecting the affected component / (sub-) plugin.

### Archiving Core (`local_archiving`)

- Add Moodle 5.1 with all supported PHP versions as well as pgsql and mariadb to CI testing matrix.
- Refactor code to comply with new Moodle coding standard v3.6
- Exclude sub-plugins from CI coding style checks since they have their own CI pipelines


### Sub-Plugins

#### Activity Archiving Driver: Quiz (`archivingmod_quiz`)

- Ensure Moodle 5.1 compatibility
- Add missing language strings
- Refactor code to comply with new Moodle coding standard v3.6
- Fix import of legacy compatibility layers in unit tests
- Fix unit test for archive task status update web service function

#### Activity Archiving Driver: Assign (`archivingmod_assign`)

- Ensure Moodle 5.1 compatibility
- Refactor code to comply with new Moodle coding standard v3.6

#### Storage Driver: Local Directory (`archivingstore_localdir`)

- Ensure Moodle 5.1 compatibility
- Refactor code to comply with new Moodle coding standard v3.6
- Clean up empty subdirectories during file deletion
- Add privacy provider class
- Create unit tests

#### Storage Driver: Moodle Filestore (`archivingstore_moodle`)

- Implement store, retrieve, and delete functionality using the Moodle Filestore backend
- Check free space in the moodledata directory to determine storage availability
- Ensure Moodle 5.1 compatibility
- Refactor code to comply with new Moodle coding standard v3.6
- Add privacy provider class
- Create unit tests

#### Archiving Trigger: Manual (`archivingtrigger_manual`)

- Ensure Moodle 5.1 compatibility
- Refactor code to comply with new Moodle coding standard v3.6
- Add privacy provider class

#### Archiving Trigger: Scheduled (`archivingtrigger_cron`)

- Ensure Moodle 5.1 compatibility
- Refactor code to comply with new Moodle coding standard v3.6
- Implementation of the privacy provider class
- Definition of unit tests

#### External Event Connector: API Stub (`archivingevent_apistub`)

- Ensure Moodle 5.1 compatibility
- Refactor code to comply with new Moodle coding standard v3.6


## Version 0.3.0 (2025101200)

Listed changes are split into categories, reflecting the affected component / (sub-) plugin.

### Archiving Core (`local_archiving`)

- Make `archivingstore_moodle` the default storage plugin for new installations.
- Add method to determine number of currently running and pending archive jobs for a given course module.
- Fix storage of course category IDs for archiving scope selection.
- Fix loading of components management admin setting from other contexts.
- Extend PHPUnit tests to cover latest features and other parts of the archiving core.
- Exclude sub-plugins from archiving core PHPUnit coverage calculations.
- Add missing language strings for storage tier descriptions.

### Sub-Plugins

No sub-plugin changes in this release.


## Version 0.2.0 (2025092100)

Listed changes are split into categories, reflecting the affected component / (sub-) plugin.

### Archiving Core (`local_archiving`)

- Create a global course category whitelist that allows to enable / disable archiving for courses, based on the category
  they belong to.
- Introduce a new capability (`local/archiving:bypasscourserestrictions`) to allow certain users to bypass any course
  category restrictions and create new archives nonetheless.
- Define archiving trigger sub-plugin interface for creating new archive jobs.
- Store and display the trigger source for archive jobs.
- Introduce activity fingerprints to determine if an activity has changed since the last successful archiving job.
- Show a warning badge in the archiving overview page if an activity was previously archived but had changes since the
  last run.
- Make the number of archive jobs that can actively be run in parallel configurable via an admin setting. Once the
  concurrency limit is reached, new jobs can still be queued but won't be executed until at least one active job
  finishes.
- Create help tooltips for all status badges on the activity archiving overview page.
- Hide unsupported or disabled activities from the archiving overview page by default. Users can still list all
  activities using the button below the table.
- Replace list of activities with a info message if archiving is disabled for this course / category.
- Log activity fingerprints during archiving and log an info message if a duplicate archive is about to be created.
- Fix archive job progress indicator tooltip text
- Fix database field type for archive job progress
- Add created default archive trigger plugins to plugin overview in the docs
- Add archiving trigger sub-plugin component and API descriptions to developer docs
- Improve speed of artifact file SHA256 hash generation
- Exclude deleted files from storage stats counters on components overview page


### Sub-Plugins

#### Activity Archiving Driver: Quiz (`archivingmod_quiz`)

- Implement course module state fingerprinting based on quiz and attempt modification times
- Adapt test data generator to new archiving trigger API
- Add Moodle plugin CI for all supported Moodle versions

#### Activity Archiving Driver: Assign (`archivingmod_assign`)

- Add stub implementation for cm state fingerprinting
- Add Moodle plugin CI for all supported Moodle versions

#### Storage Driver: Local Directory (`archivingstore_localdir`)

- Add Moodle plugin CI for all supported Moodle versions

#### Storage Driver: Moodle Filestore (`archivingstore_moodle`)

- Add Moodle plugin CI for all supported Moodle versions

#### Archiving Trigger: Manual (`archivingtrigger_manual`)

- Create plain archiving trigger for manually creating archive jobs via the UI of the core component
- Add Moodle plugin CI for all supported Moodle versions

#### Archiving Trigger: Scheduled (`archivingtrigger_cron`)

- Automatically create archive jobs for all activities that have unarchived changes and are located within any of the
  specified course categories for archiving.
- Configurable time interval for how often the automatic archiving process should run.
- Dry-run mode to simulate the automatic archiving process without actually creating any archive jobs.
- Add Moodle plugin CI for all supported Moodle versions

#### External Event Connector: API Stub (`archivingevent_apistub`)

- Add Moodle plugin CI for all supported Moodle versions


## Version 0.1.0 (2025081900)

This is the initial alpha release of the archiving subsystem core. From now on,
all changes will be tracked here and proper releases will be published 🚀

Please note that sub-plugin APIs are still subject to change until the first
stable version (`v1.0.0`) is released!
