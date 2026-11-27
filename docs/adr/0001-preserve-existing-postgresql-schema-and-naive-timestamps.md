# Preserve Existing PostgreSQL Schema and Naive Timestamps

Laravel 13 will connect directly to the existing PostgreSQL database (`tlph`, schema `intra_tl`) and map Eloquent models to the legacy Django tables (`queue_app_queuestatus`, `queue_app_queueitem`, `jobs_bms`, `queue_app_shiftclosure`, `members`) with `Asia/Bangkok` naive timestamps (`timestamp without time zone`, microseconds stripped).

## Considered Options

- **Greenfield Laravel schema + ETL migration**: Rejected because the user requires direct coexistence with the existing database structure and zero disruption to existing production data.
- **Direct mapping to `intra_tl` without destructive migrations**: Chosen; Laravel migrations will never drop or alter existing `intra_tl` tables, while PHPUnit tests run against an isolated test schema/SQLite database.

## Consequences

- Eloquent models must explicitly declare legacy `$table` names, disable/customize timestamps (`created_at` without `updated_at`), and mirror Django's exact column names (including `catagory` in `jobs_bms`).
- `php artisan migrate:fresh` must never be run against the `intra_tl` connection.
