# Changelog

## Version 1.1.0 (2026100300)

- Allow exporting only the latest quiz attempt of each user in the generated archive
- Add support for receiving chunked uploads to enable the transfer of large quiz archives independent of the upload limit
- Add support for question type [JACK](https://github.com/Wunderbyte-GmbH/moodle_qtype_jack).
- Add "Question internals" attempt report section, showing question ID, question bank version, ID number, and tags above each question
- Add attempt report setting for showing / hiding overall quiz grade
- Add attempt report setting for showing / hiding question correctness indicators
- Add attempt report setting for showing / hiding raw marks for questions
- List all defined grade items individually below the overall quiz grade in attempt report headers
- Create advanced job option to add correction margins to the right side of generated attempt reports
- Display the attempting user's email address inside the attempt report header
- Add `${email}` variable for attempt file- and folder name patterns
- Add human-readable date and time variables (`YYYY-MM-DD_HH-MM-SS`) for attempt file- and folder name patterns:
    - `${opendatetime}`: Quiz opening date and time
    - `${closedatetime}`: Quiz closing date and time
    - `${startdatetime}`: Attempt start date and time
    - `${finishdatetime}`: Attempt finish date and time
- Include user email address in attempt metadata queries and the `get_attempts_metadata` web service response
- Add an option to include or exclude the quiz attempts metadata CSV file
- Automatically reschedule archive job for immediate execution if the worker service finished successfully
- Fix rendering of overall quiz feedback
- Force wrapping of long lines in code boxes to prevent overflowing out of page boundaries
- Reduce padding of comment boxes within code boxes to prevent them from overlapping student code
- Optimize main report container spacing to reduce the amount of whitespace in the generated PDF
- Prevent instance-specific modifications to Moodle header and footer from leaking into printed PDFs (thanks to @abias !)
- Fix bug in dynamic file and folder name validation
- Fix `taskid` parameter type (was string, now int) in `process_uploaded_artifact` web service function
- Setup course and module in `$PAGE` object during `generate_attempt_report` web service function
- Forcefully disable unlocked attempt report sections that depend on another disabled section
- Migrate quiz attempt renderer to new quiz attempt summary API
- Adapt to archiving core refactoring. Now requires `local_archiving` version `2026100300` or higher
- Ensure Moodle 5.2 compatibility
- Add archive worker service link to privacy provider


## Version 1.0.0 (2025102700)

- First stable release 🎉
- Simplify web service setup process
    - Bundle web service functions for worker communication inside a statically provided web service
    - Remove superfluous admin settings for manual web service setups
    - Remove superfluous autoinstall feature that was superseded by the statically provided web service
- Finalize task flow logic for activity archiving tasks
- Finalize Moodle privacy API provider
- Adapt web service unit tests to latest activity archiving task access token invalidation behavior
- Fix language strings in job creation form validator
- Create unit tests for various miscellaneous components


## Version 0.3.0 (2025101300)

- Ensure Moodle 5.1 compatibility
- Refactor code to comply with new Moodle coding standard v3.6


## Version 0.2.0 (2025092100)

- Implement course module state fingerprinting based on quiz and attempt modification times
- Adapt test data generator to new archiving trigger API
- Add Moodle plugin CI for all supported Moodle versions
- Fix import of legacy compatibility layers in unit tests
- Add missing language strings
- Fix unit test for archive task status update web service function

**ATTENTION:** This version requires `local_archiving` version 0.2.0 (2025092100) or higher.


## Version 0.1.0 (2025081900)

- Initial release with all the core functionality implemented
