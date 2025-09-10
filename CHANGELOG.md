# Changelog

## Version X.Y.Z (YYYYMMDDXX)

- Introduce activity fingerprints to determine if an activity has changed since the last successful archiving job.
- Show a warning badge in the archiving overview page if an activity was previously archived but had changes since the
  last run.
- Create help tooltips for all status badges on the activity archiving overview page.
- Hide unsupported or disabled activities from the archiving overview page by default. Users can still list all
  activities using the button below the table.
- Log activity fingerprints during archiving and log an info message if a duplicate archive is about to be created.


## Version 0.1.0 (2025081900)

This is the initial alpha release of the archiving subsystem core. From now on,
all changes will be tracked here and proper releases will be published 🚀

Please note that sub-plugin APIs are still subject to change until the first
stable version (`v1.0.0`) is released!
