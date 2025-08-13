# Activity Archiving Drivers

This document defines the interface that [activity archiving driver](../components/activity-archiving-drivers.md)
implementations must adhere to.


## Overview

The abstract driver base class for activity archiving drivers is {{ source_file('classes/driver/archivingmod.php',
'\\local_archiving\\driver\\archivingmod') }}.

!!! notice "Overview reduced for bravery"
    For bravery, the following overview diagram is reduced to the most important classes and members. Therefore, some 
    details like methods, parameters, or members are omitted. Please refer to the {{ source_file('', 'plugin source code') }}
    for a complete reference.
    

```mermaid
classDiagram
    direction TB

    class archivingmod {
        <<abstract>>
        #context: context_module
        #cmid: int
        #courseid: int

        +get_supported_activities()$ string[]
        +can_be_archived() bool
        +execute_task(task: activity_archiving_task) void
        +get_task_content_metadata(task: activity_archiving_task) task_content_metadata[]
        +get_job_create_form(handler: string, cminfo: cm_info) job_create_form
    }
    
    class base {
        <<abstract>>
        +ALLOWED_PLUGIN_TYPES: const string[]
            
        +get_frankenstyle_name() stdClass
        +get_plugin_type() string
        +get_plugin_name() string
        +is_ready()$ bool
        +is_enabled() bool
    }
    
    class archivingmod_quiz {
    }

    class archivingmod_assign {
    }

    class archivingmod_other {
    }
    
    class activity_archiving_task {
        #taskid: int
        #archivejob: archive_job
        #archivingmod: archivingmod
        #context: context_module
        #userid: int
        #status: activity_archiving_task_status
        #settings: stdClass
        #metadata: task_content_metadata[]
        
        +create() activity_archiving_task
        +get(id: int) activity_archiving_task
        +get_progesss() int
        +link_artifact(artifactfile: stored_file) void
        +generate_artifactfile_info(filename: string) stdClass
    }
    
    class task_content_metadata {
        +taskid: int
        +userid: int
        +reftable: string|null
        +refid: int|null
        +summary: string|null
    }
    
    class archive_job {
        #id: int
        #context: context_module
        #courseid: int
        #cmid: int
        #userid: int
        #status: archive_job_status
        #settings: stdClass
        
        +create() archive_job
        +get(id: int) archive_job
        +delete() void
        +enqueue() void
        +execute() void
    }
    
    class activity_archiving_task_status {
        <<enumeration>>
        UNINITIALIZED
        CREATED
        AWAITING_PROCESSING
        RUNNING
        FINALIZING
        FINISHED
        CANCELED
        FAILED
        TIMEOUT
        UNKNOWN
    }

    %% Relationships
    base  <|--  archivingmod
    archivingmod <|-- archivingmod_quiz
    archivingmod <|-- archivingmod_assign
    archivingmod <|-- archivingmod_other
    activity_archiving_task -- archivingmod
    task_content_metadata -- archivingmod
    archive_job -- activity_archiving_task
    activity_archiving_task_status -- activity_archiving_task
    
    %% style
    style archive_job fill:#dedede,stroke:#666666
    style activity_archiving_task fill:#dedede,stroke:#666666
    style task_content_metadata fill:#dedede,stroke:#666666
    style activity_archiving_task_status fill:#dedede,stroke:#666666
```


## Implementation

Each activity archiving driver must implement the {{ source_file('classes/driver/archivingmod.php',
'\\local_archiving\\driver\\archivingmod') }} interface with a class, placed at the following location:
`/local/archiving/driver/mod/<pluginname>/classes/archivingmod.php`, where `<pluginname>` is the name of the activity
archiving driver (e.g., `quiz`, `assign`, ...).

Each activity archiving driver specifies the mod types that it supports via the `get_supported_activities()` method.
During creation, each activity archiving driver instance is bound to a specific activity instance by its respective
module context (`\context_module`). The method `can_be_archived()` is then used to determine whether the
targeted activity is ready to be archived or not.

When creating a new archive job, the method `get_job_create_form()` returns the Moodle form that is presented to the
user upon archive job creation, containing all necessary options for the activity archiving task of the targeted activity.
All form data will be stored inside the created archive job and can be accessed via the job settings API.

After creation and once scheduled for execution, the archiving manager will call the `execute_task()` method of the
activity archiving driver with the activity archiving task that should be processed. If an task executor yields before
the job is finished, it will be re-executed periodically until it is either finished or canceled. Lastly, at any point
in time, the `get_task_content_metadata()` method can be used to retrieve metadata about the data that is being targeted
by a specific activity archiving task. This metadata will be processed by the archiving manager and stored inside the
archive jobs metadata record.
