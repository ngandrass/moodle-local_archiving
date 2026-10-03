# Moodle Archiving Storage Driver: S3 compatible object storage

This archiving storage driver allows you to transfer created archives to any S3 compatible object store. This includes
[RustFS](https://rustfs.com/), [Ceph](https://ceph.io/), and [Amazon S3](https://en.wikipedia.org/wiki/Amazon_S3). All
files are automatically transferred to the configured S3 bucket during the archive job execution.

This plugin is part of the [Moodle archiving framework](https://github.com/ngandrass/moodle-local_archiving/).
You can find more information about the archiving subsystem in the [official documentation](https://archiving.gandrass.de/).


## Features

- Archive storage and retrieval using any S3 compatible object store
- Freely configurable S3 endpoint settings
- Customizable bucket storage path
- Encrypted file transfer using TLS
- Support for path-style and virtual-host-style access
- Automated check of S3 connection and bucket access rights during plugin configuration


## Installation

Storage drivers (`archivingstore`) are sub-plugins of the archiving subsystem core (`local_archiving`) and therefore
require the core plugin to be installed. They then must be placed inside your Moodle directory under
`local/archiving/store`.

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
