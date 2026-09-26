# Activity Archiving Driver: Assignment

This activity archiving driver exports assignment submissions as PDF and HTML files together with
all file attachments submitted by students and graders. Submission reports are created
in a [PDF/A-3b compliant format](https://en.wikipedia.org/wiki/PDF/A), ideal for long-term storage.
A checksum is calculated for every file within the archive, to allow verification of file integrity
whenever needed. Created archives can also be cryptographically signed by a trusted authority using
the [Time-Stamp Protocol (TSP)](https://en.wikipedia.org/wiki/Time_stamp_protocol).

Comprehensive archive settings allow selecting what should be included in the generated submission reports on a
fine-granular level (e.g., assignment instructions, submission content, feedback, grades). Submitted and provided files,
grader feedback files, and interactively annotated PDF feedback are bundled alongside the reports. A separate tabular
export of submission metadata (user identity, attempt number, status, timestamps) can be generated for the whole
archive.

Assignment archives are created by an external [Moodle Archiving Worker](worker.md) service to remove load from Moodle
and to eliminate the need to install a large number of software dependencies on the webserver. It can easily be deployed
using Docker.

[:material-archive-outline: Archived Data](data.md){ .md-button }
&nbsp;&nbsp;
[:material-monitor-screenshot: Screenshots](screenshots.md){ .md-button }
&nbsp;&nbsp;
[:material-file-document-edit-outline: Changelog](changelog.md){ .md-button }


## Features

- Archiving of assignment submissions as [PDF/A-3b](https://en.wikipedia.org/wiki/PDF/A) and HTML files
- Support for file submissions / attachments, including intro files, student submission files, grader feedback files,
  and interactively annotated PDF feedback
- Comprehensive selection of report content on a fine-granular level (header, assignment instructions, submissions,
  feedback, comments, grade, and grader details)
- Assignment submission reports are accessible completely independent of Moodle, hereby ensuring long-term readability
- Export of tabular submissions metadata (user identity, attempt number, status, timestamps) for the whole archive
- Generation of checksums for every file within the archive and the archive itself
- Cryptographic signing of archives and their creation date using
  the [Time-Stamp Protocol (TSP)](https://en.wikipedia.org/wiki/Time_stamp_protocol)
- Submission report folder and file names are fully customizable and support dynamic variables (e.g., course name,
  assignment name, group, username, ...)
- Allows definition of global archiving defaults as well as forced archiving policies (i.e., locked archive job presets
  that cannot be changed by the user)
- Fully asynchronous archive creation to reduce load on Moodle Server
- Data compression and vector based MathJax formulas to preserve disk space
- Technical separation of Moodle and archive worker service
- Data-minimizing and security driven design


## Requirements

In order to use the assignment archiving sub-plugin, you need to have the following prerequisites met:

- Moodle 4.5 (LTS) or newer
- PHP 8.1 or newer
- PostgreSQL or MariaDB / MySQL
- Admin access to the Moodle instance and shell access to the server (e.g., SSH)
- At least 1 CPU core and 1 GB of RAM available for the [archive worker service](worker.md)

!!! warning "This plugin requires a separate worker service"
    Assignment archives are created by an external [archive worker service](worker.md). It uses the Moodle webservice
    API to query the required data and to upload the created archive.
