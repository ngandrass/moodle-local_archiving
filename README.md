# moodle-local_archiving
Moodle plugin for archiving various activities 

# WARNING: DO NOT USE THIS PLUGIN YET!

This plugin is currently under active development. Please do **not use this plugin in production environments** yet!

Once a stable version is released, this warning will be removed.


## Building the documentation

Prior to any build, you have to install the dependencies using [Poetry](https://python-poetry.org/):

```bash
poetry install
```

### Locally with live preview

To run a local webserver that automatically re-builds the documentation on changes run:

```bash
poetry run mkdocs serve
```

### Building for online deployment

To build the full documentation for deployment on a webserver run:

```bash
poetry run mkdocs build
```

The resulting HTML files can be found in the `site/` directory.

### Building for offline-use without a webserver

To build the full documentation for offline use without a webserver run:

```bash
OFFLINE=true poetry run mkdocs build
```

The resulting HTML files can be found in the `site/` directory.
