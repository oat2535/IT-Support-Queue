# Spec: Migrate IT Support Queue to PHP 8.3 / Laravel 13 + SB Admin Pro

**Status:** ready-for-agent

## Problem Statement

The IT Support Queue system currently runs on Python/Django (`backup/django` branch) with an older SB Admin 2 interface. We need to modernize the application onto PHP 8.3 and Laravel 13 with the SB Admin Pro theme (matching the sibling `C:\E-Budget - PHP` stack), while preserving 100% of existing functionality, continuing to operate directly on the existing PostgreSQL database (`tlph`, schema `intra_tl`) without losing or altering existing data, and strengthening concurrency safety, performance, and server-side access control.

## Solution

Replace the Django codebase on `main` (with Django preserved on `backup/django`) with a clean PHP 8.3 / Laravel 13 application powered by FrankenPHP (`5886:8000` in Docker), mapping Eloquent models directly to the existing `intra_tl` PostgreSQL schema, replacing `APScheduler` and `pysmb` with Laravel Scheduler commands and a pure-PHP NetBIOS UDP 137 helper, verifying existing Django `pbkdf2_sha256` staff credentials while enforcing server-side `is_staff == 1` authorization and CSRF protection, and rebuilding the single-page dashboard UI using SB Admin Pro static assets from `C:\E-Budget - PHP`.

## User Stories

1. As a visitor or display screen, I want to view the single-page IT Support Queue dashboard (`GET /`) with KPI counters (Waiting, monthly Done, Pending), Active Queue card, Ad-hoc Queue card (when active), and paginated Queue List (5 per page), so that I can monitor queue progress in real time.
2. As a visitor, I want the dashboard to display the `Waiting Rank` (`1..N` ordered by urgency then queue number) when viewing Waiting items and the permanent `IT-XXXX` queue number on other tabs, so that waiting position is immediately clear.
3. As a visitor, I want to filter by status (`waiting`, `done`, `pending`, `active`), search using multiple space-separated keywords across description, requester name, and queue number, and paginate without full browser flicker (`seamlessReload` + 60-second polling paused while modals are open), so that monitoring feels smooth.
4. As a visitor, I want the top bar to show a live Thai Buddhist-era clock, Light/Dark/Flat/Auto theme switcher, a `Get Info` modal displaying my client IP and resolved hostname (reverse DNS -> NetBIOS UDP 137 -> VPN/Unknown), and a read-only Note modal when notes exist, so that I have clear context without clutter.
5. As a Staff Member, I want to log in (`POST /login-member/`) using my existing `members` username and Django password hash and log out (`POST /logout-member/`), so that administrative controls unlock seamlessly without resetting passwords.
6. As a Staff Member, I want to advance the normal queue (`GET /call-next/`), close pending items (`POST /close-queue/`), insert an Ad-hoc Queue (`POST /insert-queue/`), finish an Ad-hoc Queue (`GET /finish-adhoc/`), toggle urgent status (`POST /toggle-urgent/`), and edit comments (`POST /update-job-desc/`), protected by server-side `is_staff == 1` and CSRF checks plus database transactions/locks, so that concurrent staff actions never corrupt queue state.
7. As a Staff Member, I want completion actions (`call-next`, `finish-adhoc`, `close-queue`) to verify that any linked BMS Job has `job_status` in `['2', '12']` and block completion with a Thai warning if not yet closed in BMS, so that BMS and Queue stay synchronized.
8. As a Staff Member, I want to manually close or reopen the service shift (`POST /toggle-shift-status/`) recording my hostname and naive `Asia/Bangkok` timestamp, so that non-staff users see the blocking shift-closed overlay (`moon-zzz.svg` and emergency number `55999`) while staff see an Online reopen button.
9. As an operations system, I want `bms:sync-jobs` to run every 20 seconds without overlapping, pulling Round 1 (`11`/`1` + `dept_control='2'`) and Round 2 (local non-`2`/`12` jobs, deleting non-`T*` `dept_tech` items), computing `difficulty` and `job_category_type`, creating sequential `IT-XXXX` Queue Items, and syncing `outsource_date` transitions (`6 -> 5 <-> 1`), so that the queue reflects BMS automatically.
10. As an operations system, I want `shift:auto-control` to auto-close the shift every 5 minutes between 21:00 and 06:00 (respecting OT `opened_at >= 21:00`) and auto-open at 08:00 by `System (Auto)`, so that shift hours are enforced reliably.

## Implementation Decisions

- **PostgreSQL Schema & Timestamps (ADR-0001):** Connect directly to `tlph` schema `intra_tl` using Eloquent models mapped 1:1 to `queue_app_queuestatus`, `queue_app_queueitem`, `jobs_bms`, `queue_app_shiftclosure`, and `members`. Store naive `Asia/Bangkok` timestamps with microseconds stripped (and `outsource_date` seconds zeroed). Never run destructive migrations against `intra_tl`.
- **Authentication & Authorization (ADR-0002):** Implement a Django `pbkdf2_sha256` verifier (`hash_pbkdf2`) for `members` login. Enforce `is_staff == 1` middleware and Laravel `X-CSRF-TOKEN` validation on all mutation endpoints (`/update-job-desc/`, `/toggle-urgent/`, `/call-next/`, `/finish-queue/`, `/insert-queue/`, `/finish-adhoc/`, `/close-queue/`, `/toggle-shift-status/`). Use `DB::transaction()` + pessimistic locking (`lockForUpdate()`) on queue state transitions.
- **External Integrations (ADR-0003):** Abstract BMS MSSQL queries behind a `BmsJobSource` interface (`SqlsrvBmsJobSource` in production via `pdo_sqlsrv` on Windows and FrankenPHP Docker, fake adapter in tests) and hostname resolution behind `HostnameResolver` (`gethostbyaddr` + pure-PHP UDP port 137 NetBIOS NBSTAT query + 5-minute cache).
- **Scheduler:** Replace `APScheduler` with Laravel Scheduler commands (`bms:sync-jobs` every 20s, `shift:auto-control` every 5m/daily 08:00) guarded by `Cache::lock` and `withoutOverlapping()`.
- **UI & Layout:** Copy SB Admin Pro static assets from `C:\E-Budget - PHP\public\static\budget_app\` into `public/static/queue_app/` alongside `TLPHLogo.png` and `moon-zzz.svg`. Keep the original single-page layout (Topbar + KPI cards + Active/Ad-hoc cards + 7-column Queue table, no Sidebar) styled with SB Admin Pro tokens and Light/Dark/Flat/Auto theme switcher.
- **Runtime & Embedding:** FrankenPHP PHP 8.3 Docker container exposing `5886:8000`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, and `CSP frame-ancestors` allowing `imedx-uat.thonglorpet.com:9444`.

## Testing Decisions

- **Seam 1 — HTTP Routes & Controllers (Laravel Feature Tests):** Exercise all legacy routes through Laravel HTTP requests against an isolated test database schema matching `intra_tl` tables, asserting status codes, JSON contracts, `is_staff == 1` enforcement, CSRF protection, Django PBKDF2 login, queue ordering, BMS `2`/`12` validation, Ad-hoc single-active constraint, multi-word search, pagination, and shift lock behavior.
- **Seam 2 — External Integration Adapters & Artisan Commands:** Inject fake `BmsJobSource` and `HostnameResolver` adapters plus `Carbon::setTestNow` to verify `bms:sync-jobs` (Round 1, Round 2, non-`T*` cleanup, `IT-XXXX` numbering, `outsource_date` transitions, `difficulty`/`job_category_type` mapping) and `shift:auto-control` (21:00–06:00 + OT check, 08:00 auto-open) deterministically without touching live production databases.

## Out of Scope

- Adding new business features not present in the Django version (except SB Admin Pro styling/theme switcher and server-side `is_staff` hardening).
- Adding a sidebar navigation menu or multi-page routing.
- Altering or dropping existing tables/data in `tlph.intra_tl`.

## Further Notes

- Legacy Django code remains preserved on branch `backup/django`.
- Detailed primary-source parity notes live in `.scratch/laravel-migration/research.md`.
