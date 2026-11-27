# 04: Transactional Queue Operations and BMS Completion Validation

**What to build:** Implement all queue workflow endpoints (`GET /call-next/`, `ANY /finish-queue/`, `ANY /add-queue/`, `POST /update-job-desc/`, `POST /toggle-urgent/`, `POST /insert-queue/`, `GET /finish-adhoc/`, `POST /close-queue/`) wrapped in `DB::transaction()` + `lockForUpdate()`, enforcing `is_staff == 1`, CSRF headers, BMS `job_status` `['2', '12']` validation before completing jobs, and the single-active Ad-hoc Queue constraint, wired to the SB Admin Pro SweetAlert2 modals and AJAX controls.

**Blocked by:** 01, 02, 03

**Status:** resolved

- [x] All legacy queue routes return identical JSON contracts and enforce `is_staff == 1` and CSRF verification
- [x] `call-next`, `finish-adhoc`, and `close-queue` block completion with exact Thai BMS status message when linked `JobsBms` `job_status` is not `'2'` or `'12'`
- [x] `insert-queue` rejects duplicate Ad-hoc Queues when an active Ad-hoc Queue already exists, and `toggle-urgent` / `update-job-desc` update state cleanly
- [x] Feature tests cover every transition, error branch, and concurrent/duplicate-adhoc edge cases
