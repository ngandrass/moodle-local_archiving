# Storage Drivers

Storage drivers are responsible for safely transferring a finished archive to a specific storage location. This can be,
for example, the Moodledata storage or an S3 compatible WORM storage. Having multiple storage drivers available allows
for a flexible adaptation to existing archiving and storage systems.

The following storage drivers are currently available:

[:material-folder-open: Local Directory](localdir.md){ .md-button }
&nbsp;&nbsp;
[:simple-moodle: Moodle Filestore](moodle.md){ .md-button }
&nbsp;&nbsp;
[:fontawesome-solid-cubes: S3 Object Store](s3.md){ .md-button }
