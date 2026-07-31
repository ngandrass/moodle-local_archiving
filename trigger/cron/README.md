# Moodle Archiving Trigger: Cron (Scheduled)

Cron-based automated archiving trigger plugin for the [Moodle archiving subsystem](https://github.com/ngandrass/moodle-local_archiving/).

You can find more information about the archiving subsystem in the [official documentation](https://archiving.gandrass.de/).


## Features

- Automatically creates new archive jobs for activities that have unarchived changes
- Activities are selected from within the globally configured archiving course categories
- Archive jobs are created with the configured job presets (i.e., default values)
- Time and frequency of archiving runs can be configured
- Actions are logged on each check execution


## Installation

Archiving triggers (`archivingtrigger`) are sub-plugins of the archiving subsystem core (`local_archiving`) and
therefore require the core plugin to be installed. They then must be placed inside your Moodle directory under
`local/archiving/trigger`.

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
