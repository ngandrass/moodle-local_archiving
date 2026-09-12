# Components

The archiving subsystem consists of a core plugin ([local_archiving](https://github.com/ngandrass/moodle-local_archiving/))
and several sub-plugins that implement various functions of the archiving 
system.

This page provides a list of the different sub-plugin types and highlights existing implementations. The diagram below
gives a brief overview of the different components.

![](assets/diagrams/architecture-overview-simple.drawio)


!!! example "In-depth technical information"
    You can find more information about all components, how they work and how they interact with each other in detail 
    inside the [developer section](dev/index.md) of this documentation.


## Activity Archiving Drivers

[Activity archiving drivers](archivingmod/index.md) are responsible for the actual archiving process of a specific
Moodle activity. One such driver exists for every Moodle activity that is supported by the archiving system. Activity
archiving drivers gather all relevant data from the activity, transform it into an archivable format, and returns the
finished archive back to the archiving manager.

The following activity archiving drivers are currently available:

[:material-file-upload-outline: Assignment](assign.md){ .md-button }
&nbsp;&nbsp;
[:material-list-box-outline: Quiz](quiz.md){ .md-button }


## Storage Drivers

[Storage drivers](archivingstore/index.md) are responsible for safely transferring a finished archive to a specific
storage location. This can be, for example, the Moodledata storage or an S3 compatible WORM storage. Having multiple
storage drivers available allows for a flexible adaptation to existing archiving and storage systems.

The following storage drivers are currently available:

[:material-folder-open: Local Directory](localdir.md){ .md-button }
&nbsp;&nbsp;
[:simple-moodle: Moodle Filestore](moodle.md){ .md-button }
&nbsp;&nbsp;
[:fontawesome-solid-cubes: S3 Object Store](s3.md){ .md-button }


## Archiving Triggers

[Archiving triggers](archivingtrigger/index.md) are responsible for creating new archive jobs based on specific events
or conditions. This can be, for example, a manual trigger by a user or an automatic trigger that is based on a
configurable schedule. Multiple archiving triggers can be used simultaneously, e.g., to allow both manual on-demand
archive creation but also initiate archiving for all activities that have unarchived changes every night.

The following archiving triggers are currently available:

[:material-cursor-default-click-outline: Manual](manual.md){ .md-button }
&nbsp;&nbsp;
[:material-calendar-clock: Scheduled](cron.md){ .md-button }


## External Event Connectors

External event connectors allow forwarding of specific events within the archiving system to external services, such as
campus management systems. This can be used to trigger specific actions in external systems, such as storing the path to
an archived exam file for a given student inside a student record.

The external event connectors differ from storage drivers in the way that they do not handle data storage but instead
solely deliver information to external systems. This allows decoupling file storage from the remaining business logic of
target institutions.

There are currently no external event connectors shipped with the core plugin. Developers can use this sub-plugin type
to implement their own glue logic.
