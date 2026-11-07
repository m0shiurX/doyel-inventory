# Doyel Inventory

Doyel manages sales, purchases, stock, customers, payments, accounts, and reports.
The Windows package is intended for one PC and can be used without internet
after installation.

## Download status

The [1.1.0 Windows prerelease](https://github.com/m0shiurX/doyel-inventory/releases/tag/windows-bundle-2026-11-07-2)
contains the application, Windows runtimes, installer build tools and a separate
application-only update. **It is a build bundle, not a ready-to-install Doyel EXE.**
It was assembled and checked on macOS; Windows installation, updates, restore and
printing have not been tested yet. The older 2026-11-01 prerelease does not include
the restore UI or application updater.

If you are a regular user, ask your support person for a Windows-tested
`DoyelSetup-1.1.0.exe` and your login details. You do not need to install developer
tools or compile the ZIP yourself. Existing business data must be backed up,
migrated, and verified by your support person before the first installation.

## What you need

- A compatible 64-bit Windows 10/11 PC, with Edge or Chrome already installed.
- Administrator approval for the first installation.
- The tested Doyel installer and a prepared database file from your support person.
- Your printer installed in Windows, if you print invoices.
- A USB drive or another separate backup destination.

The package includes its PHP, web server, database, and Microsoft runtime
prerequisite. You do not need Docker, OrbStack, or XAMPP. Internet is needed to
obtain the files unless they are supplied on USB; the package is designed for
offline installation and daily use. This remains subject to Windows acceptance
testing for the current prerelease.

## First installation

These steps apply after your support person has tested the installer on Windows.
The current installer candidate supports first installation only.

1. Keep a separate verified backup of any existing Doyel data. Do not uninstall
   or overwrite your old installation before your support person completes the
   migration procedure.
2. Run the supplied `DoyelSetup-1.1.0.exe` and approve the Windows administrator
   prompt.
3. When asked for the database export, select the prepared `.sql` file supplied
   by your support person. Keep that file private. A legacy database export
   needs migration before it can be used here.
4. Follow the setup instructions. If the Microsoft runtime requires a restart,
   restart Windows and run Doyel Setup again.
5. When installation finishes, open **Doyel** from the desktop or Start menu.
   It opens in your usual browser. Log in using the credentials supplied to you.
6. With your support person, check the opening stock, balances, reports, and a
   printed invoice before entering real transactions. Keep the old installation
   until the new setup is accepted.

If setup reports an existing installation or retained data, stop and contact
your support person. Do not delete the data folder to retry. The full installer
remains first-install only. Later application updates use a separate bundle;
repair and automatic legacy migration are not supplied.

## Daily use

The 1.1.0 prerelease still needs Windows acceptance before your support person
supplies it for live use.

1. Open **Doyel** from its shortcut. The local services start automatically with
   Windows; the launcher waits briefly for them before opening the browser.
2. Log in and use the navigation menu: **Sales**, **Add to Stock**, **Product
   List**, **Customer List**, **Payment**, **Report**, and **Accounts**.
3. Use **Logout** when finished. Closing the browser does not stop the local
   database. Shut down Windows normally when you finish using the PC.

Internet is not required for these workflows. If the shortcut is unavailable,
open [Doyel on this PC](http://127.0.0.1:18080/login.php) in the browser after
installation. That address only works on the PC where Doyel is installed.

## Print an invoice or report

Open the invoice/report, use its print action or **Ctrl+P**, select your printer,
and check the paper size and print preview. Your support person should verify
Bengali text, totals, paper size, and page breaks on your actual printer during
installation.

## Back up your work

1. Log in with an administrator account.
2. Open **Accounts > Data Backup** and save the downloaded `.sql` file.
3. Copy the file to USB or another separate backup destination. Keep backups
   private and retain more than one recent copy.
4. Ask your support person to periodically test restoring a backup into a
   separate database. A file existing on disk alone does not prove restoration.

Make a backup at the end of each business day and before updates or repairs.
Automatic scheduled backups are not included in this installer candidate.
Restoring a backup should be performed by your support person, not by copying
files into the live database folder.

## Restore a backup or install an update

Administrators can open **Accounts > Backup & restore** to download a restorable
`.doyel.gz` package or prepare a restore into a separate database. The local
**Doyel Database Maintenance** shortcut asks for Windows administrator approval
to process and approve the job. Existing data is retained; writes stay closed
until acceptance. A SQL export uses the owner-assisted migration/import path.

For later application updates, obtain the reviewed application-only ZIP from
your support person, extract the complete folder, close data entry and open
**Update-Doyel.bat**. Approve Windows elevation and type `apply` when prompted.
It backs up the database, retains previous code, checks the new build and keeps
your private database connection. PHP, Apache and MySQL are reused. An interrupted
update uses `recover` from the same bundle. Automatic downloading is not supplied.

The initial move from PHP Desktop 5.6 requires the new runtime and a migrated
database once; the application-only bundle cannot perform that first move.
See the [backup, restore and update guide](docs/runbooks/backup-restore.md).

## If Doyel does not open

- Wait a moment and try the shortcut again, especially just after a restart.
- If the problem continues, contact your support person to check the installation.
- Do not reinstall, remove database files, or use a different database package
  to try to fix it yourself. Keep your backups and existing data.

Application files normally live in `C:\Program Files\Doyel`. Private data lives
under `%ProgramData%\Doyel` (normally `C:\ProgramData\Doyel`). Do not delete that
folder. Uninstalling the application is designed to retain the data, but you
should still make a separate backup first. Reinstalling with retained data needs
your support person's assistance.

## For the support person or developer

- [Build and test the Windows installer](tools/windows/README.md)
- [Detailed offline installation guide](docs/runbooks/windows-offline-installer.md)
- [Existing-client migration, acceptance, and rollback](docs/runbooks/cutover.md)
- [Pinned Windows runtime versions and hashes](tools/windows/runtime-lock.json)
- [Development and regression setup](docs/runbooks/local-development.md)

Downloaded runtimes, generated bundles/installers, credentials, and client
databases stay out of Git. The prerelease ZIP is distributed as a GitHub Release
asset. No client database or login credentials are included in that ZIP.
