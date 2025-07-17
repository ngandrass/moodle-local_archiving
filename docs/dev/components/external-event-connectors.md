# External Event Connectors

External event connectors allow forwarding of specific events within the archiving system to external services, such as
campus management systems. This can be used to trigger specific actions in external systems, such as storing the path to
an archived exam file for a given student inside a student record.

!!! warning "Work in Progress (WIP)"
    This section is still under active development. Information and specifications can still be changed in the future.


## Tasks and Responsibilities

!!! abstract "Event Forwarding"
    Forwards specific events to external systems, such as the successful creation of a new artifact for a student.

    - External event connectors primarily transmit information / metadata. Full archive files are primarily transmitted by
      the respective [storage drivers](storage-drivers.md).

!!! abstract "Transformation of Data"
    Transforms internal event data into a format that is understandable by the external system.
    
!!! abstract "Configurability"
    Allowing independent configuration of the different connectors (e.g., event sensitivity list, API credentials, ...)


## Interfaced Components

- [Archiving Manager](archiving-manager.md)


## Implementation

Each external event connector must implement the {{ source_file('classes/driver/archivingevent.php', '\\local_archiving\\driver\\archivingevent') }}
interface with a class, placed at the following location: `/local/archiving/driver/event/<pluginname>/classes/archivingevent.php`,
where `<pluginname>` is the name of the external event connector (e.g., `mycms`, `externalapi`, ...).


## Examples

!!! warning "Needs further analysis"
    An analysis of the currently used campus management solutions and the specific business processes of the target
    institutions must be conducted to determine a list of relevant external systems to be supported and what APIs to
    address.
