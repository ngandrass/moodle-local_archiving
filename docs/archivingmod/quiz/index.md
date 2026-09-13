# Activity Archiving Driver: Quiz

This activity archiving driver exports quiz attempts as PDF and HTML files together with all file attachments uploaded
by students. Attempt PDFs will be created in a [PDF/A-3b compliant format](https://en.wikipedia.org/wiki/PDF/A), ideal
for long-term storage. A checksum is calculated for every file within the archive, to allow verification of file
integrity whenever needed. Created archives can also be cryptographically signed by a trusted authority using
the [Time-Stamp Protocol (TSP)](https://en.wikipedia.org/wiki/Time_stamp_protocol).

Comprehensive archive settings allow selecting what should be included in the generated reports on a fine-granular level
(e.g., exclude example solutions, include answer history, ...). Generated quiz attempt reports include all elements of
the test, even complex ones like [MathJax](https://www.mathjax.org/)
formulas, [STACK](https://marketplace.moodle.com/plugins/qtype_stack) plots, [GeoGebra](https://www.geogebra.org/)
applets, and other question / content types that require JavaScript processing. All PDF and HTML files are fully
text-searchable, including rendered MathJax formulas. Content is saved vector based, whenever possible, to allow
high-quality printing and zooming while keeping the file size down.

Quiz archives are created by an external [Moodle Archiving Worker](worker.md) service to remove load from Moodle and to
eliminate the need to install a large number of software dependencies on the webserver. It can easily be deployed using
Docker.


## Features

- Archiving of quiz attempts as [PDF/A-3b](https://en.wikipedia.org/wiki/PDF/A) and HTML files
- Support for file submissions / attachments (e.g., essay files)
- Quiz attempt reports are accessible completely independent of Moodle, hereby ensuring long-term readability
- Customization of generated PDF and HTML reports
    - Allows creation of reduced reports, e.g., without example solutions, for handing out to students during inspection
- Support for complex content and question types, including Drag and Drop, MathJax formulas, STACK plots, and other
  question / content types that require JavaScript processing
- Support for question type [JACK](https://github.com/Wunderbyte-GmbH/moodle_qtype_jack)
- Quiz attempt reports are fully text-searchable, including mathematical formulas
- Generation of checksums for every file within the archive and the archive itself
- Cryptographic signing of archives and their creation date using
  the [Time-Stamp Protocol (TSP)](https://en.wikipedia.org/wiki/Time_stamp_protocol)
- Attempt report folder and file names are fully customizable and support dynamic variables (e.g., course name, quiz
  name, username, ...)
- Allows definition of global archiving defaults as well as forced archiving policies (i.e., locked archive job presets
  that cannot be changed by the user)
- Fully asynchronous archive creation to reduce load on Moodle Server
- Data compression and vector based MathJax formulas to preserve disk space
- Technical separation of Moodle and archive worker service
- Data-minimizing and security driven design


## Requirements

In order to use the quiz archiving sub-plugin, you need to have the following prerequisites met:

- Moodle 4.5 (LTS) or newer
- PHP 8.1 or newer
- PostgreSQL or MariaDB / MySQL
- Admin access to the Moodle instance and shell access to the server (e.g., SSH)
- At least 1 CPU core and 1 GB of RAM available for the [archive worker service](worker.md)

!!! warning "This plugin requires a separate worker service"
    Quiz archives are created by an external [archive worker service](worker.md). It uses the Moodle webservice API to
    query the required data and to upload the created archive.
