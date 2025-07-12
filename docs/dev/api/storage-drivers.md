# Storage Drivers

This document defines the interface that [storage driver](../../components/storage-drivers.md) implementations must adhere
to.

!!! warning "Work in Progress (WIP)"
    This section is still under active development. Information and specifications can still be changed in the future.


## Overview
```mermaid
classDiagram 
    namespace ArchivingLogic{
        class ArchivingManager {
            +manageArchive(task: StorageTask): void
        }
        class StorageTask {
            +data: any
            +operation: str
        }   
    }
    namespace Supporting{
        class LoggingMechanism {
            +logRequest(source: str, action: str, data: any): void
            +logActivity(source: str, message: str): void
        }
            class NotificationService {
            +notifySuccess(message: str): void
            +notifyFailure(message: str): void
        }

        class ErrorHandler {
            +handleError(error: Exception): void
            +retryOperation(operation: Callable): void
            +switchToAlternative(driver: StorageDriver): void
            +restartWorker(): void
        }
    }
    namespace Storing{
        class StorageAPI {
            +getStorageDriver(driver_type: str): StorageDriver
        }

        class StorageDriver {
            <<interface>>
            +store(data: any): void
            +retrieve(key: str): any
            +delete(key: str): void
        }

        class LocalFileStorageDriver {
            +store(data: any): void
            +retrieve(key: str): any
            +delete(key: str): void
        }

        class S3StorageDriver {
            +store(data: any): void
            +retrieve(key: str): any
            +delete(key: str): void
        }

        class NoSQLStorageDriver {
            +store(data: any): void
            +retrieve(key: str): any
            +delete(key: str): void
        }

        class StorageDriverFactory {
            +get_storage_driver(driver_type: str): StorageDriver
        }

        class AsyncTask {
            +processTask(task: StorageTask): void
        }
    }

    ArchivingManager --> StorageAPI : "requests driver"
    ArchivingManager --> ErrorHandler : "reports erros"
    ArchivingManager --> LoggingMechanism : "logs activities"
    NotificationService --> ArchivingManager : "listens for notifications"
    StorageAPI --> StorageDriverFactory : "uses"
    StorageDriverFactory --> StorageDriver : "creates"
    AsyncTask --> StorageDriver : "uses"
    AsyncTask --> ErrorHandler : "reports errors"
    ErrorHandler --> NotificationService : "sends notifications"
    StorageAPI --> LoggingMechanism : "logs requests"
    StorageDriverFactory --> LoggingMechanism : "logs activities"
    StorageDriver --> LoggingMechanism : "logs actions"

    StorageDriver <|.. LocalFileStorageDriver
    StorageDriver <|.. S3StorageDriver
    StorageDriver <|.. NoSQLStorageDriver
```

## Implemented Concepts

1. **Encapsulation of Storage Drivers through an API**  
   The StorageDriver implementations are abstracted behind an API that serves as a central entry point. This provides a
   clear separation between the logic for selecting drivers and their actual usage, enhancing maintainability and
   scalability.
2. **Asynchronous Communication and Callback Mechanism**  
   An asynchronous communication mechanism ensures efficient processing without blocking delays. Callback mechanisms
   allow receiving notifications, such as errors or successes, and facilitate exception handling.
3. **Extension Point via Strategy Pattern**  
   Using the Strategy Pattern enables the flexible integration of different storage solutions. New StorageDrivers can be
   added without modifying the existing application, simply by providing a new implementation.
4. **Logging / Notification Mechanism**  
   A logging mechanism records detailed information about what data was stored, when, and where. These logs support
   traceability of operations and assist in error analysis. Defines log and error levels and is responsible for managing
   the error status.
