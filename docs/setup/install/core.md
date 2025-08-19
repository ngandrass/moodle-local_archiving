# Archiving Subsystem Core

You can install this plugin like any other Moodle plugin, by either placing it inside your Moodle installation folder or
by uploading it as a ZIP archive via the website administration. Both methods are described below.


## Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code.
3. Check the plugin validation report and finish the installation.


## Installing manually

The plugin can be also installed by putting its source code into:

```text
{your/moodle/dirroot}/local/archiving
```

Afterward, log in to your Moodle site as an admin and go to _Site administration > Notifications_ to complete the
installation. Alternatively, you can run `php admin/cli/upgrade.php` to complete the installation from the command line.


## Verifying installation

After the installation is complete, you should see the plugins settings listed under: _Site administration > Plugins >
Local Plugins > Archiving_.


## Uninstalling

Uninstalling the plugin will remove all data that the plugin stores. This includes all archive job metadata, logs, and
archives that are stored within the internal Moodle file store. However, this will not delete any archives that are
stored on external storage systems. For removing these, please refer to the documentation of the respective storage
drivers.

To remove the plugin perform the following steps:

1. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Plugin overview_.
2. Find `local_archiving` and click on _Uninstall_ next to it.
3. Let the uninstallation process run.
4. Delete the plugin files from your Moodle folder.


## Next Steps

The archiving subsystem core plugin is distributed with a default set of sub-plugins (e.g., activity archiving drivers,
storage drivers, ...). If you are happy with the default set, you can continue configuring the plugin and start using
it:
 
[:material-cog: Configuration](../config/index.md){ .md-button }

If you would like to add or remove sub-plugins, please refer to the corresponding section in the documentation:

[:material-cube-outline: Installation: Sub-Plugins](plugins.md){ .md-button }
