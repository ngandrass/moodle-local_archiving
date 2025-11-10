# Archiving Triggers

Archiving triggers are responsible for creating new archive jobs based on specific events or conditions. This can, for
example, be a manual trigger by a user or an automatic trigger that is based on a configurable schedule. Multiple
archiving triggers can be used simultaneously, e.g., to allow both manual on-demand archive creation but also initiate
archiving for all activities that have unarchived changes every night.

If your use case is not covered by any of the existing archiving triggers, the simple interface of archiving triggers
lets you easily implement a custom trigger that exactly fits your needs.

[:material-file-code-outline: Archiving Triggers API](../api/archiving-triggers.md){ .md-button }


## Tasks and Responsibilities

!!! abstract "Creation of archive jobs"
    Creating new archive jobs based on user input or automatic conditions (e.g., schedule, activity changes, ...)
    
!!! abstract "Configurability"
    Allowing independent configuration of the different triggers (e.g., job presets, scope, schedule, ...)


## Interfaced Components

- [Archiving Manager](archiving-manager.md)


## Examples

### Manual Trigger

- Allows users to manually create new archive jobs via the archiving manager UI.
- Provides a way to allow or forbid creating new archive jobs manually unless explicitly allowed to bypass this
  restriction via a respective capability.


### Scheduled Trigger (Cron)

- Automatically creates new archive jobs for targeted activities that have unarchived changes.
- Runs on a configurable schedule via the Moodle cron system.
- Uses configured default values for new archive jobs (e.g., storage location, naming conventions, ...).
