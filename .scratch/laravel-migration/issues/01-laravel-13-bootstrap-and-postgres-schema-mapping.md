# 01: Bootstrap Laravel 13, PostgreSQL Schema Mapping, and Windows/Docker SQLSRV

**What to build:** Replace the Django root workspace on `main` (safely backed up on `backup/django`) with a Laravel 13 / PHP 8.3 application configured for PostgreSQL (`intra_tl`), Eloquent models mapped 1:1 to all five legacy tables (`queue_app_queuestatus`, `queue_app_queueitem`, `jobs_bms`, `queue_app_shiftclosure`, `members`) with naive `Asia/Bangkok` timestamp formatting, an isolated test migration/schema setup for PHPUnit, `php_pdo_sqlsrv` enabled on Windows PHP, and FrankenPHP Docker (`5886:8000`) configured with `pdo_pgsql` + `pdo_sqlsrv` + `sqlsrv`.

**Blocked by:** None (can start immediately).

**Status:** resolved

- [x] Laravel 13 skeleton installed on `main` with `APP_TIMEZONE=Asia/Bangkok`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, and `pgsql` (`search_path=intra_tl`) + `sqlsrv` connections configured
- [x] All five Eloquent models map exact legacy table/column names, relationships, naive timestamp truncation (microseconds stripped, `outsource_date` seconds zeroed), and `JobsBms::getJobStatusDisplay()`
- [x] PHPUnit passes smoke tests verifying model persistence against isolated test schema without modifying production `intra_tl`
- [x] `php -m` on Windows includes `pdo_pgsql` and `php_pdo_sqlsrv`, and `Dockerfile` + `docker-compose.yml` (`5886:8000`) build cleanly
