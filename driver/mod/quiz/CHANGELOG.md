# Changelog

## Version X.Y.Z (YYYYMMDDXX)

- Add attempt report setting for showing / hiding overall quiz grade
- Add attempt report setting for showing / hiding question correctness indicators
- Add attempt report setting for showing / hiding raw marks for questions
- Add support for receiving chunked uploads to enable the transfer of large quiz archives independent of the upload limit
- Fix rendering of overall quiz feedback
- Force wrapping of long lines in code boxes to prevent overflowing out of page boundaries
- Reduce padding of comment boxes within code boxes to prevent them from overlapping student code
- Optimize main report container spacing to reduce the amount of whitespace in the generated PDF
- Migrate quiz attempt renderer to new quiz attempt summary API
- Prevent `update_task_status` external functions from making changes to activity archiving tasks that belong to other activity types


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
