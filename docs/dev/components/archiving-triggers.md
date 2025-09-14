# Archiving Triggers

Archiving triggers are responsible for creating new archive jobs based on specific events or conditions. This can be,
for example, a manual trigger by a user or an automatic trigger that is based on a configurable schedule. Multiple
archiving triggers can be used simultaneously, e.g., to allow both manual on-demand archive creation but also initiate
archiving for all activities that have unarchived changes every night.

[:material-file-code-outline: Archiving Triggers API](../api/archiving-triggers.md){ .md-button }

!!! warning "Work in Progress (WIP)"
    This section is still under active development. Information and specifications can still be changed in the future.


## Tasks and Responsibilities

!!! abstract "Creation of archive jobs"
    Creating new archive jobs based on user input or automatic conditions (e.g., schedule, activity changes, ...)
    
!!! abstract "Configurability"
    Allowing independent configuration of the different triggers (e.g., job presets, scope, schedule, ...)


## Interfaced Components

- [Archiving Manager](archiving-manager.md)
