<?php
// Copy to gitignored config.local.php and return only settings you override.
// Each DOYEL_DB_* environment value takes precedence, including an empty password.
// DOYEL_ENV=development displays E_ALL diagnostics; production logs errors only.
// DOYEL_LOG_DIR overrides log_dir. It must be an absolute writable directory
// outside the application web root, including after resolving symlinks/junctions.
// The default is private OS temporary storage; use durable storage in production.
// Windows deployment example: 'log_dir' => 'C:\\Doyel-private\\logs',
// Restore-package downloads require backup_dir outside every web root, with
// owner/service ACLs provisioned on Windows. DOYEL_BACKUP_DIR overrides it.
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_user' => 'doyel',
    'db_pass' => '',
    'db_name' => 'software',
    'env' => 'production',
    'backup_dir' => '', // e.g. 'C:\\ProgramData\\Doyel\\backups'
];
