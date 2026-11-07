# Doyel: build an offline Windows installer with Inno Setup

This guide is for the developer preparing a one-PC installation. The client
should eventually receive one `DoyelSetup.exe`, install it once with administrator
approval, and open a desktop shortcut. No Docker, OrbStack, or XAMPP is required.

**Status, updated 2026-11-01:** an unverified first-install candidate now lives in
[tools/windows](../../tools/windows/README.md), including a cross-platform bundle
builder, Inno script, and database/service setup helper. Follow its README for the
current build commands. No Windows EXE has been compiled or tested here. Upgrade,
repair, and scheduled backups remain unimplemented. This longer guide explains
the manual setup and design; its packaging-only example below is educational,
not the current candidate's full installation script.

## 1. Understand what is compiled

Inno Setup compiles an `.iss` installation script and your payload into a setup
EXE. It does not compile the PHP application into native machine code or hide
its source. PHP, Apache, and MySQL are already compiled Windows binaries that
you redistribute alongside the PHP application files.

Use this runtime design:

```text
Doyel shortcut -> browser -> http://127.0.0.1:18080/login.php
                               |
                        Apache + PHP 8.4
                               |
                        MySQL 8.4, port 13306
```

These ports are examples. Detect conflicts before installation; do not stop an
unrelated service to obtain a port. Both servers must listen only on loopback.
No inbound firewall rule or internet connection is needed for normal use.

## 2. Prepare a Windows build and test environment

Build and test on an x64 Windows 11 machine or VM. You can prepare the PHP source
on macOS, but the Windows services and installer must be tested on Windows.
Use a separate clean Windows VM for acceptance testing.

Download these on your developer machine:

| Item | What to obtain |
| --- | --- |
| Inno Setup | Installer/compiler from <https://jrsoftware.org/isdl.php> |
| PHP | PHP **8.4 x64 Thread Safe (TS)** ZIP from <https://windows.php.net/download/> |
| Apache | A compatible x64 Apache 2.4 Windows distribution; see <https://httpd.apache.org/docs/2.4/platform/windows.html> |
| MySQL | MySQL **8.4 x64 ZIP**, from <https://dev.mysql.com/downloads/mysql/>, selecting the 8.4 series |
| Microsoft runtime | Offline Visual C++ Redistributable required by your selected builds; see <https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist> |

Pin exact versions and record hashes of the downloaded archives. Match the PHP
and Apache architectures and supported compiler/runtime builds. Apache's project
does not itself publish Windows binary releases; use a provider identified by its
Windows documentation and verify that provider's package requirements.

**Use TS for the Apache PHP module in this guide.** The existing PHP Desktop
cutover candidate uses NTS, which is a different integration. Do not copy that
choice into this Apache configuration. PHP's official instructions:
<https://www.php.net/manual/en/install.windows.apache2.php>.

Include the offline runtime prerequisite in the installer, so it does not need
to download dependencies on the client's PC. Include required license notices;
check redistribution terms for all selected packages, particularly MySQL, before
distributing the bundle. Packaging software does not settle licensing rights.

## 3. Assemble a staging directory

Create this directory outside your Git checkout. Do not stage real client data
or credentials in the general installer.

```text
C:\DoyelBuild\
  Doyel.iss
  output\
  payload\
    apache\             complete extracted Apache distribution
    php\                complete extracted PHP distribution
    mysql\              complete extracted MySQL distribution
    www\                generated application release
    prerequisites\
      vc_redist.x64.exe
    licenses\           required third-party notices
```

Retain each distribution's required DLLs, modules, support files, and notices.
Do not prune executables or DLLs just because they appear unused until the full
offline installation has been verified.

The installed layout will be:

```text
C:\Program Files\Doyel\       application and runtime binaries
  apache\
  php\
  mysql\
  www\

C:\ProgramData\Doyel\         persistent private state
  config\
    runtime.php
    my.ini
    httpd.conf
    php.ini
  data\                     MySQL data directory
  logs\
  sessions\
  tmp\
  backups\
```

Separate binary files from persistent state so upgrades do not overwrite the
database. Configure NTFS permissions: service identities need only their required
read/write access; ordinary users must not read database credentials or alter
binaries. Do not grant `Everyone:FullControl` to make a service start.

## 4. Build the application payload

With `payload\www` absent, run the existing builder from the source checkout.
Create the staging parent and private configuration parent first. For this
fixed-path prototype, on the Windows build VM:

```bat
mkdir "C:\DoyelBuild\payload"
mkdir "C:\ProgramData\Doyel\config"
"C:\DoyelBuild\payload\php\php.exe" tools\prod\build-release.php "C:\DoyelBuild\payload\www" "C:\ProgramData\Doyel\config\runtime.php"
```

Extract PHP before running that command. The builder must be run from the
repository root; the source checkout itself must not be in the payload.
The generated `www\config.local.php` points to the private runtime configuration;
it does not include your development credentials. The target `www` must be new
on every build.

Keep the installed private path consistent with this generated stub. If you
support a different ProgramData location, generate the stub during installation
using that actual path rather than reusing this fixed-path example.

Do not copy the entire repository into `www`. The release builder's allowlist
excludes maintenance scripts, migrations, documentation, fixtures, and `.git`.

## 5. Prove Apache and PHP work before making the installer

First configure a disposable manual installation under the installed paths above.
Copy `php.ini-production` to `C:\ProgramData\Doyel\config\php.ini`, then configure:

```ini
extension_dir = "C:/Program Files/Doyel/php/ext"
extension=pdo_mysql
extension=mbstring
date.timezone=Asia/Dhaka
display_errors=Off
log_errors=On
error_log="C:/ProgramData/Doyel/logs/php-error.log"
session.save_path="C:/ProgramData/Doyel/sessions"
sys_temp_dir="C:/ProgramData/Doyel/tmp"
upload_tmp_dir="C:/ProgramData/Doyel/tmp"
memory_limit=128M
```

Verify DOM and Argon2id support as well as the enabled extensions. For the CLI,
use `php.exe -c "C:\ProgramData\Doyel\config\php.ini"` so it uses the same file.
The existing `tools\prod\runtime-probe.php` can check the CLI runtime; verify
the Apache runtime independently with real application requests.

Start from the chosen Apache distribution's `httpd.conf`. Preserve its required
module-loading directives. Replace its existing `Listen`, `ServerRoot`,
`DocumentRoot`, directory access rules, and logging paths as appropriate; do not
just append a second conflicting listener or leave its default document root.
The important application-specific settings are:

```apache
ServerRoot "C:/Program Files/Doyel/apache"
Listen 127.0.0.1:18080
ServerName 127.0.0.1:18080
DocumentRoot "C:/Program Files/Doyel/www"
DirectoryIndex index.php

LoadModule php_module "C:/Program Files/Doyel/php/php8apache2_4.dll"
PHPIniDir "C:/ProgramData/Doyel/config"

<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>

<Directory "C:/Program Files/Doyel/www">
    Options -Indexes
    AllowOverride None
    Require local
</Directory>

<FilesMatch "(?i)^(\.|config\.(local|sample)\.php$)|\.(sql|log|ini|cnf|bak)$">
    Require all denied
</FilesMatch>

ErrorLog "C:/ProgramData/Doyel/logs/apache-error.log"
CustomLog "C:/ProgramData/Doyel/logs/apache-access.log" common
PidFile "C:/ProgramData/Doyel/tmp/httpd.pid"
```

This is a configuration fragment, not a replacement for the distribution's full
configuration. Remove unused sample applications/aliases and verify the effective
configuration does not expose other directories. The private ProgramData tree
must never be a document root or alias.

From an administrator Command Prompt, test and register the dedicated service:

```bat
"C:\Program Files\Doyel\apache\bin\httpd.exe" -t -f "C:\ProgramData\Doyel\config\httpd.conf"
"C:\Program Files\Doyel\apache\bin\httpd.exe" -k install -n "DoyelWeb" -f "C:\ProgramData\Doyel\config\httpd.conf"
sc.exe config DoyelWeb start= auto
sc.exe start DoyelWeb
```

Stop on a nonzero exit code. Verify the service's configured executable and
configuration path. Reference: <https://httpd.apache.org/docs/2.4/en/programs/httpd.html>.

## 6. Prepare the database on the test PC

Use a dedicated `DoyelDB` service, not the client's old XAMPP database service.
Create the private directories and configure the service account's permissions
before starting it.

Initialize a **new, empty** data directory once:

```bat
"C:\Program Files\Doyel\mysql\bin\mysqld.exe" --no-defaults --initialize --console --basedir="C:\Program Files\Doyel\mysql" --datadir="C:\ProgramData\Doyel\data"
```

This creates a temporary root password. Keep it private. Never initialize a data
directory on an upgrade or reinstall. Initialization guidance:
<https://dev.mysql.com/doc/refman/8.4/en/data-directory-initialization.html>.

Create private `C:\ProgramData\Doyel\config\my.ini`:

```ini
[mysqld]
basedir="C:/Program Files/Doyel/mysql"
datadir="C:/ProgramData/Doyel/data"
port=13306
bind-address=127.0.0.1
mysqlx=0
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
log-error="C:/ProgramData/Doyel/logs/mysql-error.log"
```

Disabling MySQL X Protocol avoids an additional unused listener. Register and
start the service from an administrator Command Prompt:

```bat
"C:\Program Files\Doyel\mysql\bin\mysqld.exe" --install DoyelDB --defaults-file="C:\ProgramData\Doyel\config\my.ini"
sc.exe config DoyelDB start= auto
sc.exe start DoyelDB
"C:\Program Files\Doyel\mysql\bin\mysql.exe" --no-defaults --host=127.0.0.1 --port=13306 --user=root --password --connect-expired-password
```

Enter the temporary root password at the prompt. Set a unique root password in
the private session before other SQL. Create the database, import the verified
data, and create a restricted application account with SELECT, INSERT, UPDATE,
and DELETE on the application database. Never distribute a shared root password
or store maintenance credentials in `www`.

The MySQL service option order is deliberate: `--install` precedes
`--defaults-file`. Reference: <https://dev.mysql.com/doc/refman/8.4/en/server-options.html>.

There are two different database preparation paths:

- **This existing client:** restore a verified private backup and follow
  [cutover.md](cutover.md). That guide covers migration order, cleanup review,
  accounts, acceptance, and rollback. Do not automatically run cleanup as part
  of a generic installer, and do not bundle the client's records in a general EXE.
- **A brand-new customer:** prepare a tested, clean current schema plus approved
  initial reference data and first-admin provisioning. The repository's numbered
  migration scripts alter an existing schema; they are not a complete empty
  database installer. Never use the regression fixture as a customer database.

Write private `runtime.php` with the actual generated account/password:

```php
<?php
return [
    'db_host' => '127.0.0.1',
    'db_port' => 13306,
    'db_name' => 'software',
    'db_user' => 'doyel_runtime',
    'db_pass' => 'REPLACE_WITH_UNIQUE_GENERATED_PASSWORD',
    'env' => 'production',
    'log_dir' => 'C:/ProgramData/Doyel/logs',
];
```

Allow Apache's service identity to read this file, but not ordinary users. Open
`http://127.0.0.1:18080/login.php` and prove the application works before packaging.

## 7. Use Inno Setup to compile the package

Install Inno Setup on the developer's Windows machine. Create
`C:\DoyelBuild\Doyel.iss` with the following **packaging-only example**:

```iss
#define MyAppVersion "1.1.0"

[Setup]
AppId=DoyelOffline
AppName=Doyel
AppVersion={#MyAppVersion}
DefaultDirName={autopf}\Doyel
DisableDirPage=yes
DefaultGroupName=Doyel
PrivilegesRequired=admin
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
OutputDir=output
OutputBaseFilename=DoyelSetup-{#MyAppVersion}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern

[Files]
Source: "payload\apache\*"; DestDir: "{app}\apache"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\mysql\*"; DestDir: "{app}\mysql"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\www\*"; DestDir: "{app}\www"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "payload\licenses\*"; DestDir: "{app}\licenses"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{autodesktop}\Doyel"; Filename: "http://127.0.0.1:18080/login.php"
Name: "{group}\Doyel"; Filename: "http://127.0.0.1:18080/login.php"
```

This example makes a setup EXE and browser shortcuts. It **does not** install the
prerequisite, configure services, initialize MySQL, import data, manage updates,
or remove services on uninstall. Use it only on a disposable VM with the manual
runtime already prepared. Do not send this version to the client, and do not
uninstall it while its services are running.

To build in the GUI:

1. Open Inno Setup Compiler.
2. Choose **File > Open** and open `C:\DoyelBuild\Doyel.iss`.
3. Choose **Build > Compile**, or press **F9**.
4. Fix missing-source errors by checking your staging layout.
5. Find `C:\DoyelBuild\output\DoyelSetup-1.1.0.exe`.

F9 can launch the generated installer after compilation. Use the clean test VM
for installation; avoid accidentally installing onto your development machine.

Alternatively, run from Command Prompt, adjusting the compiler path if needed:

```bat
"C:\Program Files (x86)\Inno Setup 6\ISCC.exe" "C:\DoyelBuild\Doyel.iss"
```

Reference: <https://jrsoftware.org/ishelp/topic_compilercmdline.htm>.

## 8. Automate the manual setup before delivering to the client

The current candidate implements first-install setup in
[manage.php](../../tools/windows/manage.php), invoked by its own Inno script.
The requirements below describe the broader production lifecycle, including
features the candidate does not yet implement. A helper could also be written
in PowerShell; the example farther below illustrates that alternative:

1. Preflight x64 Windows support, available disk space, intended paths, both ports,
   service names, and existing Doyel installation state before changing anything.
2. Install the bundled Visual C++ runtime only when needed. Handle success and
   reboot-required outcomes separately from errors; stop if a reboot is required
   before dependent steps can run.
3. Create the private folders and scoped NTFS permissions, and generate the
   Apache, PHP, and MySQL configuration files for the actual installation paths.
4. On a genuine first install, initialize MySQL securely and provision unique
   credentials. Do not expose passwords in process arguments or installer logs.
5. Register the dedicated services. Wait for database readiness with a timeout;
   service registration or process startup alone is not proof of readiness.
6. Import only an explicitly chosen, verified client backup or a reviewed clean
   baseline. Never replace existing data automatically. Retain private migration
   evidence and distinguish first install from upgrade or repair.
7. Write private runtime settings and the public stub; verify the application
   account can connect. Restrict and remove temporary bootstrap credential files.
8. Start Apache, check an actual HTTP response and database connectivity, and
   create shortcuts only after successful configuration.
9. If setup fails, report the failed step, preserve logs/data for diagnosis,
   and clean up only newly created resources that are safe to remove. Track
   incomplete installation state so a repair cannot misinterpret it as success.

Use Inno's Pascal `Exec` with `ewWaitUntilTerminated`, check both its Boolean
return and child process exit code, and fail setup on a real error. Merely adding
a hidden command to `[Run]` is not a complete error-handling strategy.
Reference: <https://jrsoftware.org/ishelp/topic_isxfunc_exec.htm>.

For example, after adding a tested helper to `[Files]`, the integration point is:

```iss
[Code]
procedure CurStepChanged(CurStep: TSetupStep);
var
  ExitCode: Integer;
begin
  if CurStep = ssPostInstall then
  begin
    if not Exec(
      ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe'),
      '-NoProfile -NonInteractive -File "' +
        ExpandConstant('{app}\installer\Configure-Doyel.ps1') + '"',
      ExpandConstant('{app}'), SW_HIDE, ewWaitUntilTerminated, ExitCode) then
      RaiseException('Could not start Doyel configuration.');
    if ExitCode <> 0 then
      RaiseException('Doyel configuration failed. Check the private setup log.');
  end;
end;
```

This illustrates invocation only; that PowerShell helper is not included. It must implement
the preceding requirements, take paths from a defined installation contract,
and exit nonzero on errors. Sign scripts as appropriate for the target execution
policy; do not rely on weakening the machine's execution policy. Installer
exceptions alone do not undo service/database mutations: implement explicit
failure recovery in the helper and lifecycle hooks.

Also implement these lifecycle behaviors before distribution:

- **Launch:** wait briefly for the local application to become ready, then open
  the URL in the normal user's browser. Give a useful local error if services
  fail; routine use must not require administrator rights.
- **Upgrade:** verify a backup, stop services and writers before replacing
  binaries, apply only required reviewed schema changes, and restart/test. Do not
  use the packaging-only file-copy example as the production upgrade mechanism.
- **Uninstall:** stop and unregister only Doyel's services before removing their
  binaries. Retain the persistent data directory and backups by default.
- **Backup:** schedule private daily backups, rotate only verified backups, and
  provide a way to copy a backup to USB. Test restoration, not just file creation.
  Database exports need a consistent snapshot; do not copy live MySQL data files.

## 9. Test the final installer with internet disconnected

Use a fresh Windows VM with no PHP, Apache, MySQL, XAMPP, or developer tools.

1. Disconnect the VM's network and install the setup EXE. All prerequisites must
   come from the installer. Test both a standard Windows user with elevation at
   install time and normal daily use without elevation.
2. Reboot and open the shortcut. Services must start automatically; nothing
   should require a command window or XAMPP control panel.
3. Test both application roles, sales, purchases, payments, stock, and reports.
4. Check Bengali text, keyboard shortcuts, print preview, and the real invoice
   printer, including paper size and pagination.
5. Download a backup and restore it into a separate test database; compare data.
6. Verify service listeners bind only to `127.0.0.1` on the selected ports.
7. Request `/.git/HEAD`, `/config.local.php`, `/config.sample.php`, `/tools/prod/inspect.php`,
   `/db/migrations/001_indexes.up.sql`, and a private log URL. Require 403/404 and
   no source code, credentials, or directory listing.
8. Test occupied ports, insufficient disk space, bad configuration, interrupted
   first install, and failed database startup. Require a useful failure and a
   recoverable state, not a success page.
9. Add test transactions, upgrade, and verify they survive. Uninstall and verify
   services are removed while data/backups remain. Reinstall/repair must safely
   recognize that retained state rather than initialize it again.

Windows runtime, installer, and printing verification have not been performed by
writing this guide. Do not infer those results from macOS/Linux regression tests.

## 10. Deliver the release

After the above passes, sign the setup EXE where practical, calculate its SHA256,
and copy it to USB or send it to the client. Record exact application, PHP,
Apache, MySQL, and installer versions. Runtime security updates still need a
planned offline delivery process even though the application runs offline.

For this existing client, perform the private backup/import and migration with
the owner during the first installation. Keep their business data separate from
the reusable setup EXE. The client's day-to-day instructions can then be:

> Open Doyel from the desktop. Work normally without internet. Use the backup
> action to keep a current copy on USB. Do not delete the Doyel data directory.

Related repository material:

- [Release builder](../../tools/prod/build-release.php)
- [Existing-client migration and rollback](cutover.md)
- [Current Windows acceptance requirements](local-development.md#windows-delivery-acceptance-s45)

Official sources checked for this guide on 2026-10-29 are linked beside the
relevant steps. The examples require testing against the exact selected binaries.
