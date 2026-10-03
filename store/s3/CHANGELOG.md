# Changelog

## Version 1.0.0 (2026100100)

- Initial release of the S3 storage driver for the Moodle archiving subsystem 🎉
- Implements store, retrieve, and delete functionality.
- Supports asynchronous file retrieval from object storage.
- Periodically report upload progress to job log during processing.
- Automatically test S3 connection and bucket access rights during plugin configuration.
- Detection and handling of stalled up- and downloads
