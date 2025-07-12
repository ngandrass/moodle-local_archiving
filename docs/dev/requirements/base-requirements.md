# Base Requirements

This section describes base requirements for the archiving solution and the data that it archives. These requirements
apply to all archivable data, regardless of the specific Moodle activity it originates from. Therefore, this set of
requirements can be extended by additional requirements for specific Moodle activities.

This set of requirements underpin each of the [user stories](user-stories.md), but do not directly map to them. Instead,
they are meant as a framework of standards that the archiving solution must adhere to with every feature that is
implemented or define specific technical standards that must be met.


## Data Integrity

This section focuses on measures to verify and ensure the integrity of the archived data throughout the archiving
process.

!!! warning "[REQ-DI-01] Equality of archived and original data"
    The archived data must resemble the original data as closely as possible and no critical information, e.g., grades,
    must be lost or altered. Archived data must only be transformed into an alternate representation when inevitable.

!!! warning "[REQ-DI-02] Static presentation of archived data"
    The presentation of the archived data must not change in the future. Therefore, all data must be fully rendered at
    the point of archiving so that it does not depend on future software behavior[^1]. For PDF files,
    the [PDF/A](https://en.wikipedia.org/wiki/PDF/A) standard should be used.

!!! warning "[REQ-DI-03] Checksums for data integrity"
    Archived data must be stored alongside checksums ([SHA256](https://en.wikipedia.org/wiki/SHA-2) or similar) to
    verify the integrity of the archived data.

!!! warning "[REQ-DI-04] Digital cryptographic signatures"
    Digital cryptographic signatures should be able to be issued for archived data by a third party to attest their
    integrity and time of creation using the [Time-Stamp Protocol (TSP)](https://datatracker.ietf.org/doc/html/rfc3161).

[^1]: This explicitly excludes dynamic web content, such as JavaScript-based content and HTML/CSS DOMs, from being a
valid archiving format on its own. This is due to it requiring rendering inside a browser at the time of viewing, which
is not guaranteed to reproduce the exact same data presentation in the future.


## Traceability and Reproducibility

This section focuses on the traceability and reproducibility of the archiving process and the resulting archive data.

!!! warning "[REQ-TR-01] Unique identifiers for archive jobs"
    Each archive job must possess an identifier that uniquely identifies the archive job. This can be a
    [universally unique identifier (UUID)](https://pubs.opengroup.org/onlinepubs/9629399/apdxa.htm) with a length of 128
    bit.

!!! warning "[REQ-TR-02] Reproducibility of archive jobs"
    An archive job must produce the same result every time it is executed with the same input data. Archive job metadata,
    e.g., the date of archiving, is excluded from this requirement.

!!! warning "[REQ-TR-03] Metadata for archive jobs"
    Representative metadata about the archive job must be stored alongside the archived data and be kept until the
    archived data can be deleted. This includes, but is not limited to, the date of archiving, the user who initiated
    the archiving, a list of the users whose data is included in the archive, and the Moodle course / activity the data
    originates from.

!!! warning "[REQ-TR-04] Identifying archived data"
    The user should be able to determine, which data is already archived and which data currently is not.

!!! warning "[REQ-TR-05] Identifying data to be archived"
    The user should be able to determine, which data requires archiving and which data does not.

!!! warning "[REQ-TR-06] Traceability of automated archiving processes"
    If the archiving process is initiated automatically, it must be possible to trace back which data was marked for
    archiving at which point in time.

!!! warning "[REQ-TR-07] Error handling"
    If an error occurs during the archiving process, the user must be informed about the error and the archiving process
    must be aborted to prevent archiving invalid or incomplete data.


## Compatibility and Autonomy

This section focuses on ensuring the future readability of the archived data, its independence from Moodle itself, and 
the compatibility of the archiving solution with external systems.

!!! warning "[REQ-CA-01] Future readability"
    Archives must be readable in up to 10 years.

!!! warning "[REQ-CA-02] Autonomy of archived data"
    Archives must be readable without requiring the Moodle instance it was created on.

!!! warning "[REQ-CA-03] Archive retention"
    Archives must be kept, even if the Moodle course or activity is deleted.

!!! warning "[REQ-CA-04] Transferring archived data"
    Archives must be transferable to external third party systems, such as network storages or document management
    systems.

!!! warning "[REQ-CA-05] Open data formats"
    If an archiving process requires transforming data into a different format, an open format must be used to ensure that
    the data can still be accessed in the future, without requiring specific software[^2].

!!! warning "[REQ-CA-06] Open data storage standards and transmission protocols"
    The archiving software must use open standards for data storage and data transmission to ensure that the data can
    still be accessed in the future, without requiring specific software[^2].

!!! warning "[REQ-CA-07] Source code availability"
    The code of the archiving solution should be publicly available, free of charge.


[^2]: If proprietary software or standards are used, it can not be guaranteed that the software will still be maintained
and work in the future. Open source software and open standards, on the other hand, can be maintained by the community
and are less likely to become fully unavailable in the foreseeable future.


## Data Protection, Regulatory, and Privacy

This section focuses on regulatory and privacy aspects of the archiving solution, as well as data protection aspects.

!!! warning "[REQ-DP-01] GDPR compliance"
    The archiving process must comply with the general data protection regulation (GDPR).

!!! warning "[REQ-DP-02] Legal user data requests"
    Users must be able to request the data stored about them.

!!! warning "[REQ-DP-03] Automatic deletion of archived data"
    Archived data should automatically be deleted after the legal retention period.

!!! warning "[REQ-DP-04] Policies"
    Global archiving policies must be enforceable to ensure that all data is archived according to the institution's 
    regulations and policies. This includes, but is not limited to, the retention period of the data, the obligatory
    data that is always archived, the way data is stored, and the way data is accessed.

!!! warning "[REQ-DP-05] Data retention policy enforcement"
    If a data retention policy is in place, all archives must store their retention period alongside the archive.

!!! warning "[REQ-DP-06] Access rights and user capabilities"
    Access to archived data must be controlled by appropriate capabilities, integrating with the Moodle role concept.

!!! warning "[REQ-DP-07] Third-party services"
    Private data must not compulsorily be sent to third-party services that are not under the control of the
    institution. It must be possible to run all software components of the archiving solution on-premises.

!!! warning "[REQ-DP-08] Data security"
    The archived data must be storable in a secure manner[^3], e.g., encrypted at rest.

[^3]: It is sufficient to support encryption on storage level, e.g., using an encrypted file system.


## Miscellaneous

This section lists additional requirements that do not fit into any of the previous categories.

!!! warning "[REQ-MI-01] Machine-readable metadata"
    Archive metadata must be machine-readable (e.g., as CSV files).

!!! warning "[REQ-MI-02] Machine-readable archive data"
    Archive data should be machine-readable, whenever possible.

!!! warning "[REQ-MI-03] Text-searchable archive data"
    Archived data should be text-searchable by the user, whenever possible, e.g., exporting full-text PDFs instead of
    images.

!!! warning "[REQ-MI-04] Data compression"
    Archives should be compressed to preserve storage space using open compression standards (e.g., gzip, deflate, ...).
