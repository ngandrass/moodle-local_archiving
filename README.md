# Archiving Subsystem for Moodle

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

A Moodle plugin that archives student data for long-term storage. It provides an extensible framework for archiving data
from various Moodle activities, such as quizzes and assignments, and transferring it to different storage locations,
such as the Moodle file store or external storage systems.

More information about the project, its features, screenshots, and installation instructions can be found in the
[official documentation](https://archiving.gandrass.de/).

[![Archiving Subsystem: Official Documentation](docs/assets/images/docs-button.png)](https://archiving.gandrass.de/)


## Installation and Configuration

You can find detailed installation and configuration instructions within the
[official documentation](https://archiving.gandrass.de/).

[![Archiving Subsystem: Official Documentation](docs/assets/images/docs-button.png)](https://archiving.gandrass.de/)

If you're having problems installing the archiving subsystem or have further questions, please feel free to open an
issue within the [GitHub issue tracker](https://github.com/ngandrass/moodle-local_archiving/issues).


## Screenshots

This section contains various screenshots of the archiving subsystem core as well as examples from the available
sub-plugins. The screenshots shown here do not cover the full depth of the plugins functionality, but they should give
you a good first impression of the plugin and its features.

### User interface

This section contains screenshots of the pages that are visible to managers.

#### Course archiving overview page
![Screenshot of the archiving overview within a Moodle course](docs/assets/screenshots/course_archiving_overview.png)

#### Creating a new archive
![Screenshot of the quiz archive creation form](docs/assets/screenshots/course_create_quiz_archive.png)

#### Downloading archived data
![Screenshot of the artifact download page](docs/assets/screenshots/course_quiz_archive_download_artifacts.png)

#### Inspecting archive job logs
![Screenshot of the archive job log inspection page](docs/assets/screenshots/course_archive_job_logs.png)

### Archive contents

This section contains example screenshots of archived data.

#### Example of PDF report (excerpt)
![Image of example of PDF report (extract): Header](docs/assets/screenshots/quiz_archiver_report_example_pdf_header.png)
![Image of example of PDF report (extract): Question 1](docs/assets/screenshots/quiz_archiver_report_example_pdf_question_1.png)
![Image of example of PDF report (extract): Question 2](docs/assets/screenshots/quiz_archiver_report_example_pdf_question_2.png)
![Image of example of PDF report (extract): Question 3](docs/assets/screenshots/quiz_archiver_report_example_pdf_question_3.png)

### Admin interface and settings

This section contains screenshots of the admin interface and some configuration options.

#### Manage components
![Screenshot of the admin components management interface of the plugin](docs/assets/screenshots/admin_manage_components.png)

#### Common settings
![Screenshot of the common admin settings of the plugin](docs/assets/screenshots/admin_common_settings.png)

#### Quiz archiving presets (excerpt)
![Screenshot of quiz archiving presets](docs/assets/screenshots/admin_archivingmod_quiz_presets.png)


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
