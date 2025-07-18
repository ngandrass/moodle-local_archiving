# Events

This document defines all events that are emitted by the archiving system to the Moodle Events API.

!!! danger "Not implemented yet!"
    The Moodle Event API is currently not implemented within the archiving system. Stay tuned for future updates :)

!!! warning "Work in Progress (WIP)"
    This section is still under active development. Information and specifications can still be changed in the future.

!!! question "Check subplugin frankenstyle naming"
    It needs to be verified how the frankenstyle names of subplugins are generated. Adjust class names accordingly.


## Archive Jobs

The following events are related to the top-level archiving jobs.

| Class name                                                  | Trigger                               | CRUD   | Payload        | Record snapshot |
|-------------------------------------------------------------|---------------------------------------|--------|----------------|-----------------|
| `\{{moodle_component_manager}}\event\archive_job_created`   | A new archive job was created         | create |                | Archive job     |
| `\{{moodle_component_manager}}\event\archive_job_updated`   | An archive job was updated            | update | Updated fields | Archive job     |
| `\{{moodle_component_manager}}\event\archive_job_completed` | An archive job completed successfully | update |                | Archive job     |
| `\{{moodle_component_manager}}\event\archive_job_failed`    | An archive job failed                 | update | Cause          | Archive job     |
| `\{{moodle_component_manager}}\event\archive_job_aborted`   | An archive job was aborted gracefully | update | Cause          | Archive job     |
| `\{{moodle_component_manager}}\event\archive_job_deleted`   | An archive job was deleted            | delete |                | Archive job     |


## Activity Archiving Tasks

The following events are used for the communication with the
[activity archiving drivers](../../components/activity-archiving-drivers.md).

| Class name                                                               | Trigger                                                                         | CRUD   | Payload                                      | Record snapshot |
|--------------------------------------------------------------------------|---------------------------------------------------------------------------------|--------|----------------------------------------------|-----------------|
| `\{{moodle_component_manager}}_archiver_<activity>\event\task_created`   | An archive job requests data from an activity of type `activity` to be archived | create | Activity metadata, Activity-specific configs | Task metadata   |
| `\{{moodle_component_manager}}_archiver_<activity>\event\task_updated`   | An archive task for an activity of type `activity` was updated                  | update | Updated fields                               | Task metadata   |
| `\{{moodle_component_manager}}_archiver_<activity>\event\task_completed` | An archive task for an activity of type `activity` was completed successfully   | update |                                              | Task metadata   |
| `\{{moodle_component_manager}}_archiver_<activity>\event\task_failed`    | An archive task for an activity of type `activity` failed                       | update | Cause                                        | Task metadata   |
| `\{{moodle_component_manager}}_archiver_<activity>\event\task_aborted`   | An archive task for an activity of type `activity` was aborted gracefully       | update | Cause                                        | Task metadata   |


## External Event Connectors

The following events are used for communication with
[external event connectors](../../components/external-event-connectors.md).

| Class name                                                                     | Trigger                                                                               | CRUD   | Payload                              | Record snapshot |
|--------------------------------------------------------------------------------|---------------------------------------------------------------------------------------|--------|--------------------------------------|-----------------|
| `\{{moodle_component_manager}}_exteventcon_<eec>\event\transmission_completed` | Transmission of an event via an external event connector of type `eec` was successful | update | Service-specific metadata (optional) | Event object    |
| `\{{moodle_component_manager}}_exteventcon_<eec>\event\transmission_failed`    | Transmission of an event via an external event connector of type `eec` failed         | update | Service-specific metadata (optional) | Event object    |


## Storage Tasks

The following events are used for the communication with the [storage drivers](../../components/storage-drivers.md).

| Class name                                                                | Trigger                                                                 | CRUD   | Payload             | Record snapshot |
|---------------------------------------------------------------------------|-------------------------------------------------------------------------|--------|---------------------|-----------------|
| `\{{moodle_component_manager}}_store_<storage>\event\read_task_created`   | Retrieval of an artifact from a storage of type `storage` was requested | create | Source, Destination | Task metadata   |
| `\{{moodle_component_manager}}_store_<storage>\event\read_task_updated`   | A read task for a storage of type `storage` was updated                 | update | Updated fields      | Task metadata   |
| `\{{moodle_component_manager}}_store_<storage>\event\read_task_completed` | An artifact was successfully retrieved from a storage of type `storage` | update | Source, Destination | Task metadata   |
| `\{{moodle_component_manager}}_store_<storage>\event\read_task_failed`    | A read from a storage of type `storage` failed                          | update | Cause               | Task metadata   |
| `\{{moodle_component_manager}}_store_<storage>\event\read_task_aborted`   | A read from a storage of type `storage` was aborted gracefully          | update | Cause               | Task metadata   |

| Class name                                                                 | Trigger                                                                 | CRUD   | Payload             | Record snapshot |
|----------------------------------------------------------------------------|-------------------------------------------------------------------------|--------|---------------------|-----------------|
| `\{{moodle_component_manager}}_store_<storage>\event\write_task_created`   | Transfer of an artifact to a storage of type `storage` was requested    | create | Source, Destination | Task metadata   |
| `\{{moodle_component_manager}}_store_<storage>\event\write_task_updated`   | A write task for a storage of type `storage` was updated                | update | Updated fields      | Task metadata   |
| `\{{moodle_component_manager}}_store_<storage>\event\write_task_completed` | An artifact was successfully transferred to a storage of type `storage` | update | Source, Destination | Task metadata   |
| `\{{moodle_component_manager}}_store_<storage>\event\write_task_failed`    | A transfer to a storage of type `storage` failed                        | update | Cause               | Task metadata   |
| `\{{moodle_component_manager}}_store_<storage>\event\write_task_aborted`   | A transfer to a storage of type `storage` was aborted gracefully        | update | Cause               | Task metadata   |


## Worker Services

Worker services communicate via the Moodle external API and do not emit any events. Communication is handled directly
within the respective [activity archiving drivers](../../components/activity-archiving-drivers.md).
