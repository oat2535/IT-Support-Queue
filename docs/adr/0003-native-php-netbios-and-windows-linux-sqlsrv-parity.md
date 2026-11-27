# Native PHP NetBIOS Fallback and Dual Windows/Docker SQLSRV Support

Hostname resolution for `Get Info` and `ShiftClosure` audit trails will replace Python's `pysmb` with a pure-PHP UDP port 137 NetBIOS Name Query fallback (after `gethostbyaddr`, cached 5 minutes), and BMS MSSQL synchronization will use `pdo_sqlsrv` in both FrankenPHP Docker and Windows PHP.

## Considered Options

- **Shell out to Python/`pysmb` or `nmblookup`**: Rejected to eliminate Python runtime dependencies in the Laravel container and keep Windows/Linux behavior uniform.
- **Docker-only MSSQL testing**: Less convenient when Windows host already has ODBC Driver 17/18 for SQL Server (64-bit) installed.

## Consequences

- Zero Python dependencies remain in production.
- Windows local PHP requires `php_pdo_sqlsrv.dll` (PHP 8.3 ZTS x64) enabled in `C:\php\php.ini` to run live MSSQL sync outside Docker; automated tests fake the BMS source adapter so the test suite runs anywhere without network access to MSSQL.
