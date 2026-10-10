# Changelog

## Version 1.0.0 (2026101000)

- Initial release of the event-based archiving trigger
- Automatically creates archive jobs whenever selected Moodle events occur inside supported activities (e.g., a submitted quiz attempt or a graded assignment submission)
- Freely selectable events per activity type, as exposed by the respective activity archiving drivers
- Only archives the object that triggered the event (e.g., a single quiz attempt or assignment submission)
- Supports the configured archive job presets for all created archive jobs
- Prevents the creation of identical archive jobs while a previous one is still pending
