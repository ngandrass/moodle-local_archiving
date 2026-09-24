# Changelog

## Version X.Y.Z (YYYYMMDDXX)

- Initial release of the S3 storage driver for the Moodle archiving subsystem.
- Implements store, retrieve, and delete functionality.
- Supports asynchronous file retrieval from object storage.
- Periodically report upload progress to job log during processing.
- Automatically test S3 connection and bucket access rights during plugin configuration.
