# Windows installer candidate

You can prepare this bundle on the Mac. Compile it on a Windows PC or Windows
VM with Inno Setup 6. A remote Windows build machine also works; the client's PC
does not need the compiler. This repository does not currently have a configured
remote Windows builder.

**This is an unverified first-install candidate, not a production release.**
No Windows binaries are checked in, and no `.exe` has been compiled or run here.
It packages the owner restore helper; the older 2026-11-01 prerelease does not
contain that feature. Application-only updates are a separate delivery path.
Automatic daily backups are not implemented. Do not use it
on the client's only copy of their data. Use a clean Windows test VM first.

Regular users should start with the [installation and daily-use guide](../../README.md).
The [1.1.0 Windows prerelease](https://github.com/m0shiurX/doyel-inventory/releases/tag/windows-bundle-2026-11-07)
contains the assembled runtime payload and the offline Inno Setup 6.7.4 compiler
installer in `build-tools`. Extract the full ZIP on Windows, install that compiler
on the build PC, and run `Build-Windows.bat` in the bundle folder.

The runtime selection used for the assembled bundle is recorded in
[runtime-lock.json](runtime-lock.json). This is an audit record of versions,
download URLs, SHA256 values, and verification methods. The bundle builder accepts
local extracted packages; it does not automatically download packages or enforce
this lock file. The generated bundle's SHA256 manifest checks its actual files.

## Prepare on the Mac

Download and extract clean Windows **x64** distributions into a private build
directory outside this repository:

- Apache 2.4: the directory containing `bin/httpd.exe`, `conf`, and `modules`.
- PHP 8.4 **Thread Safe**: contains `php.exe`, `php-win.exe`, `php8ts.dll`, and `php8apache2_4.dll`.
- MySQL 8.4 ZIP: the directory containing `bin/mysqld.exe` and `bin/mysql.exe`.
- Offline Microsoft `vc_redist.x64.exe` matching the packages' prerequisites.

Obtain packages from the sources linked in the
[detailed guide](../../docs/runbooks/windows-offline-installer.md#2-prepare-a-windows-build-and-test-environment).
Verify vendor download hashes/signatures. Keep original licenses/notices in the
distributions; the builder retains the full runtime trees. Do not use an existing
XAMPP/production installation as a runtime source. Do not put client SQL, logs,
credentials, or initialized MySQL data in these input folders.

Example macOS command (replace the runtime paths with your extracted packages):

```sh
php tools/windows/build-bundle.php \
  /private/tmp/DoyelWindowsBundle \
  /private/tmp/doyel-runtimes/Apache24 \
  /private/tmp/doyel-runtimes/php84-ts \
  /private/tmp/doyel-runtimes/mysql84 \
  /private/tmp/doyel-runtimes/vc_redist.x64.exe
```

The output directory must be new, outside the source checkout, with an existing
parent. The builder validates x64 PE headers, copies the allowlisted application,
keeps private configuration outside `www`, and creates a SHA256 manifest. It
does not execute Windows binaries or prove vendor compatibility on the Mac.

Use a durable private directory instead of `/private/tmp` for retained builds.
The temporary paths above are examples; macOS may remove them.

## Compile on Windows

1. Copy the complete generated bundle folder to Windows. Do not transfer just
   `Doyel.iss`: the compiler also needs `payload` and `SHA256SUMS.json`.
2. Install [Inno Setup 6](https://jrsoftware.org/isdl.php) on that developer PC.
3. Open Command Prompt in the bundle folder and run:

   ```bat
   Build-Windows.bat
   ```

   If necessary, pass a custom compiler path:

   ```bat
   Build-Windows.bat "D:\Tools\Inno Setup 6\ISCC.exe"
   ```

4. The script verifies the assembled files and compiles `Doyel.iss`. The output
   is `output\DoyelSetup-1.1.0.exe`. Inno compiles the installer, not PHP source
   into native application code.

The batch script runs inline PowerShell for hashing; it does not change Windows
execution policy or require permission to run a downloaded `.ps1` file.

## Install in a clean Windows VM

1. Prepare a **verified current-schema MySQL 8.4 export** separately from the EXE.
   This can be the existing client's migrated data or an approved clean initial
   database with an admin account. The export must contain the 13 application
   tables and target the selected `software` database without `USE` or
   `CREATE/DROP DATABASE`. This is a trusted owner-supplied SQL file; do not use
   arbitrary downloads. The installer does not create initial business/admin
   records and does not migrate legacy data.
2. Disconnect internet and run the setup EXE with administrator approval.
3. Select the private prepared SQL file on the database page. No SQL is embedded
   in the reusable installer. Preserve the original backup.
4. The installer installs the bundled Visual C++ runtime. If a reboot is needed,
   reboot and run setup again before continuing.
5. It creates private state under `%ProgramData%\Doyel`, initializes secure
   MySQL credentials, imports data, and creates `DoyelDB` and `DoyelWeb` services.
   Apache uses LocalService with access granted to its dedicated service SID;
   MySQL uses its default LocalSystem service account. Database maintenance
   credentials and diagnostics remain administrator/SYSTEM-only.
6. It runs PHP/database smoke checks and a temporary HTTP probe, then removes
   the probe. These are basic runtime/schema checks, not a complete validator of
   the migration sequence or business data. Owner acceptance is still required.
7. Open the Doyel shortcut. Its hidden launcher waits briefly for both services,
   then uses the installed default browser at
   `http://127.0.0.1:18080/login.php`. MySQL binds to `127.0.0.1:13306`.
   Edge/Chrome itself must already be installed and able to run offline.

Setup refuses existing private state and occupied ports/service names. It is
intentionally **first-install only**, including after an uninstall that retains
data. For a failed install, inspect `%ProgramData%\Doyel\setup.log` and private
`maintenance` diagnostics as an administrator. Keep the data. Resolve the failure
with a reviewed recovery procedure; do not delete the directory just to retry.

Uninstall verifies service ownership, stops/unregisters Doyel's services, and
retains private data, logs, and backups. It does not remove a shared VC++ runtime.
After uninstall, reinstall/repair requires a separate owner-run recovery plan.
Use the application's existing backup download and copy a verified backup to USB;
automatic scheduled backup and USB-copy UI are not supplied by this candidate.

## Verify before distribution

Run the acceptance checklist in the
[guide](../../docs/runbooks/windows-offline-installer.md#9-test-the-final-installer-with-internet-disconnected),
especially reboot, standard-user daily use, actual printer, backup restoration,
HTTP deny checks, and failure/uninstall handling. The existing
[cutover procedure](../../docs/runbooks/cutover.md) remains the authority for
migrating the real client's legacy data. Do not open business writes on the new
runtime until that procedure and Windows acceptance pass.

## Application-only updates

The first full installation supplies the runtime once. Subsequent compatible
updates reuse the installed PHP/Apache/MySQL services and private database:

```sh
php tools/windows/build-update.php /absolute/new/DoyelAppUpdate-1.1.0 1.1.0-reviewed-commit
php tools/windows/update-check.php
```

The builder copies the separate public/owner allowlists, adds matching build
metadata, a schema requirement and checksums, and supplies `Update-Doyel.bat`.
No runtime binary, SQL or private connection credential is included. Supply the
whole extracted folder or a ZIP through a trusted release location/USB. Hashes
check corruption; they do not authenticate who published a release. Git push
does not install code on a user's Windows PC.

On Windows, extract everything, close data entry and run the launcher with owner
approval. Choose `apply`; use `recover` from the same bundle after interruption.
The update retains code/configuration/database backup references and keeps writes
closed during its SELECT-only health check. Incompatible schemas require a
separate reviewed migration. This update does not replace the first PHP 5.6
runtime migration, and the full first-install EXE must not be rerun over retained
state. See the [operator guide](../../docs/runbooks/backup-restore.md).

This is a Windows acceptance candidate. Native UAC, PowerShell bootstrap, service
identity, DACLs, directory rename/reboot recovery and standard-user launching must
be tested on Windows before distribution. Local fixture adapters do not prove
those native behaviors.

The full bundle builder optionally accepts a final reviewed build identifier;
otherwise its metadata uses the source Git commit. Both public and owner trees
display the same build. Its generated installer version follows the application
version; the older published prerelease remains unchanged.

## Local checks

```sh
php -l tools/windows/build-bundle.php
php -l tools/windows/manage.php
php -l tools/windows/launch.php
php tools/windows/check.php
```

`check.php` uses synthetic PE headers solely to exercise packaging and rejects
existing output directories. It does not substitute for a Windows build or test.

## Restore helper in current source

The builder creates separate `payload/www` and `payload/maintenance` trees from
explicit allowlists. Provisioning/activation/finish code is never published under
`www`. First installation provisions `%ProgramData%\Doyel\restore`, its writable
inbox, read-only published status/gate, and owner-only job/credential storage.
The private runtime config receives backup/restore locations and generation zero.

After a Windows acceptance test, use **Accounts > Backup & restore** to download
or upload `.doyel.gz` packages. Open **Doyel Database Maintenance** from Start and
approve UAC. Type `prepare` to handle the next queued upload. Review its job in
the browser, request activation, then reopen the helper with `activate` and the
job reference. Passwords are requested in its secure local console. The service
is stopped during switching; MySQL remains running. Reopen the saved job and log
in for read-only acceptance, then request/complete `accept` with administrator
and ordinary-user credentials. `rollback` is available before acceptance;
`recover` explicitly reconciles a saved interrupted operation. Both databases
and verified recovery exports remain retained. See the
[backup/restore guide](../../docs/runbooks/backup-restore.md).

The adapter refuses customized Apache configuration, unknown service executable
or configuration paths, missing elevation, unsafe ACLs, symlinks/reparse paths,
or private credentials accessible outside the intended owner boundary. It audits
code for untrusted write permissions before requiring private PHP configuration.
Native ACL/service/console and interrupted-reboot acceptance is still required;
these are implemented candidates, not Windows-tested guarantees.

An owner can convert an approved, already migrated **existing database** with:

```bat
"C:\Program Files\Doyel\php\php.exe" -c "%ProgramData%\Doyel\config\php.ini" "C:\Program Files\Doyel\maintenance\tools\restore\convert.php" --config="%ProgramData%\Doyel\maintenance\restore-owner.php" --database=approved_database --output="C:\PrivateBackups\converted.doyel.gz"
```

The output must be new and outside web roots; choose an owner-private directory.
Conversion requires the canonical schema and valid relationships, briefly holds
READ locks while exporting a consistent package, and changes no records or
credentials. It never executes a supplied SQL file or reapplies old cleanup.
Legacy SQL follows the reviewed migration/import runbook. A refused conversion
can retain a partial file where the platform does not expose a reliable file
identity; inspect/remove that newly generated partial as the owner and choose a
new output name. Existing output files are never replaced.
