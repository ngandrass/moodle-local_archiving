# Activity Archiving Driver: Quiz

TODO


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
