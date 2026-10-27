<?php
// Copy to gitignored config.local.php and return only settings you override.
// Each DOYEL_DB_* environment value takes precedence, including an empty password.
// DOYEL_ENV=development displays E_ALL diagnostics; production logs errors only.
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_user' => 'doyel',
    'db_pass' => '',
    'db_name' => 'software',
    'env' => 'production',
];
