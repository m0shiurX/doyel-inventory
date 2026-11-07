# R8a — owner conversion and Windows maintenance integration

Implemented the owner helper candidate, separate allowlisted maintenance payload,
first-install provisioning, Start menu/UAC launcher and current-schema converter.
No native Windows execution or production database operation is claimed.

`tools/prod/maintenance-files.txt` and `build-maintenance.php` assemble only the
reviewed owner libraries and fixed CLI tools outside `www`. The full Windows
builder includes that tree; the Inno candidate is version 1.1.0. First-install
verification now uses the explicit runtime database rather than hardcoded
`software`, and the PHP configuration permits 64 MiB restore uploads. Provision
runs after service registration/SID configuration and before HTTP bootstrap.

`tools/windows/maintenance-lib.php` provides reusable APIs for the next updater:

- `doyel_windows_context(app, installing=false)` validates installation state,
  exact executable/configuration paths and service identities. It audits private
  DACLs/owners and code write permissions before requiring private PHP files.
- `doyel_windows_service(context, 'stop'|'start')` rechecks ownership and controls
  only DoyelWeb. DoyelDB must remain running and is never controlled here.
- `doyel_windows_config_acl(context)` returns the trusted atomic-config callback:
  a fresh protected DACL grants SYSTEM/Administrators full access and the dedicated
  web service SID read access. No web action selects a command or callable.
- `doyel_windows_runtime(context)` reads the checked authoritative connection.
- `doyel_windows_owner_config(context)` validates the fixed private runtime/job/web
  boundaries and returns owner configuration with its trusted ACL callback.
- `doyel_windows_provision(context)` adds only missing feature settings; existing
  DB name/user/password/generation remain intact. Private owner configuration is
  obtained from the installer's owner-only root.cnf. Existing owner configuration
  must match the expected runtime/web/job roots and owner credential.

The native audit rejects reparse paths, untrusted owners, untrusted private reads
and code writes. Existing restore-owner evidence is recursively audited as secret;
published status/control files permit only service read access. The service
account must be LocalService with its dedicated
service SID enabled. The actual Apache ConfigArgs must identify the fixed private
configuration. Both that configuration and the web config stub must exactly match
shared trusted templates; custom Alias/include/docroot/loader changes require
separate owner review. The Apache registry contract is based on the
[Apache source](https://github.com/apache/httpd/blob/2.4.x/server/mpm/winnt/service.c)
and its [Windows service documentation](https://httpd.apache.org/docs/2.4/platform/windows.html).

Restore storage has a web-modifiable inbox, web-readable published status/gate,
and SYSTEM/Administrator-only owner subtree. Configuration replacement preserves
service read access. Backups are private to the owner and web service. The local
interactive console supports prepare/activate/accept/rollback/recover and uses
R4/R6/R7 directly. Passwords enter a bounded anonymous pipe from Console.ReadKey,
not argv/history/files/logs. The UAC batch launcher uses fixed encoded PowerShell;
it does not change the machine's execution policy. Operation progress is persisted
without credentials under owner-only maintenance/native-operation.json. Failure
retains native stderr under a random owner-only diagnostic filename and prints
that reference locally, after explicitly securing its individual DACL. If private
storage cannot be secured, the temporary diagnostic is removed. Secure password
prompts use a different anonymous-pipe path and never enter those diagnostics.
Failure only restarts the web service when normal/read-only control and runtime generation
agree; ambiguous frozen states remain stopped for owner recovery.

`tools/restore/convert.php` converts an approved existing canonical database to a
new `.doyel.gz` file. It executes no SQL input, performs no cleanup or login, and
preserves records/password hashes. All source tables are READ locked while schema,
relationships and the exporter snapshot are checked; another connection owns the
snapshot. The output is exclusively reserved, streamed, synced and read-verified.
Existing outputs are refused. Failure cleanup closes its handle and deletes only
a positively identified own file; unsupported file identities retain the partial
for explicit owner inspection rather than risking deletion of another file.

## Verification

PHP 8.4 lint passes for new/modified PHP files. `php tools/windows/check.php`
passes allowlisted public and separate owner payload checks, privileged helper
exclusion, target stub equality, hashes, overwrite refusal, PE/listener checks and
upload-limit checks. `php tools/windows/maintenance-check.php --mysql` with local
owner credentials on STDIN passes **17 assertions**, including eleven pure service/
configuration refusals and a disposable MySQL 8.4 converter fixture. All 13 table
digests and inventory match; source hashes/records remain unchanged; existing
outputs are preserved; unknown schemas fail and their identified partial output
is removed. Only a random tiny synthetic database was created and removed.

Still required on Windows 10/11: service registry shape against the actual bundled
binaries; native DACL inheritance/inspection/replacement; secure console/UAC;
stop/restart/reboot interruption; standard-user web permissions; uploaded package
roundtrip/acceptance and printing. Power-loss durability and DB-volume capacity
remain owner acceptance gates. Application-only updater is the following slice;
this helper never initializes/recreates a database or registers/deletes services.
