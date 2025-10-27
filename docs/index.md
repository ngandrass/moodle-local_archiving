# Archiving Subsystem for Moodle

<div style="margin-top: -30px;" markdown>

[![Latest Version](https://img.shields.io/github/v/release/ngandrass/moodle-local_archiving?include_prereleases)](https://github.com/ngandrass/moodle-local_archiving/releases)
[![PHP Support](https://img.shields.io/badge/dynamic/regex?url=https%3A%2F%2Fraw.githubusercontent.com%2Fngandrass%2Fmoodle-local_archiving%2Frefs%2Fheads%2Fmaster%2Fversion.php&search=meta-supported-php%7B(%3F%3Cdata%3E%5B%5E%7D%5D%2B)%7D&replace=%24%3Cdata%3E&label=PHP&color=blue)](https://github.com/ngandrass/moodle-local_archiving/blob/master/version.php)
[![Moodle Support](https://img.shields.io/badge/dynamic/regex?url=https%3A%2F%2Fraw.githubusercontent.com%2Fngandrass%2Fmoodle-local_archiving%2Frefs%2Fheads%2Fmaster%2Fversion.php&search=meta-supported-moodle%7B(%3F%3Cdata%3E%5B%5E%7D%5D%2B)%7D&replace=%24%3Cdata%3E&label=Moodle&color=orange)](https://github.com/ngandrass/moodle-local_archiving/blob/master/version.php)
[![GitHub Workflow Status: Moodle Plugin CI](https://img.shields.io/github/actions/workflow/status/ngandrass/moodle-local_archiving/moodle-plugin-ci.yml?label=Moodle%20Plugin%20CI)](https://github.com/ngandrass/moodle-local_archiving/actions/workflows/moodle-plugin-ci.yml)
[![Code Coverage](https://img.shields.io/coverallsCoverage/github/ngandrass/moodle-local_archiving)](https://coveralls.io/github/ngandrass/moodle-local_archiving)
[![GitHub Issues](https://img.shields.io/github/issues/ngandrass/moodle-local_archiving)](https://github.com/ngandrass/moodle-local_archiving/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/ngandrass/moodle-local_archiving)](https://github.com/ngandrass/moodle-local_archiving/pulls)
[![Maintenance Status](https://img.shields.io/maintenance/yes/9999)](https://github.com/ngandrass/moodle-local_archiving/)
[![License](https://img.shields.io/github/license/ngandrass/moodle-local_archiving)](https://github.com/ngandrass/moodle-local_archiving/blob/master/LICENSE)
[![Donate with PayPal](https://img.shields.io/badge/PayPal-donate-d85fa0)](https://www.paypal.me/ngandrass)
[![Sponsor with GitHub](https://img.shields.io/badge/GitHub-sponsor-d85fa0)](https://github.com/sponsors/ngandrass)
[![GitHub Stars](https://img.shields.io/github/stars/ngandrass/moodle-local_archiving?style=social)](https://github.com/ngandrass/moodle-local_archiving/stargazers)
[![GitHub Forks](https://img.shields.io/github/forks/ngandrass/moodle-local_archiving?style=social)](https://github.com/ngandrass/moodle-local_archiving/network/members)
[![GitHub Contributors](https://img.shields.io/github/contributors/ngandrass/moodle-local_archiving?style=social)](https://github.com/ngandrass/moodle-local_archiving/graphs/contributors)

</div>

This is the official documentation for the Moodle Archiving Subsystem project. It provides a solid framework for
archiving data from various Moodle activities, such as quizzes and assignments, and transferring it to different storage
locations, such as the Moodle file store or external WORM storage systems.

!!! warning "Work in Progress (WIP)"
    This project is currently under active development and is not yet ready for production use. Please feel free to try
    it out on a test system and provide feedback if you like :)

If you are new here, taking a look at screenshots and the component overview is a good way to familiarize yourself with
the project:

[:material-monitor-screenshot: Screenshots](screenshots.md){ .md-button }&nbsp;&nbsp;
[:fontawesome-solid-cubes: Components Overview](components.md){ .md-button }


## Quickstart

[:material-download: (1) Installation](setup/install/index.md){ .md-button }&nbsp;&nbsp;
[:material-cog: (2) Configuration](setup/config/index.md){ .md-button }&nbsp;&nbsp;
[:material-account: (3) Usage](usage/index.md){ .md-button }

_You can navigate to each section using the buttons above or jump to each document individually using the tree
navigation on the left side._


## Current State and Roadmap

You can find information about the current state of this project as well as an overview of already achieved milestones
and their results on the [Roadmap](roadmap.md) page.

[:material-road-variant: Show Roadmap](roadmap.md){.md-button}&nbsp;&nbsp;
[:material-file-document-edit-outline: Changelog](changelog.md){.md-button}


## Join the Discussion and Contribute

If you want to take part in the discussion and shape the future of this project, you can join us over on
[Matrix](https://matrix.org/): [#archiving:gandrass.de](https://matrix.to/#/#archiving:gandrass.de)

[:simple-matrix:&nbsp;&nbsp;Join the Matrix Chat<br><tt><small>#archiving:gandrass.de</small></tt>](https://matrix.to/#/#archiving:gandrass.de){ .md-button }


## Developer Documentation

Are you planing to contribute to the Moodle archiving subsystem? Check out our [developer documentation](dev/index.md)
to learn more about the system architecture, its components, and how to implement new sub-plugins.

[:material-file-code-outline: Developer Documentation](dev/index.md){.md-button}&nbsp;&nbsp;
[:simple-github: GitHub Repository](https://github.com/ngandrass/moodle-local_archiving){ .md-button }


## License

This work is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.en.html).
