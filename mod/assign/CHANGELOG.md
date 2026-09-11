# Changelog

## Version X.Y.Z (YYYYMMDDXX)

- Implement full assignment submission archiving pipeline: submission report generation, metadata retrieval, artifact upload, and task status webservices
- Add remote archive worker driver to enqueue and track archiving jobs on the external worker service
- Add submission report renderer with configurable report sections (header, instructions, submission, status, comments, feedback, grade, grading details)
- Add configurable attachment handling (assignment, submission, feedback, annotation files) with per-type selection in the job creation form
- Add folder name and file name pattern generation for archived submissions
- Add admin settings for worker service connection and archive flattening
- Finalize Moodle privacy API provider
- Rename dependency from moodle-quiz-archive-worker to moodle-archiving-worker


## Version 0.0.3 (2025102700)

- Add Moodle privacy API stub provider


## Version 0.0.2 (2025101300)

- Ensure Moodle 5.1 compatibility
- Refactor code to comply with new Moodle coding standard v3.6
- Add stub implementation for cm state fingerprinting
- Add Moodle plugin CI for all supported Moodle versions


## Version 0.0.1 (2025081900)

- Base implementation of archiving subsystem APIs and creation of a stub artifact file
