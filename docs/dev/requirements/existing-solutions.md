# Existing Solitions

This section lists existing solutions for archiving data from different Moodle activities.

!!! info
    You can click on the plugin name to get more information about the plugin.


## Quizzes


### [Moodles built-in attempt review page (print as PDF)](https://docs.moodle.org/en/Quiz_reports)

- Requires manual interaction and is not suitable for bulk archiving
- Renders all contents and question types, including MathJax and GeoGebra
- Data is stored on the teachers PC
 

### [Quiz Archiver](https://moodle.org/plugins/quiz_archiver)

- Fully automated archiving of quiz attempts as PDF files and many more features
- Support for file submissions / attachments (e.g., essay files)
- Renders all contents and question types, including MathJax and GeoGebra
- Customization of generated PDF and HTML reports
- Moodle backups (.mbz) of both the quiz and the whole course are supported
- Generation of checksums for every file within the archive and the archive itself
- Cryptographic signing of archives and their creation date using the Time-Stamp Protocol (TSP)
- Fully asynchronous archive creation to reduce load on Moodle Server
- Data is stored inside the Moodle LMS


### [Quiz Archive Report](https://moodle.org/plugins/quiz_archive)

- Renders all quiz attempts on a single page for manual printing to PDF
- Fails for quizzes with many attempts
- No customization of the PDF files possible
- Data is stored on the teachers PC


### [Quizattemptexport](https://github.com/MoodleNRW/moodle-local_quizattemptexport)

- Renders quiz attempts as PDF files
- Does not support all question types and has problems with dynamic content (e.g., MathJax and GeoGebra)
- Relies on old binaries with multiple security vulnerabilities for rendering the PDFs
- Runs directly on the Moodle server and uses `wkhtmltopdf` for rendering
- Data is stored inside the Moodle LMS
- [Comparison of _Quiz Archiver_ and _Quizattemptexport_](https://moodlenrw.de/course/view.php?id=125)

### [QuizExport](https://moodle.org/plugins/quiz_export)

- Renders attempts as PDF files
- Runs directly on the Moodle server and uses `mpdf` for rendering


### [Exportresults](https://moodle.org/plugins/quiz_exportresults)

- Exports student answers into `.odt` files


## Assignments


### [Assign Submission Download](https://moodle.org/plugins/local_assignsubmission_download)

- Allows bulk-downloading of submitted files
- Limited to files only

### [Assessment Archive](https://github.com/innocampus/moodle-local_assessment_archive)

- Creates Moodle backups (.mbz) of assignments
- Signs the backups using TSP


## Miscellaneous

### [Block „Course Files Archive“ bzw. „Externe Dateiarchivierung](https://collaborate.hn.de/display/PROJMOODLE/Block+Externe+Dateiarchivierung)"

- Allows to store additional arbitrary files that will be part of a course archive
- Documentation missing
- See also: [https://github.com/Wunderbyte-GmbH/moodle_blocks_coursefilesarchive](https://github.com/Wunderbyte-GmbH/moodle_blocks_coursefilesarchive)
