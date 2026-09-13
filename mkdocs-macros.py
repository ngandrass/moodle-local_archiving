# Python hooks for mkdocs-macros
#
# Copyright (C) 2025 Niels Gandraß <niels@gandrass.de>
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.


def define_env(env):
    """ Hooks / Macros for mkdocs-macros """

    @env.macro
    def source_file(filepath, title=None):
        """
        Renders a link to the given source file in the repository.

        :param filepath: Path to the file in the repository (e.g., 'classes/archive_job.php').
        :param title: Optional title for the link. If not provided, the filepath will be used as the title.
        """

        # Use filepath as title if no custom title is provided
        if not title:
            title = filepath

        # Build full URL to file in repo
        baseurl = env.conf['repo_url'].lstrip('/') + '/blob/master/'
        url = baseurl + filepath.lstrip('/')

        # Build markdown link to the source file
        return f'<a href="{url}" target="_blank"><code>{title}</code><sup>:material-code-block-tags:</sup></a>'

    @env.macro
    def moodle_nav_path(*args):
        """
        Renders a Moodle navigation path for the given arguments.

        :param args: Strings of navigation path components.
        :return: Rendered Moodle navigation path.
        """
        navpath = ' / '.join(args)

        return f'<span style="padding: 2px 6px; border-radius: 8px; background-color: #f9f9f9; border: 1px solid #cccccc;">:simple-moodle: <span style="font-size:80%;">{navpath}</span></span>'

    @env.macro
    def mform_element(title, type ='text'):
        """
        Renders a Moodle form element with the given name.

        :param title: title of the element to render.
        :param type: Type of the element (default is 'text').
        :return: Rendered Moodle form element box.
        """

        # Determine icon to use
        icon = ':material-cursor-default-click-outline:'
        if type == 'text':
            icon = ':material-form-textbox:'
        elif type == 'checkbox':
            icon = ':material-toggle-switch-outline:'
        elif type == 'select':
            icon = ':material-form-select:'
        elif type == 'section':
            icon = ':material-chevron-down-box-outline:'
        elif type == 'button':
            icon = ':material-button-cursor:'

        # Render the Moodle form element indicator with the icon and title
        return f'<span style="padding: 2px 6px; border-radius: 8px; background-color: #f9f9f9; border: 1px solid #cccccc;">{icon} <span style="font-size:80%;">{title}</span></span>'
