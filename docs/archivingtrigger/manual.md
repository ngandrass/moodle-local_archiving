# Archiving Trigger: Manual

The manual archiving trigger `archivingtrigger_manual` allows users to create archive jobs on-demand using the Moodle
web interface. In most cases you want this trigger to always be enabled, so that users can create archive jobs whenever
they want.

If you disable the manual trigger, users will receive the following error message when trying to create a new archive
job:

!!! warning
    Manually triggering the creation of new archives has been disabled by your system administrator. You can still
    access previously created archives using the table below. New archives can be created automatically if configured
    accordingly.

This can be useful if you automated the creation of archive jobs using another trigger, e.g., the
[scheduled trigger](cron.md), and want to prevent users from creating new archive jobs manually.