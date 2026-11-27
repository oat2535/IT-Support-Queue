# Legacy Django Parity Audit & Laravel 13 Target Research

Generated from primary-source inspection of `c:\IT-Support-Queue-main` and `C:\E-Budget - PHP`.

---

## 1. Environment & Git Baseline

- **Backup branch created**: `backup/django` tracking `origin/main` (pure Django snapshot).
- **Host PHP / Composer**:
  - `PHP 8.3.36` (`C:\php\php.exe`), Composer `2.11-dev`.
  - Loaded extensions include `pdo_pgsql`, `pgsql`, `pdo_sqlite`, `sqlite3`, `mbstring`, `intl`, `zip`, `gd`, `curl`, `openssl`.
  - **Missing locally**: `pdo_sqlsrv` / `sqlsrv` DLLs are not present in `C:\php\ext` (must be added for local Windows MSSQL connectivity, and installed via `install-php-extensions pdo_pgsql pdo_sqlsrv sqlsrv` in FrankenPHP Docker).
- **Target reference (`C:\E-Budget - PHP`)**:
  - `composer.json`: `"php": "^8.3"`, `"laravel/framework": "^13.0"`, `"laravel/tinker": "^3.0"`, `"phpunit/phpunit": "^12.5.12"`, `"laravel/pint": "^1.27"`.
  - Docker runtime: `dunglas/frankenphp:1-php8.3` + `Caddyfile` + `docker-entrypoint.sh`.
  - SB Admin Pro static assets in `C:\E-Budget - PHP\public\static\budget_app\`:
    - `css/styles.css`, `css/theme-override.css`, `css/polish.css`, `css/dashboard.css`
    - `js/scripts.js` (SB Admin Pro v2.0.5 + Bootstrap 5.2.3)
    - `assets/fonts/metropolis/*`, `assets/img/backgrounds/*`, `assets/img/illustrations/*`
    - Master layout pattern: `resources/views/layouts/app.blade.php` (Light/Dark/Flat/Auto theme switcher, Feather icons, FontAwesome 6, SweetAlert2, `nav-fixed` + `#layoutSidenav`).

---

## 2. Django Settings & Infrastructure (`config/settings.py`, `Dockerfile`, `docker-compose.yml`)

- **Database**: PostgreSQL `tlph` on `192.168.99.39:5435`, user `eservices`, **`search_path=intra_tl`** (`settings.py:108-120`).
- **Timezone & Naive Timestamps**: `LANGUAGE_CODE = 'th-th'`, `TIME_ZONE = 'Asia/Bangkok'`, `USE_TZ = False` (`settings.py:145-151`). All PostgreSQL timestamp columns are `timestamp without time zone` in local `Asia/Bangkok` time, with microseconds stripped.
- **Iframe / Embedding**: `XFrameOptionsMiddleware` commented out (`settings.py:50`); `CSP_FRAME_ANCESTORS` allows `'self'`, `http://imedx-uat.thonglorpet.com:9444`, `https://imedx-uat.thonglorpet.com:9444` (`settings.py:182-186`); `CSRF_TRUSTED_ORIGINS` lists internal IPs/domains (`settings.py:173-178`).
- **Container**: Python 3.12 + `unixodbc-dev` + `msodbcsql18`, port `5886`.

---

## 3. PostgreSQL Schema Parity (Schema `intra_tl`)

Existing tables and columns MUST be preserved intact:

1. **`intra_tl.queue_app_queuestatus`** (`models.py:40-50`, `populate_statuses.py:8-18`)
   - `id` (`bigint` PK), `name` (`varchar(50)`), `code` (`varchar(20)` UNIQUE), `color` (`varchar(20)`).
   - Active rows: `1: WAITING`, `2: ACTIVE`, `3: DONE`, `5: COORDINATING` (note: `6: WAITING_PARTS` is deleted by sync `utils.py:390` if `id=5` exists).
2. **`intra_tl.queue_app_queueitem`** (`models.py:52-110`, migrations `0001`–`0019`)
   - `id` (`bigint` PK), `queue_number` (`varchar(20)` UNIQUE), `user_name` (`varchar(100)`), `user_department` (`varchar(100)`), `issue_description` (`text`), `comment` (`text` NULL), `is_urgent` (`integer` default `0`), `created_at` (`timestamp without time zone`), `linked_job_no` (`integer` UNIQUE, indexed, NULL), `status_id` (`bigint` FK -> `queue_app_queuestatus.id` `ON DELETE SET NULL`), `call_queue_date` (`timestamp without time zone` NULL), `is_adhoc` (`integer` default `0`).
3. **`intra_tl.jobs_bms`** (`models.py:112-185`, migrations `0009`–`0011`, `0014`, `0017`)
   - `id` (`bigint` PK), `jobno` (`integer` UNIQUE), `catagory` (sic, `varchar(50)` NULL), `description` (`text` NULL), `dept_tech` (`varchar(50)` NULL), `name` (`varchar(100)` NULL), `jobdate`, `assign_date`, `arrive_date`, `req_date`, `caller` (`varchar(100)` NULL), `sap_code` (`varchar(50)` NULL), `aname` (`varchar(255)` NULL), `note` (`text` NULL), `act_dstart`, `act_dfin`, `job_status` (`varchar(10)` NULL), `return_date`, `enterdate`, `enterby` (`varchar(100)` NULL), `outsource_date` (seconds stripped to `00`), `abb_desc` (`varchar(100)` NULL), `descriptions` (`text` NULL), `difficulty` (`integer` NULL), `job_category_type` (`varchar(50)` NULL).
4. **`intra_tl.queue_app_shiftclosure`** (`models.py:4-37`, migrations `0023`–`0024`)
   - `id` (`bigint` PK), `closed_at` (`timestamp without time zone` NULL), `closed_by` (`varchar(100)`), `opened_at` (`timestamp without time zone` NULL — `NULL` means shift is closed), `opened_by` (`varchar(100)` NULL).
5. **`intra_tl.members`** (`models.py:187-197`, unmanaged)
   - `id` (`bigint` PK), `password` (`varchar(128)` — **Django `pbkdf2_sha256` hash format**), `username` (`varchar(150)` UNIQUE), `first_name` (`varchar(150)`), `last_name` (`varchar(150)`), `is_staff` (`integer`).

---

## 4. Routes & API Endpoints (`queue_app/urls.py`, `queue_app/views.py`)

| Route | Method(s) | Behavior & Parity Notes |
|---|---|---|
| `/` | `GET` | Dashboard: KPI counts (`waiting`, `done` current month/year, `pending` = `COORDINATING + WAITING_PARTS`), Active normal (`is_adhoc=0`) & Ad-hoc (`is_adhoc=1`) cards, filtered/sorted list (`waiting` -> `-is_urgent, queue_number`; `active`/`done`/`pending` -> `created_at`), multi-word `ILIKE` search (`issue_description`, `user_name`, `queue_number`), 5/page pagination, global `waiting_rank` (`1..N`), batched `JobsBms` lookup (`operator_name`, `bms_note`), `client_ip`, `client_hostname`, `is_shift_closed`, `is_admin_computer` (`session.is_staff == 1`). |
| `/add-queue/` | `ANY` | Redirects to `/` (`dashboard`). |
| `/call-next/` | `GET` (Frontend AJAX) / `ANY` | Checks all `ACTIVE, is_adhoc=0` items against `JobsBms`: if `job_status not in ['2', '12']`, aborts with Thai error message. Otherwise marks current `ACTIVE, is_adhoc=0` as `DONE`, promotes first `WAITING` (`-is_urgent, queue_number`) to `ACTIVE` with `call_queue_date = now`. |
| `/finish-queue/` | `ANY` | Marks all `ACTIVE, is_adhoc=0` items as `DONE` (no BMS check; unlinked in UI) and redirects to `/`. |
| `/update-job-desc/` | `POST` JSON | `{id, comment, status_id?}` -> updates `comment` (and optional `status_id`). |
| `/toggle-urgent/` | `POST` JSON | `{id, is_urgent}` -> sets `is_urgent = 1|0`. |
| `/insert-queue/` | `POST` JSON | `{id}` -> prevents duplicate active ad-hoc; sets target item `status=ACTIVE, call_queue_date=now, is_adhoc=1`. |
| `/finish-adhoc/` | `GET` (Frontend AJAX) / `ANY` | Checks all `ACTIVE, is_adhoc=1` items against `JobsBms` (`['2', '12']`), then sets them `DONE`. |
| `/close-queue/` | `POST` JSON | `{id}` -> checks `JobsBms` (`['2', '12']`), then sets item `DONE`. |
| `/toggle-shift-status/` | `POST` JSON | `{closed}` -> resolves `hostname` (or `client_ip`); if closing, inserts `ShiftClosure(closed_by=hostname)`; if opening, updates open-ended `ShiftClosure` rows (`opened_at=now, opened_by=hostname`). Returns `{success, is_closed, timestamp}`. |
| `/login-member/` | `POST` JSON | `{username, password}` -> verifies Django `pbkdf2_sha256` hash on `members`, stores session (`member_id`, `is_staff`, `first_name`, `last_name`). |
| `/logout-member/` | `POST` JSON | Flushes session, returns `{success: true}`. |

*(Note: legacy Django views did not enforce `is_staff == 1` server-side on mutation endpoints and used `@csrf_exempt`. Laravel should support CSRF headers and decide on server-side admin middleware.)*

---

## 5. MSSQL Sync (`queue_app/utils.py`) & Scheduler (`queue_app/scheduler.py`)

- **MSSQL Source**: `173.16.200.103`, DB `BMSDB`, user `kanchana_a` (`utils.py:128-156`).
- **Round 1 (`utils.py:23-106`)**: Pulls `jobs` (`job_status IN ('11', '1') AND dept_control = '2'`) joined with `employee` and `m_dept`, computes `difficulty` (`1..5`) and `job_category_type` (`SERVER`/`APP`/`ENDPOINT`/`OTHER`) via SQL `CASE`, upserts `jobs_bms`.
- **Round 2 (`utils.py:194-302`)**: Re-queries MSSQL in batches of 50 for all local `jobs_bms` where `job_status NOT IN ('2', '12')`. If `dept_tech` is non-empty and does NOT start with `'T'`, deletes matching `QueueItem` and `JobsBms`; otherwise upserts `jobs_bms`.
- **Queue Creation (`utils.py:304-372`)**: Inserts new `QueueItem` rows for unlinked `jobs_bms` ordered by `req_date`, generating sequential `IT-0001` numbers from the highest/last `QueueItem`.
- **Outsource Sync (`utils.py:374-424`)**: Migrates status `6` -> `5` (and deletes status `6`), sets items with non-null `outsource_date` (excluding `DONE` and `5`) to status `5` (`COORDINATING`), and restores items in status `5` whose `outsource_date` became `NULL` back to status `1` (`WAITING`).
- **Scheduler (`scheduler.py:10-93`)**:
  - Every `20` seconds: `sync_jobs_from_mssql()`.
  - Every `5` minutes between `21:00`–`06:59` (active `>= 21` or `< 6`): auto-close shift by `System (Auto)` unless an OT `opened_at >= 21:00` exists.
  - Daily at `08:00`: auto-open any open-ended `ShiftClosure` by `System (Auto)`.

---

## 6. Hostname / NetBIOS (`pysmb`) & Frontend Behavior

- **Hostname resolution (`utils.py:428-474`)**: `X-Forwarded-For` -> `REMOTE_ADDR` -> reverse DNS (`gethostbyaddr`) -> NetBIOS UDP port 137 (`pysmb` `queryIPForName`, 2s timeout) -> fallback `VPN/Unknown (<ip>)`, cached 300s.
- **Frontend (`base.html`, `dashboard.html`)**:
  - Top bar: `TLPHLogo.png`, "IT Smart Queue", "Get Info" modal, Login/Logout SweetAlert2 modals, Admin "Offline" button, live Thai Buddhist-era clock (`year + 543`).
  - Shift-closed blocking SweetAlert2 overlay (`moon-zzz.svg`, emergency contact `55999`, admin "Online (Open Shift)" button).
  - Seamless AJAX reload (`seamlessReload`) swapping `#main-content` on link/search/pagination clicks and every 60s (paused while a modal is open).
