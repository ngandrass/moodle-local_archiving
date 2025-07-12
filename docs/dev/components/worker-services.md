# Worker Services

Worker services can be used by [activity archiving drivers](activity-archiving-drivers.md) to offload heavy processing
tasks from the Moodle system and enable the archiving system to perform complex transformations.

!!! warning "Work in Progress (WIP)"
    This section is still under active development. Information and specifications can still be changed in the future.


## Tasks and Responsibilities

!!! abstract "Offloading Heavy Processing Tasks"
    Performs off-loaded complex or heavy archiving tasks, e.g., web page to PDF rendering

!!! abstract "Communication Interface"
    Communicates via the Moodle Webservice API

    - Fully specified REST API via Moodle webservice API
    - Webservice tokens are only valid for a single task and are automatically invalidated after task completion or
      timeout
    - Webservice tokens only allow access to information that is required for the specific task

!!! abstract "Deployment and Encapsulation"
    Worker services are deployed independent of Moodle. They should be shipped as self-contained Docker containers to
    allow easy deployment.

!!! abstract "Status Reporting"
    Providing status information about current tasks as well as the worker service itself

!!! abstract "Multiple Task Handling"
    Worker services must be able to handle multiple tasks in a queue. A worker service should allow multiple tasks to be
    processed in parallel.

!!! abstract "Configurability"
    Allow configuration of the worker service, e.g., maximum number of parallel tasks, maximum task runtime, ...


## Interfaced Components

- [Activity Archiving Drivers](activity-archiving-drivers.md)


## Implementations

### Quiz Archive Worker

![](../quiz-archiver-architecture.drawio)
