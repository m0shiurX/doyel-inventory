# R8b — application-only update packaging and installation

Implemented the application-only update bundle and its owner-run installer. No
native Windows execution or production database operation is claimed.

`tools/windows/build-update.php` builds a new external folder holding the
allowlisted `www` and `maintenance` trees, matching build metadata, a schema
requirement, a complete SHA256 manifest, `Update-Doyel.bat`, a preflight script
and instructions. It holds no PHP/Apache/MySQL binaries, SQL, private connection
files or test passwords. Hashes detect corruption; they do not authenticate the
publisher, so the bundle must come from the developer's trusted location.

## Update flow

`Update-Doyel.bat` checks the preflight hash and elevates. The preflight rejects
linked, unexpected or changed files and copies the verified bundle into a new
`%ProgramData%\DoyelUpdate-*` folder that only SYSTEM and Administrators can
reach. PHP then runs from that copy and asks for `apply` or `recover`, typed twice.

`doyel_update_run` in `tools/windows/update-core.php`:

1. Refuses an incompatible schema before any journal or code effect.
2. Adds missing feature settings to an older installation without changing the
   database name, user, password or generation.
3. Freezes the application, stops only DoyelWeb, locks the runtime account and
   read-locks all tables.
4. Takes and read-verifies an owner-only `.doyel.gz` backup.
5. Swaps `www` and `maintenance` by rename, retaining the old code in
   `.doyel-previous-*`, and raises the session generation.
6. Starts DoyelWeb with SELECT-only credentials and checks build, version and
   schema through a one-time HTTP probe. The probe is then removed.
7. Restores write access, records the durable commit and reopens the application.

Recovery before commit restores the old code and connection with a higher
generation. Recovery after commit completes forward and preserves later business
work. Unknown code, configuration or account identities stay closed.

## Changes during final review

- `Update-Preflight.ps1` no longer assigns to PowerShell's automatic `$input`
  variable when copying files.
- The elevated window now shows a preflight refusal and waits for Enter. It
  previously closed before the owner could read the message.
- The update console now prints the underlying reason for a refusal. It previously
  printed only generic text, which would have made native failures undiagnosable.
- The fixture suite stubs the HTTP health step, so a new check runs the real
  generated probe through a PHP server with SELECT-only credentials.

## First native Windows result

The first run on a Windows 1.0.0 installation refused before making any change:
`Untrusted code writer`. Folders under `Program Files` inherit full control for
`NT SERVICE\TrustedInstaller`, which the code audit treated as untrusted. That
would have refused every default installation, including a fresh 1.1.0 install.

The audit now accepts that one Windows component account for the code tree only;
private state remains stricter. Its refusals name the path and SID, and the console
reason now includes the native error text.

## Verification

Run on PHP 8.4 and the disposable local MySQL 8.4 fixture:

| Check | Result |
| --- | --- |
| PHP lint of `tools/windows` | pass |
| `tools/windows/update-check.php --mysql` | 54 checks, including seven database/code fixtures and five real process interruptions |
| `tools/windows/check.php` | pass |
| `tools/windows/maintenance-check.php --mysql` | 19 checks |
| `tools/restore/activation-check.php --mysql` | 46 checks |
| `tools/restore/finish-check.php --mysql` | 39 checks |
| `tools/restore/ui-check.php --mysql` | 32 HTTP checks |
| `tools/restore/jobs-check.php` | 51 checks |
| PowerShell parser, PowerShell 7 on Linux | all launcher, preflight, inline and generated scripts parse |
| `tools/restore/package-check.php` on the cleaned test package | verified |

The parser check proves syntax only. Windows PowerShell 5.1 was not run.

## Not verified

Everything native to Windows remains unproven: UAC, the launcher, service
identity and control, DACL behavior, directory rename and reboot recovery,
standard-user launch, Apache serving the probe, the compiled installer and
printing. Run the full EXE and update on a Windows test machine before live use.

## Known limits

- An installation first made by 1.0.0 keeps its 2 MiB PHP upload limit after an
  application-only update, because the update does not rewrite `php.ini`. The
  current cleaned package is about 1.1 MB. A larger package needs the limit raised.
- The same 1.0.0 installation gets no **Doyel Database Maintenance** Start menu
  shortcut from an update. Open it from
  `C:\Program Files\Doyel\maintenance\tools\windows\Database-Maintenance.bat`.
- `Update-Doyel.bat` refuses an update for a different application version or
  schema than the bundle states, and a schema change needs a reviewed migration.
- Retained code, backups and verified release copies are not pruned automatically.
