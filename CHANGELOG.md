# Changelog

## Version X.Y.Z (YYYYMMDDXX)

- Create a global course category whitelist that allows to enable / disable archiving for courses, based on the category
  they belong to.
- Introduce a new capability (`local/archiving:bypasscourserestrictions`) to allow certain users to bypass any course
  category restrictions and create new archives nonetheless.
- Introduce activity fingerprints to determine if an activity has changed since the last successful archiving job.
- Show a warning badge in the archiving overview page if an activity was previously archived but had changes since the
  last run.
- Create help tooltips for all status badges on the activity archiving overview page.
- Hide unsupported or disabled activities from the archiving overview page by default. Users can still list all
  activities using the button below the table.
- Replace list of activities with a info message if archiving is disabled for this course / category.
- Log activity fingerprints during archiving and log an info message if a duplicate archive is about to be created.
- Fix archive job progress indicator tooltip text
- Fix database field type for archive job progress


## Version 0.1.0 (2025081900)

This is the initial alpha release of the archiving subsystem core. From now on,
all changes will be tracked here and proper releases will be published 🚀

Please note that sub-plugin APIs are still subject to change until the first
stable version (`v1.0.0`) is released!
