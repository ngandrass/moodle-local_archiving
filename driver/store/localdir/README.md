# Moodle Archiving Storage Driver: Local Filesystem

[![Latest Version](https://img.shields.io/github/v/release/ngandrass/moodle-archivingstore_localdir?include_prereleases)](https://github.com/ngandrass/moodle-archivingstore_localdir/releases)
[![PHP Support](https://img.shields.io/badge/dynamic/regex?url=https%3A%2F%2Fraw.githubusercontent.com%2Fngandrass%2Fmoodle-archivingstore_localdir%2Frefs%2Fheads%2Fmaster%2Fversion.php&search=meta-supported-php%7B(%3F%3Cdata%3E%5B%5E%7D%5D%2B)%7D&replace=%24%3Cdata%3E&label=PHP&color=blue)](https://github.com/ngandrass/moodle-archivingstore_localdir/blob/master/version.php)
[![Moodle Support](https://img.shields.io/badge/dynamic/regex?url=https%3A%2F%2Fraw.githubusercontent.com%2Fngandrass%2Fmoodle-archivingstore_localdir%2Frefs%2Fheads%2Fmaster%2Fversion.php&search=meta-supported-moodle%7B(%3F%3Cdata%3E%5B%5E%7D%5D%2B)%7D&replace=%24%3Cdata%3E&label=Moodle&color=orange)](https://github.com/ngandrass/moodle-archivingstore_localdir/blob/master/version.php)
[![GitHub Workflow Status: Moodle Plugin CI](https://img.shields.io/github/actions/workflow/status/ngandrass/moodle-archivingstore_localdir/moodle-plugin-ci.yml?label=Moodle%20Plugin%20CI)](https://github.com/ngandrass/moodle-archivingstore_localdir/actions/workflows/moodle-plugin-ci.yml)
[![Code Coverage](https://img.shields.io/coverallsCoverage/github/ngandrass/moodle-archivingstore_localdir)](https://coveralls.io/github/ngandrass/moodle-archivingstore_localdir)
[![GitHub Issues](https://img.shields.io/github/issues/ngandrass/moodle-archivingstore_localdir)](https://github.com/ngandrass/moodle-archivingstore_localdir/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/ngandrass/moodle-archivingstore_localdir)](https://github.com/ngandrass/moodle-archivingstore_localdir/pulls)
[![Maintenance Status](https://img.shields.io/maintenance/yes/9999)](https://github.com/ngandrass/moodle-archivingstore_localdir/)
[![License](https://img.shields.io/github/license/ngandrass/moodle-archivingstore_localdir)](https://github.com/ngandrass/moodle-archivingstore_localdir/blob/master/LICENSE)
[![Donate with PayPal](https://img.shields.io/badge/PayPal-donate-d85fa0)](https://www.paypal.me/ngandrass)
[![Sponsor with GitHub](https://img.shields.io/badge/GitHub-sponsor-d85fa0)](https://github.com/sponsors/ngandrass)
[![GitHub Stars](https://img.shields.io/github/stars/ngandrass/moodle-archivingstore_localdir?style=social)](https://github.com/ngandrass/moodle-archivingstore_localdir/stargazers)
[![GitHub Forks](https://img.shields.io/github/forks/ngandrass/moodle-archivingstore_localdir?style=social)](https://github.com/ngandrass/moodle-archivingstore_localdir/network/members)
[![GitHub Contributors](https://img.shields.io/github/contributors/ngandrass/moodle-archivingstore_localdir?style=social)](https://github.com/ngandrass/moodle-archivingstore_localdir/graphs/contributors)

Archiving storage driver for storing data on the local filesystem.

This plugin is part of the [Moodle archiving subsystem](https://github.com/ngandrass/moodle-local_archiving/).
You can find more information about the archiving subsystem in the [official documentation](https://archiving.gandrass.de/).


## Features

- Stores archives in a folder on the local filesystem
- Storage location is freely configurable
- Supports store and retrieve operations
- Can be used to write to external systems (e.g., network share) when mounted by the host system


## Installation

Storage drivers (`archivingstore`) are sub-plugins of the archiving subsystem core (`local_archiving`) and therefore
require the core plugin to be installed. They then must be placed inside your Moodle directory under
`local/archiving/driver/store`.

You can find detailed installation instructions within the [official documentation](https://archiving.gandrass.de/).
If you have problems installing this plugin or have further questions, please feel free to open an issue within the
[GitHub issue tracker](https://github.com/ngandrass/moodle-local_archiving/issues).


## License

2025 Niels Gandraß <niels@gandrass.de>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
