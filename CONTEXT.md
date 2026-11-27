# IT Support Queue

IT Support Queue manages walk-in and BMS-synchronized IT service requests, active technician queues, and service shift availability.

## Language

**Queue Item**:
A service or repair ticket in the IT support queue, optionally linked to a BMS Job via `linked_job_no`.
_Avoid_: Ticket (when referring to the runtime record), Job (use BMS Job for the external record)

**Queue Status**:
The workflow state of a Queue Item: `WAITING`, `ACTIVE`, `COORDINATING`, `WAITING_PARTS`, or `DONE`.
_Avoid_: Job status (reserved for BMS Job)

**Ad-hoc Queue**:
An urgent Queue Item promoted directly to `ACTIVE` alongside the normal queue; at most one Ad-hoc Queue may be `ACTIVE` at a time.
_Avoid_: Express queue, bypass queue

**Waiting Rank**:
The 1-based position of a `WAITING` Queue Item ordered by urgency first and queue number second.
_Avoid_: Queue number (which is the permanent `IT-XXXX` identifier)

**BMS Job**:
An external repair work-order synchronized from the BMS system, whose `job_status` (`2` or `12`) gates closing or advancing an active Queue Item.
_Avoid_: Queue Item

**Shift Closure**:
A record of when IT queue service was closed (`opened_at` is null) and later reopened (`opened_at` is set), either manually by staff or automatically by schedule.
_Avoid_: Global config, offline mode

**Staff Member**:
An authenticated user in `members` whose `is_staff` flag grants administrative queue and shift controls.
_Avoid_: Admin computer (legacy term from IP allowlisting)
