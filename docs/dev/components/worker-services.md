# Worker Services

Worker services can be used by [activity archiving drivers](activity-archiving-drivers.md) to offload heavy processing
tasks from the Moodle server and enable the archiving system to perform complex transformations (e.g., PDF exports).


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


## Implementation

Worker service implementations do not have to follow strict rules and can be tailored to the respective [activity
archiving drivers](activity-archiving-drivers.md) that use them. However, they all should:

1. Use the Moodle web services / external API
2. Grant as least privileges as possible
3. Grant privileges only for the exact time an activity archiving task is processed
4. Be easy to deploy and configure


## Examples

### Quiz Archive Worker

An example of one such worker service is the [Quiz Archive Worker Service](https://github.com/ngandrass/moodle-quiz-archive-worker)
that is used in conjunction with the [Moodle Quiz Archiver Plugin](https://moodle.org/plugins/quiz_archiver).

The following diagram depicts the general architecture and information flow of the Moodle plugin and the corresponding
worker service:

![](../quiz-archiver-architecture.drawio)
