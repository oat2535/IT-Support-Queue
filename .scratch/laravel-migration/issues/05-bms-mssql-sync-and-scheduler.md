# 05: BMS MSSQL Synchronization Command and 20-Second Laravel Scheduler

**What to build:** Build `BmsJobSource` (`SqlsrvBmsJobSource` querying `BMSDB` + fake adapter for tests) and the `bms:sync-jobs` Artisan command scheduled every 20 seconds (`withoutOverlapping` + `Cache::lock`) implementing Round 1 (`11`/`1` + `dept_control='2'` + SQL `CASE` `difficulty`/`job_category_type`), Round 2 (batch-50 refresh of incomplete local `jobs_bms` + deleting non-`T*` `dept_tech` rows), sequential `IT-XXXX` Queue Item creation, and `outsource_date` status transitions (`6 -> 5 <-> 1`), plus running `schedule:work` in Docker entrypoint.

**Blocked by:** 01

**Status:** resolved

- [x] `bms:sync-jobs` mirrors Round 1 and Round 2 queries, datetime microsecond stripping, and `outsource_date` second zeroing
- [x] Non-`T*` `dept_tech` rows delete matching `QueueItem` and `JobsBms` while empty/null `dept_tech` rows are retained
- [x] Sequential `IT-XXXX` generation skips existing numbers safely and `outsource_date` sync migrates status `6 -> 5` and toggles `WAITING <-> COORDINATING`
- [x] Integration tests using fake `BmsJobSource` verify all sync rules, lock overlap protection, and Laravel Scheduler registration (`everyTwentySeconds`, `everyFiveMinutes`, `dailyAt('08:00')`)
