# Changelog

## Version X.Y.Z (YYYYMMDDXX)

Listed changes are split into categories, reflecting the affected component / (sub-) plugin.

### Archiving Core (`local_archiving`)

- Remove alpha sub-plugins from core distribution
- Adapt unit tests to detect optional sub-plugins


### Sub-Plugins

#### Activity Archiving Driver: Quiz (`archivingmod_quiz`)

-

#### Storage Driver: Local Directory (`archivingstore_localdir`)

- 

#### Storage Driver: Moodle Filestore (`archivingstore_moodle`)

- 

#### Archiving Trigger: Manual (`archivingtrigger_manual`)

- 

#### Archiving Trigger: Scheduled (`archivingtrigger_cron`)

- 


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
