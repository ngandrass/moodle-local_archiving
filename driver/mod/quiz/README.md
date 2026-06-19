# Moodle Activity Archiving Driver: Quiz

Activity archiving driver for Moodle quizzes.

This plugin is part of the [Moodle archiving subsystem](https://github.com/ngandrass/moodle-local_archiving/).
You can find more information about the archiving subsystem in the [official documentation](https://archiving.gandrass.de/).

Quiz archives are created by an external [quiz archive worker](https://github.com/ngandrass/moodle-quiz-archive-worker)
service to remove load from Moodle and to eliminate the need to install a large number of software dependencies on the
webserver. It can easily be [deployed using Docker](https://github.com/ngandrass/moodle-quiz-archive-worker#installation).


## Features

- Archiving of quiz attempts as PDF and HTML files
- Support for file submissions / attachments (e.g., essay files)
- Quiz attempt reports are accessible completely independent of Moodle, hereby ensuring long-term readability
- Customization of generated PDF and HTML reports
  - Allows creation of reduced reports, e.g., without example solutions, for handing out to students during inspection
- Support for complex content and question types, including Drag and Drop, MathJax formulas, STACK plots, and other
  question / content types that require JavaScript processing
- Quiz attempt reports are fully text-searchable, including mathematical formulas 
- Generation of checksums for every file within the archive and the archive itself
- Attempt report names are fully customizable and support dynamic variables (e.g., course name, quiz name, username, ...)
- Allows definition of global archiving defaults as well as forced archiving policies (i.e., locked archive job presets
  that cannot be changed by the user)
- Fully asynchronous archive creation to reduce load on Moodle Server
- Data compression and vector based MathJax formulas to preserve disk space
- Technical separation of Moodle and archive worker service
- Data-minimising and security driven design


## Installation

Activity archiving drivers (`archivingmod`) are sub-plugins of the archiving subsystem core (`local_archiving`) and
therefore require the core plugin to be installed. They then must be placed inside your Moodle directory under
`local/archiving/driver/mod`.

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
