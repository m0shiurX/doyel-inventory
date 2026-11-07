# Backups, restore and application updates

Open **Accounts > Backup** as staff or **Accounts > Backup & restore** as an
administrator. A restore prepares a separate database, verifies it against
the backup, and retains the previous database. Uploading a file does not switch
the application or reopen business writes.

Staff and administrators use the single backup link in Accounts. Staff can download
daily `.doyel.gz` backups. SQL exports, restore uploads, job status and all restore
actions require an administrator.

## Choose the right backup

- **Download restore backup** produces a `.doyel.gz` data package for the restore
  page. It includes all current records, accounts, password hashes and retained
  history. Backup and restore do not apply the earlier two-year cleanup again.
- **Download SQL backup** remains available for owner-assisted SQL import. A SQL
  file cannot be uploaded to the restore page. Legacy SQL needs the reviewed
  [migration procedure](cutover.md) before conversion to a restore package.

Keep several dated backups and copy them to another device. A backup contains
private business data and password hashes. Test restoration periodically; a
successful download alone does not establish that recovery works.

The compressed package can be much smaller than SQL without changing its data.
The earlier approved cleanup was a separate operation that removed old stock
snapshots and the specifically approved orphan/empty records. Converting that
cleaned SQL into a restore package does not restore deleted history or delete
additional records. Review table counts, financial totals, historical coverage
and customer balance checks in the restore screen.

## Restore into a new database

1. Stop data entry and make a fresh backup of the current installation. Keep the
   original file being restored as well. An older backup does not contain work
   entered after its source snapshot; restore does not merge that work.
2. Upload the `.doyel.gz` file on **Backup & restore**. Save the job reference or
   bookmark its page. Closing the browser does not cancel the saved job.
3. The owner runs the separately installed maintenance helper. It validates the
   entire package, creates a randomly named database, imports data using a
   temporary account restricted to that database, and verifies every table and
   the financial/balance inventory. The normal application account cannot create
   or replace databases.
4. Review the resulting evidence, backup date, historical dates, old/new database
   names and potential work gap. Confirm the activation request with your current
   administrator password. The owner independently supplies valid administrator
   credentials for both databases to the local helper.
5. The helper closes writes, drains requests, locks the old runtime account,
   saves and verifies a final backup, then switches the private connection. The
   old database is retained. Sign in again; existing sessions expire. The
   candidate remains read-only during acceptance.
6. Check administrator and ordinary-user access, stock, sales/purchases,
   collections, expenses, balances, reports, Bengali text and printing. Compare
   against the accepted source snapshot. Do not enter real transactions yet.
7. Request acceptance in the page, then complete owner approval in the local
   helper. The helper verifies administrator and ordinary-user credentials and
   records the acceptance boundary before granting normal writes.

An unknown administrator password is an owner-assisted account recovery task.
Restore does not add a default login or reset existing passwords. A separately
supplied test database may contain explicitly added test accounts; the pristine
migrated SQL preserves its original cleaned accounts.

On the Windows installer candidate, open **Doyel Database Maintenance** from the
Start menu and approve the Windows administrator prompt. Choose `prepare` to
process an upload, then use the job reference from the page for `activate`,
`accept`, `rollback` or `recover`. The console asks for the appropriate application
logins and a typed operation confirmation. MySQL provisioning credentials remain
in protected installer state; the operator does not type them. Ordinary daily use
does not need this elevated helper. Test this candidate on Windows before using
it with live data.

## Rollback and interruption

Before acceptance opens writes, request rollback and use the local owner helper.
It preserves candidate evidence, checks the retained source, switches back with a
new session generation, and retains both databases. Do not delete either database
or private recovery files to make a retry pass.

After the acceptance boundary is committed, blind rollback is refused. If new
work must be recovered, close data entry, export the current database and
reconcile intervening work under a separate recovery procedure.

A service interruption can temporarily make the browser unavailable. Reopen the
saved job after service returns. An ambiguous configuration, account, backup or
generation keeps the application closed. The owner must inspect the private
job/recovery records and use the recovery operation; refreshing the page does
not authorize a database switch. Never edit job JSON, manually decrement a
generation, unlock accounts or replace connection files to bypass that gate.

## Application files versus the runtime

The initial move from PHP 5.6 to PHP 8.4 requires a compatible runtime and a
migrated database. Once that new installation is accepted, routine updates can
contain only application PHP, JavaScript, CSS, images and maintenance code.
They do not need to reinstall PHP, Apache or MySQL, initialize another database,
or replace the private connection credentials.

Use a reviewed application update bundle supplied by the owner. Make a backup,
close data entry, apply it using the local elevated updater, and check the new
build in **About Doyel** before resuming. Retain the previous code and verified
backup. An update with an incompatible schema needs a separately rehearsed
migration; copying application files is not a database migration.

Distribution is manual: the developer builds and checks a release, supplies that
bundle through a trusted release location or USB, and the Windows owner installs
it. The website does not download or execute updates. Native Windows service,
ACL, interruption, standard-user and printing acceptance remains required before
production use of a new maintenance/update build.

Build an application-only bundle from the reviewed checkout, using a new external
directory and a reviewed build identifier:

```sh
php tools/windows/build-update.php /absolute/new/DoyelAppUpdate-1.1.0 1.1.0-reviewed-commit
```

The output contains `www`, a separate `maintenance` code tree, build/schema
metadata, a complete SHA256 manifest, the Windows launcher and instructions.
It contains no runtime binaries, SQL, private connection files or test passwords.
Compress that entire directory and publish it through the developer's trusted
release location or supply it on USB. A Git commit or push alone does not update
an installed Windows application. The earlier Windows prerelease bundle is not
this new application update.

On the Windows test machine, extract the complete update and double-click
`Update-Doyel.bat`. The launcher verifies the bootstrap and files before elevated
PHP execution, copies verified code into protected owner staging, then asks for
`apply` and a typed confirmation. It refuses an incompatible database schema or
an installation whose service/configuration/permission identity cannot be proved.
It stops only DoyelWeb, retains a verified owner-only database backup and previous
code, swaps application code, increases the session generation and tests the new
HTTP/PHP/schema path using SELECT-only credentials before opening normal writes.

After success, check **About Doyel** for the supplied build, sign in again and
verify representative pages and printing. Keep the old code and private backup.
If interrupted, run the same update bundle and choose `recover`. Before its
durable commit, recovery restores the previous code and connection with a higher
generation. After commitment, recovery completes forward and preserves later
business work. Ambiguous code/configuration/account identities stay closed for
owner inspection. Recovery never initializes or replaces the MySQL data directory.

The owner evidence is under `%ProgramData%\Doyel\maintenance\updates`, with the
current update reference in `update-active.json`. Retained code uses uniquely
named `.doyel-previous-*` siblings beneath the application directory. Failed/new
code and verified protected release copies are retained for review; this version
does not automatically prune them. These paths are owner maintenance details,
not folders to copy into the live database.
