# Changelog

## Version X.Y.Z (YYYYMMDDXX)

Listed changes are split into categories, reflecting the affected component / (sub-) plugin.

### Archiving Core (`local_archiving`)

- Exclude sub-plugins from archiving core PHPUnit coverage calculations.
- Extend PHPUnit tests to cover latest additions.

### Sub-Plugins

#### Activity Archiving Driver: Quiz (`archivingmod_quiz`)

#### Activity Archiving Driver: Assign (`archivingmod_assign`)

#### Storage Driver: Local Directory (`archivingstore_localdir`)

#### Storage Driver: Moodle Filestore (`archivingstore_moodle`)

#### Archiving Trigger: Manual (`archivingtrigger_manual`)

#### Archiving Trigger: Scheduled (`archivingtrigger_cron`)

#### External Event Connector: API Stub (`archivingevent_apistub`)


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
