# Django PBKDF2 Password Verification and Server-Side Staff Auth

Authentication against `members` will verify existing Django `pbkdf2_sha256` password hashes via a custom PHP hasher/verifier, while all queue and shift mutation endpoints will enforce `is_staff == 1` and CSRF verification on the server.

## Considered Options

- **Reset all staff passwords to bcrypt**: Rejected because existing `members` rows must continue working immediately with zero user disruption.
- **Keep legacy unauthenticated/CSRF-exempt API endpoints**: Rejected in favor of security and stability; legacy Django only hid buttons in the UI and marked POST routes `@csrf_exempt`.

## Consequences

- Login works seamlessly against existing `members` records without altering stored password hashes.
- Frontend AJAX calls must include the Laravel `X-CSRF-TOKEN` header, and admin-only endpoints return JSON error responses (`401`/`403`) if called without an active staff session.
