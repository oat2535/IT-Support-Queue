# 03: Staff Member Auth (Django PBKDF2) and Shift Control (Manual + Scheduler)

**What to build:** Implement `POST /login-member/` verifying existing Django `pbkdf2_sha256` hashes in `members`, `POST /logout-member/`, server-side `is_staff == 1` middleware + CSRF enforcement, `POST /toggle-shift-status/`, the non-dismissible SweetAlert2 shift-closed lock screen (`moon-zzz.svg`, emergency contact `55999`, staff Online button), and the `shift:auto-control` Artisan command scheduled every 5 minutes (21:00–06:00 with OT check) and daily at 08:00.

**Blocked by:** 01, 02

**Status:** resolved

- [x] Existing Django `pbkdf2_sha256` password hashes verify accurately on `POST /login-member/`, storing `member_id`, `is_staff`, `first_name`, `last_name` in session
- [x] `POST /logout-member/` flushes session and admin middleware blocks unauthenticated/non-staff calls to mutation endpoints
- [x] `POST /toggle-shift-status/` opens/closes `ShiftClosure` with resolved hostname and naive timestamp, triggering the shift-closed UI lock screen
- [x] `shift:auto-control` passes deterministic `Carbon::setTestNow` tests for 21:00–06:00 auto-close, OT suppression when `opened_at >= 21:00`, and 08:00 auto-open
