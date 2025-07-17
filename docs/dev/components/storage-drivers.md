# Storage Drivers

Storage drivers are responsible for safely transferring a finished archive to a specific storage location. When creating
archives for legal reasons, storing the archives in an external WORM[^1] storage is strongly advised.


[^1]: WORM: _"write once, read many"_ - A storage solution that allows to write data only once and only allow subsequent
reads. Changes of data that was once written is prohibited.


## Tasks and Responsibilities

!!! abstract "Writing Data to Storage"
    Provides an interface to write archives to storage

    - Data must be stored in a way that overriding existing data is not possible, even if archive file names are
      identical

!!! abstract "Reading Data from Storage"
    Provides an interface to read archives from storage
    
    - If data retrieval is not possible, the data can not be retrieved via Moodle
    - If data retrieval is not possible, GDPR compliance (automatic deletion, personal data retrieval) is not possible

!!! abstract "Authentication"
    Store credentials for authentication against the storage system, if applicable

    - Credentials must only be visible to the system administrators

!!! abstract "Connection Check"
    Provides a way to check the status of the storage system connection (e.g., connection test, test write, test read)

!!! abstract "Storage Space Monitoring"
    Provides a way to monitor the available storage space, if reported by the respective storage system / interface

!!! abstract "Configurability"
    Allowing independent configuration of the different available storage drivers (e.g., storage path, storage system
    credentials, ...)


## Interfaced Components

- [Archiving Manager](archiving-manager.md)


## Implementation

Each storage driver must implement the {{ source_file('classes/driver/archivingstore.php', '\\local_archiving\\driver\\archivingstore') }}
interface with a class, placed at the following location: `/local/archiving/driver/store/<pluginname>/classes/archivingstore.php`,
where `<pluginname>` is the name of the storage driver (e.g., `localdir`, `moodle`, ...).

[:material-file-code-outline: Storage Driver API](../api/storage-drivers.md){ .md-button }

Once all data for a single archive job is collected, the [archiving manager](archiving-manager.md) will call the storage
driver that is responsible for the respective task. The storage driver will then write all data to the associated
storage and create {{ source_file('classes/file_handle.php', '\\local_archiving\\file_handle') }} objects for each file
that has been stored. File handles keep track of stored files and contain various metadata that allows to retrieve the
referenced files from the external storage system at a later point in time.


## Examples

### Moodledata Storage

!!! warning "TODO"
    This section is still work in progress (WIP) and might contain incomplete, incorrect, or outdated information.

- Stores archived data in the Moodle storage system
- Archived data is stored in the file area of a specific course


### File System Storage

!!! warning "TODO"
    This section is still work in progress (WIP) and might contain incomplete, incorrect, or outdated information.

- Copies archived data to a given filesystem path
- Filepath can be configured in the driver settings


### S3 Object Storage

!!! warning "TODO"
    This section is still work in progress (WIP) and might contain incomplete, incorrect, or outdated information.

- Sends archived data to a S3 compatible object storage system (e.g., WORM storage)


### FTP

!!! warning "TODO"
    This section is still work in progress (WIP) and might contain incomplete, incorrect, or outdated information.

- Uploads archived data to a FTP server
