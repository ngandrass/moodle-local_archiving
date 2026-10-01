# Changelog

## Version X.Y.Z (YYYYMMDDXX)

- Implement full assignment submission archiving pipeline: submission report generation, metadata retrieval, and status reporting
- Make submission reports configurable with various sections (header, instructions, submission, comments, feedback, grading details, ...)
- Allow configurable file attachment handling (assignment, submission, feedback, annotation files) with per-type selection
- Provide machine-readable assignment submissions metadata export in CSV format
- Add folder name and file name pattern generation for archived submissions
- Allow to decide between flat and hierarchical archive folder structure
- Re-use existing Moodle archiving worker service for report generation
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
