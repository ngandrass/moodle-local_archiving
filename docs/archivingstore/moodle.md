# Storage Driver: Moodle Filestore

This simple storage driver stores all files inside the
[Moodle filestore](https://moodledev.io/docs/apis/subsystems/files), also known as _"Moodledata"_. This means that no
additional setup is required and everything works out of the box. All files are stored locally on the Moodle server and
are therefore directly accessible via the Moodle UI.


## Configuration

No special configuration is required for this storage driver. It is enabled by default and can be selected during
[archive job creation](../usage/archive-creation.md) or set as a default storage driver in the
[job presets](../setup/config/job-presets.md) settings.

If you want to prevent users from storing archives in the Moodle filestore, you can disable this storage driver on the
[components overview page](../setup/config/components.md) or via the {{ mform_element('Enable', 'checkbox') }} checkbox
on the plugin settings page.


## File structure

Files are automatically associated with the respective archive job and stored inside the _"Moodledata"_ directory by
Moodle. Admins do not need to specify any paths or directories, as this is handled automatically by Moodle.

!!! example "Technical implementation details"
    This information is for developers and administrators only: All files are stored via the Moodle file API 
    using the values listed below. See 
    {{ source_file('store/moodle/classes/archivingstore.php', '\\archivingstore_moodle\\archivingstore::store()') }} 
    for the full implementation details.
    
    | Parameter   | Value                                 |
    |-------------|---------------------------------------|
    | `contextid` | _Context ID of the archived activity_ |
    | `component` | `archivingstore_moodle`               |
    | `filearea`  | `archive`                             |
    | `itemid`    | _ID of the archive job_               |
    | `filepath`  | `/{$contextid}/{$jobid}/`             |
    
