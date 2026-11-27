# 06: Full Parity Verification, Live DB Smoke Checks, and Code Review

**What to build:** Run the full PHPUnit test suite, Laravel Pint style check, read-only connectivity smoke checks against PostgreSQL `tlph` (`intra_tl`) and MSSQL `BMSDB`, live UI & browser verification of the SB Admin Pro dashboard (Light/Dark/Flat/Auto themes, modals, clock, search, pagination, shift lock), Docker build check (`5886:8000`), and two-axis `/code-review` against `backup/django` before preparing `main` for push.

**Blocked by:** 02, 03, 04, 05

**Status:** resolved

- [x] `php artisan test` passes 100% with zero skipped or relaxed assertions
- [x] `vendor/bin/pint --test` passes cleanly with zero style violations
- [x] Read-only smoke check confirms PostgreSQL `intra_tl` data intact and MSSQL `BMSDB` readable via `pdo_sqlsrv`
- [ ] Browser verification confirms zero console/network errors and full SB Admin Pro UI/UX parity (blocked: Chrome remote debugging not active; HTTP 200 & static asset checks passed)
- [x] `/code-review` (Standards + Spec) reports zero unresolved blockers
