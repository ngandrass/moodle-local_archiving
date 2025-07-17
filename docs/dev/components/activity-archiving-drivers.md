# Activity Archiving Drivers

The activity archiving drivers are responsible for the actual archiving process of a specific Moodle activity. One such
driver exists for every Moodle activity that is supported by the archiving system.


## Tasks and Responsibilities

!!! abstract "Activity Data Extraction and Transformation"
    Transforms existing user data into an archivable format
    
    - Collects all relevant data from the activity (metadata, text submissions, files, ...)
    - Can make use of [Worker Services](worker-services.md), if required

!!! abstract "Archive Creation"
    Creates digital archive files
    
    - Created archives are stored inside the Moodledata storage, awaiting further processing by the
      [Archiving Manager](archiving-manager.md)

!!! abstract "Archive Data Forwarding"
    Sends archived data back to the [Archiving Manager](archiving-manager.md) once archiving is completed

!!! abstract "Task Processing"
    - All archiving jobs are always executed asynchronously
    - Multiple archiving jobs can be processed in parallel

!!! abstract "Task Configuration"
    Providing activity-driver-specific configuration options for jobs (e.g., file format, PDF paper size, ...)

!!! abstract "Task Scope / Filtering"
    Allowing to define the scope of the archived data (e.g., all quiz attempts, only attempts of a specific user, only
    one specific attempt)


## Interfaced Components

- [Archiving Manager](archiving-manager.md)
- [Worker Services](worker-services.md) (Optional)


## Implementation

Each activity archiving driver must implement the {{ source_file('classes/driver/archivingmod.php', '\\local_archiving\\driver\\archivingmod') }} interface with a class, placed
at the following location: `/local/archiving/driver/mod/<pluginname>/classes/archivingmod.php`, where `<pluginname>` is
the name of the activity archiving driver (e.g., `quiz`, `assign`, ...).

[:material-file-code-outline: Activity Archiving Driver API](../api/activity-archiving-drivers.md){ .md-button }

Once an archive job reached the activity archiving phase, the [archiving manager](archiving-manager.md) will create an
activity archiving task for the specific activity type and call the responsible activity archiving driver. The activity
archiving driver will then extract the required information, create one or more artifact files, and returns them to the
[archiving manager](archiving-manager.md). All further processing is done by other components.


## Examples

### Quiz (mod_quiz)

- Exports quiz attempts as fully-rendered PDF files
- Support for complex content and question types, including Drag and Drop, MathJax formulas, STACK plots, and other
  question / content types that require JavaScript processing
- Exports answer history, marks, comments, and feedback
- Support for file submissions / attachments (e.g., essay files)
- Customization of generated PDF and HTML reports
- Quiz attempt reports are fully text-searchable, including mathematical formulas
- Generation of Moodle backups (.mbz) of the quiz
- Generation of checksums for every file within the archive and the archive itself
- Data compression and vector based MathJax formulas to preserve disk space
- Based on the Moodle [Quiz Archiver](https://moodle.org/plugins/quiz_archiver) Plugin


### Assignment (mod_assign)

- Exports ...
    - Assignment metadata, instructions, text submissions, submission metadata, grading, feedback, and comments as PDF
      file(s)
    - Submitted files
    - Annotated feedback / PDF files
- Text submissions are fully-rendered PDF files to support complex content and formatting
- Generation of Moodle backups (.mbz) of the assignment
- Generation of checksums for every file within the archive and the archive itself
- Data compression and vector based MathJax formulas to preserve disk space
