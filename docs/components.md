# Components

The archiving subsystem consists of a core plugin ([local_archiving](https://github.com/ngandrass/moodle-local_archiving/))
and several sub-plugins that implement various functions of the archiving systa list

This page provides a list of the different sub-plugin types and highlights existing implementations. The diagram below
gives a brief overview of the different components.

![Archiving subsystem components overview](assets/diagrams/architecture-overview-simple.drawio)


!!! example "Additional information"
    You can find more information about all components and how they work in detail inside the
    [developer section](dev/index.md) of this documentation.


## Activity Archiving Drivers

Activity archiving drivers are responsible for the actual archiving process of a specific Moodle activity. One such
driver exists for every Moodle activity that is supported by the archiving system. Activity archiving drivers gather all
relevant data from the activity, transform it into an archivable format, and returns the finished archive back to the
archiving manager.

### Quiz (`archivingmod_quiz`)

Activity archiving driver for Moodle quizzes.

!!! github "GitHub Repository"
    [https://github.com/ngandrass/moodle-archivingmod_quiz](https://github.com/ngandrass/moodle-archivingmod_quiz)

### Assignment (`archivingmod_assign`)

Activity archiving driver for Moodle assignments.

!!! github "GitHub Repository"
    [https://github.com/ngandrass/moodle-archivingmod_assign](https://github.com/ngandrass/moodle-archivingmod_assign)


## Storage Drivers

Storage drivers are responsible for safely transferring a finished archive to a specific storage location. This can be,
for example, the Moodledata storage or an S3 compatible WORM storage. Having multiple storage drivers available allows
for a flexible adaptation to existing archiving and storage systems.

### Local Directory (`archivingstorage_localdir`)

Archiving storage driver for storing data on the local filesystem.

!!! github "GitHub Repository"
    [https://github.com/ngandrass/moodle-archivingstore_localdir](https://github.com/ngandrass/moodle-archivingstore_localdir)


### Moodledata (`archivingstorage_moodle`)

Archiving storage driver for storing archived data inside the Moodle file store.

!!! github "GitHub Repository"
    [https://github.com/ngandrass/moodle-archivingstore_moodle](https://github.com/ngandrass/moodle-archivingstore_moodle)


## External Event Connectors

External event connectors allow forwarding of specific events within the archiving system to external services, such as
campus management systems. This can be used to trigger specific actions in external systems, such as storing the path to
an archived exam file for a given student inside a student record.

The external event connectors differ from storage drivers in the way that they do not handle data storage but instead
solely deliver information to external systems. This allows decoupling file storage from the remaining business logic of
target institutions.

### API Stub (`archivingevent_apistub`)

A stub implementation of an external event connector.

!!! github "GitHub Repository"
    [https://github.com/ngandrass/moodle-archivingevent_apistub](https://github.com/ngandrass/moodle-archivingevent_apistub)
