# Moodle Archiving Trigger: Event

Event-based archiving trigger plugin for the [Moodle archiving subsystem](https://github.com/ngandrass/moodle-local_archiving/).

You can find more information about the archiving subsystem in the [official documentation](https://archiving.gandrass.de/).


## Features

- Automatically creates archive jobs whenever selected Moodle events occur inside supported activities (e.g., a
  submitted quiz attempt or a graded assignment submission)
- Freely selectable events per activity type, as exposed by the respective activity archiving drivers
- Only archives the object that triggered the event (e.g., a single quiz attempt or assignment submission)
- Supports the configured archive job presets for all created archive jobs
- Prevents the creation of identical archive jobs while a previous one is still pending


## Installation

Archiving triggers (`archivingtrigger`) are sub-plugins of the archiving subsystem core (`local_archiving`) and
therefore require the core plugin to be installed. They then must be placed inside your Moodle directory under
`local/archiving/trigger`.

You can find detailed installation instructions within the [official documentation](https://archiving.gandrass.de/).
If you have problems installing this plugin or have further questions, please feel free to open an issue within the
[GitHub issue tracker](https://github.com/ngandrass/moodle-local_archiving/issues).


## License

2026 Niels Gandraß <niels@gandrass.de>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
