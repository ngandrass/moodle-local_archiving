# Sub-Plugins

The [archiving core plugin](core.md) is distributed with a default set of sub-plugins (e.g., activity archiving drivers,
storage drivers, ...). These will be automatically installed alongside the core plugin. Adding and removing any of the
available sub-plugins is described in this section.

You can find an overview of available sub-plugins types as well as a list of available sub-plugins on the
[components page](../../components.md):

[:fontawesome-solid-cubes: Components Overview](../../components.md){ .md-button }


## Installing sub-plugins

All sub-plugins must be installed inside a plugin-type specific subfolder inside the `local/archiving/driver` directory
of your Moodle installation. The table below lists the different installation locations.

| Sub-plugin type                                                             | Component          | Installation directory           |
|-----------------------------------------------------------------------------|--------------------|----------------------------------|
| [Activity archiving driver](../../components.md#activity-archiving-drivers) | `archivingmod`     | `local/archiving/driver/mod`     |
| [Storage driver](../../components.md#storage-drivers)                       | `archivingstore`   | `local/archiving/driver/store`   |
| [Archiving trigger](../../components.md#archiving-triggers)                 | `archivingtrigger` | `local/archiving/driver/trigger` |
| [External event connector](../../components.md#external-event-connectors)   | `archivingevent`   | `local/archiving/driver/event`   |

After placing the sub-plugins code inside the correct directory (e.g., `local/archiving/driver/mod/quiz` for
`archivingmod_quiz`), log into your Moodle site as an admin and go to _Site administration_ to complete the
installation.

!!! info "Check the sub-plugin documentation"
    Sub-plugins may require additional setup steps or dependencies to be met. Please always check the documentation of
    the sub-plugin you are installing for additional information.


## Verifying installation

After the installation is complete, you should see the sub-plugin listed under: _Site administration > Plugins > Local
plugins > Archiving > Manage Components_.


## Uninstalling

To remove sub-plugin perform the following steps:

1. Log in to your Moodle site as an admin.
2. (Optional) Go to _Site administration > Plugins > Local plugins > Archiving > Manage components_, find the sub-plugin
   you want to remove and make sure it is disabled, if applicable.
3. Navigate to _Site administration > Plugins > Plugin overview_.
4. Find the sub-plugin and click on _Uninstall_ next to it.
5. Let the uninstallation process run.
6. Delete the plugin files from your Moodle folder.
