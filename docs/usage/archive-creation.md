# Archive Creation

The archive creation form can be reached from the [course archiving overview](overview.md) by clicking on the desired
activity or directly from within the activity by clicking on {{ moodle_nav_path('More', 'Archiving') }} inside the
secondary navigation.

![Quiz archive creation form](../assets/screenshots/course_create_quiz_archive.png)

The form elements largely depend on the targeted activity type. In the example above, a quiz activity is being archived.
All settings feature a comprehensive help text that explains their effect in detail. It can be accessed by hovering over
the question mark symbol next to each setting.

To create the archiving job, click the {{ mform_element('Create archive', 'button') }} button at the bottom of the
form. You should now see a confirmation message and a newly created job in the table at the bottom of the page. You can
monitor the progress of the archive job by clicking on the refresh button in the top right corner of the table or by
navigating to the job logs page of the respective archive job.

If you wish to select another activity, use the {{ mform_element('Cancel', 'button') }} button to return to the
archiving overview without creating a new job.

!!! info "Locked settings"
    Some settings might be locked by the administrator to enforce organization-wide policies. Locked settings are
    indicated by a greyed-out appearance and can not be changed by the user.


## Archive naming

When creating any new activity archive, the {{ mform_element ('Advanced settings', 'section') }} section will always
contain the {{ mform_element('Archive name', 'text') }} option for naming the archive itself. Patterns supplied here
may contain plain text and variables. Variables must use the `${variablename}` syntax. The file extension is added
automatically, so do not add an extension yourself.

One example of an archive name might be `${cmname}-archive-${courseshortname}-${date}`, which results in an archive with
the file name `quiz-archive-MATH101-2026-08-07.zip`.

### Available variables

The table below lists all available variables:

| Variable             | Description                        |
|----------------------|------------------------------------|
| `${courseid}`        | Course ID                          |
| `${coursename}`      | Course name                        |
| `${courseshortname}` | Course short name                  |
| `${cmid}`            | Course module ID                   |
| `${cmtype}`          | Activity type                      |
| `${cmname}`          | Activity name                      |
| `${date}`            | Current date (`YYYY-MM-DD`)        |
| `${time}`            | Current time (`HH-MM-SS`)          |
| `${timestamp}`       | Current Unix timestamp             |

!!! info
    This list may not be exhaustive. Please check the help text of the respective option in Moodle itself. It will
    always contain an up-to-date list of all variables that your current plugin version supports.

### Naming rules

The following characters are forbidden for generated archive names: `.`, `:`, `;`, `*`, `?`, `!`, `"`, `<`, `>`, `|`,
and `/`.
