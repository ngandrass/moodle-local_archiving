# Installation

!!! warning "Work in Progress (WIP)"
    This section is still under active development. Information and specifications can still be changed in the future.

This section provides instructions on how to install the archiving core and additional sub-plugins, according to your
needs.

If you encounter any issues during the installation process, please open a bug report or ask a question in the issue
tracker over on GitHub.

[:simple-github: Issue Tracker](https://github.com/ngandrass/moodle-local_archiving/issues){ .md-button }


## Requirements

In order to use the archiving subsystem, you need to have the following prerequisites met:

- Moodle 4.5 (LTS) or newer
- PHP 8.1 or newer
- PostgreSQL or MariaDB / MySQL (other DBMS might work but are untested)
- Admin access to the Moodle instance

!!! danger "A note on PHP versions"
    Please always use the **most recent version of PHP** supported by your
    Moodle version. Older PHP versions contain security vulnerabilities and bugs.

    You can check the specific requirements and supported software versions for
    your specific Moodle version over at [the Moodle Docs](https://moodledev.io/general/releases).


## Versioning

This plugin uses [Semantic Versioning 2.0.0](https://semver.org/). This means that their version numbers are structured
as `MAJOR.MINOR.PATCH`.

Breaking changes are indicated by an increment of the `MAJOR` version number, while new features and improvements are
indicated by an increment of the `MINOR` version number. Releases that contain only bug fixes or minor optimizations are
indicated by an increment of the `PATCH` version number.

It is **recommended to always use the latest version** of this plugin so that you get all the latest bug fixes, features,
and optimizations.


### Development / Testing Versions

Special development versions, used for testing, can be created but will never be
published to the Moodle plugin directory. Such development versions are marked
by a `+dev-[TIMESTAMP]` suffix, e.g., `2.4.2+dev-2022010100`.


## Installation

To use the archiving subsystem, installing the core plugin is required. Additional features (e.g., support for certain
Moodle activities) can be added by installing additional sub-plugins.

[:simple-moodle: Installation: Archiving Core](core.md){ .md-button }
&nbsp;&nbsp;&nbsp;
[:material-cube-outline: Installation: Sub-Plugins](plugins.md){ .md-button }
