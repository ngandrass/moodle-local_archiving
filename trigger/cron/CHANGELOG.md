# Changelog

## Version X.Y.Z (YYYYMMDDXX)

- Adapt to archiving core refactoring. Now requires `local_archiving` version `2026073000` or higher.
- Adapt unit test to Moodle upstream permission check changes


## Version 1.0.0 (2025101300)

- Automatically create archive jobs for all activities that have unarchived changes and are located within any of the
  specified course categories for archiving.
- Configurable time interval for how often the automatic archiving process should run.
- Dry-run mode to simulate the automatic archiving process without actually creating any archive jobs.
- Implementation of the privacy provider class
- Definition of unit tests
- Add Moodle plugin CI for all supported Moodle versions
