# Architecture

This document provides a high-level introduction to the architecture of the Moodle Data Archiving System. It describes
the different components of the system, their responsibilities, and how they interact with each other.

Each component is described in detail in its own subsection, linked below every component description.


# Components

This section describes the different components of the archiving system and their responsibilities. Each component is
described in detail in its own subsection, linked below every component description.

![](../../assets/diagrams/architecture-overview.drawio)


## Archiving Manager

The archiving manager is the central entry point for archiving tasks and represents the primary UI, that is visible to
the non-administrative user. It provides an overview of archivable data inside each course and whether it has already
been archived or not. The archiving manager keeps track of the whole archiving process and manages the various software
components required for processing it.

[:fontawesome-solid-cubes: Component Details](archiving-manager.md){ .md-button }


## Activity Archiving Drivers

The activity archiving drivers are responsible for the actual archiving process of a specific Moodle activity. One such
driver exists for every Moodle activity that is supported by the archiving system. Activity archiving drivers gather all
relevant data from the activity, transform it into an archivable format, and returns the finished archive back to the
archiving manager.

[:fontawesome-solid-cubes: Component Details](activity-archiving-drivers.md){ .md-button }


## Storage Drivers

Storage drivers are responsible for safely transferring a finished archive to a specific storage location. This can be,
for example, the Moodledata storage or an S3 compatible WORM storage. Having multiple storage drivers available allows
for a flexible adaptation to existing archiving and storage systems.

[:fontawesome-solid-cubes: Component Details](storage-drivers.md){ .md-button }


## Worker Services

If an activity archiving driver requires additional or specific processing, it can delegate certain tasks to a
designated worker service. Worker services not only offload heavy processing tasks from the Moodle system but also
allow to handle complex transformations, such as rendering and exporting a quiz attempt page into a single PDF file.

[:fontawesome-solid-cubes: Component Details](worker-services.md){ .md-button }


## External Event Connectors

External event connectors allow forwarding of specific events within the archiving system to external services, such as
campus management systems. This can be used to trigger specific actions in external systems, such as storing the path to
an archived exam file for a given student inside a student record.

The external event connectors differ from storage drivers in the way that they do not handle data storage but instead
solely deliver information to external systems. This allows decoupling file storage from the remaining business logic of
target institutions.

[:fontawesome-solid-cubes: Component Details](external-event-connectors.md){ .md-button }
