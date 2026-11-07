<?php
// Owner/elevated installer tool. Never publish in www. First installations only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once is_file(__DIR__ . '/server-config.php') ? __DIR__ . '/server-config.php' : dirname(__DIR__) . '/maintenance/tools/windows/server-config.php';

function write_file(string $path, string $value): void {
    if (file_put_contents($path, $value) !== strlen($value)) throw new RuntimeException('Cannot write ' . basename($path));
}
function command(array $args, ?string $input = null, ?string $inputFile = null, array $allowed = [0]): string {
    global $state;
    $out = tempnam($state . '/maintenance', 'command-');
    $err = tempnam($state . '/maintenance', 'command-');
    $stdin = $inputFile === null ? ['pipe', 'r'] : ['file', $inputFile, 'r'];
    $process = proc_open($args, [0 => $stdin, 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Cannot launch ' . basename($args[0]));
    if ($inputFile === null) {
        try {
            if ($input !== null) {
                $offset = 0;
                while ($offset < strlen($input)) {
                    $n = fwrite($pipes[0], substr($input, $offset));
                    if ($n === false || $n === 0) throw new RuntimeException('Child input failed');
                    $offset += $n;
                }
            }
        } finally { fclose($pipes[0]); }
    }
    $code = proc_close($process);
    $result = file_get_contents($out);
    if (!in_array($code, $allowed, true)) {
        // Detailed child diagnostics stay administrator-only, outside web logs.
        throw new RuntimeException(basename($args[0]) . " failed ($code); private diagnostic: " . basename($err));
    }
    unlink($out); unlink($err);
    return $result;
}
function service_owned(string $name): bool {
    global $owned;
    return in_array($name, $owned, true);
}
function wait_stopped(string $name): void {
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $status = command(['sc.exe', 'query', $name], null, null, [0, 1060]);
        if ($status === '' || preg_match('/:\s+1\s+|\b1060\b/', $status)) return;
        usleep(500000);
    }
    throw new RuntimeException('Timed out stopping ' . $name);
}
function remove_service(string $name): void {
    if (!service_owned($name)) throw new RuntimeException('Refusing an unowned service');
    command(['sc.exe', 'stop', $name], null, null, [0, 1060, 1062]);
    wait_stopped($name);
    command(['sc.exe', 'delete', $name], null, null, [0, 1060]);
}
function store_state(string $status): void {
    global $state, $app, $owned;
    write_file($state . '/installation.json', json_encode(['format' => 1, 'app' => $app, 'services' => $owned, 'status' => $status], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
function private_client(string $path, string $password): void {
    $escaped = str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', '\\r', '\\n'], $password);
    write_file($path, "[client]\nhost=127.0.0.1\nport=13306\nuser=root\npassword=\"$escaped\"\n");
}
function php_ini(string $app, string $state): string {
    return "extension_dir=\"$app/php/ext\"\nextension=pdo_mysql\nextension=mbstring\n" .
        "date.timezone=Asia/Dhaka\ndisplay_errors=Off\nlog_errors=On\n" .
        "error_log=\"$state/logs/php-error.log\"\nsession.save_path=\"$state/sessions\"\n" .
        "sys_temp_dir=\"$state/tmp\"\nupload_tmp_dir=\"$state/tmp\"\nmemory_limit=128M\nupload_max_filesize=64M\npost_max_size=66M\n";
}
function apache_conf(string $app, string $state): string { return doyel_windows_apache_conf($app,$state); }
function mysql_conf(string $app, string $state): string {
    return "[mysqld]\nbasedir=\"$app/mysql\"\ndatadir=\"$state/data\"\nport=13306\n" .
        "bind-address=127.0.0.1\nmysqlx=0\ncharacter-set-server=utf8mb4\n" .
        "collation-server=utf8mb4_unicode_ci\nlog-error=\"$state/maintenance/mysql-error.log\"\n";
}

// Allows macOS/Linux tests to load the pure configuration functions only.
if (defined('DOYEL_WINDOWS_CONFIG_TEST')) return;

$state = ''; $owned = []; $app = ''; $createdState = false;
try {
    if (PHP_OS_FAMILY !== 'Windows') throw new RuntimeException('Service management must run on Windows');
    if (!in_array($argv[1] ?? '', ['install', 'uninstall', 'verify'], true) || empty($argv[2])) throw new RuntimeException('Usage: php -n manage.php install <app-dir> <prepared.sql> | uninstall <app-dir>');
    $mode = $argv[1];
    $app = str_replace('\\', '/', realpath($argv[2]) ?: '');
    $base = getenv('ProgramData');
    if ($app === '' || !$base || !preg_match('~^[A-Za-z]:[\\\\/]~', $base)) throw new RuntimeException('Invalid installation paths');
    $state = str_replace('\\', '/', $base) . '/Doyel';
    // Configuration formats use literal paths: reject delimiters rather than escape ambiguously.
    if (preg_match('/["\r\n]/', $app . $state)) throw new RuntimeException('Unsupported path characters');

    if ($mode === 'verify') {
        if (PHP_VERSION_ID < 80400 || PHP_VERSION_ID >= 80500 || !PHP_ZTS) throw new RuntimeException('PHP 8.4 Thread Safe is required');
        foreach (['pdo_mysql', 'mbstring', 'dom'] as $ext) if (!extension_loaded($ext)) throw new RuntimeException('Missing ' . $ext);
        if (!defined('PASSWORD_ARGON2ID')) throw new RuntimeException('Argon2id missing');
        foreach (["nul\0password", str_repeat('x', 73)] as $password) if (!password_verify($password, password_hash($password, PASSWORD_ARGON2ID))) throw new RuntimeException('Password runtime check failed');
        $c = require $state . '/config/runtime.php';
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $c['db_name'] ?? '')) throw new RuntimeException('Invalid runtime database');
        $db = new PDO('mysql:host=127.0.0.1;port=13306;dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $expected = ['canteen_ac_head','canteen_collection','canteen_customar','canteen_expenses','canteen_item','canteen_purchase','canteen_purchase_detail','canteen_sales','canteen_sales_detail','canteen_stock','canteen_stock_detail','canteen_supplier','l_user'];
        $tables = $db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_COLUMN); sort($tables);
        if ($tables !== $expected) throw new RuntimeException('Expected exactly 13 Doyel tables');
        $invalid = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (ENGINE<>'InnoDB' OR TABLE_COLLATION<>'utf8mb4_unicode_ci')")->fetchColumn();
        if ((int)$invalid !== 0) throw new RuntimeException('Expected migrated InnoDB/utf8mb4 tables');
        $type = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='canteen_item' AND COLUMN_NAME='Qnty'")->fetchColumn();
        $fk = $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()")->fetchColumn();
        $unique = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='l_user' AND INDEX_NAME='uq_l_user_username' AND NON_UNIQUE=0")->fetchColumn();
        if ($type !== 'decimal(12,3)' || (int)$fk !== 4 || (int)$unique !== 1) throw new RuntimeException('Current migrated schema is required; see cutover.md');
        if ((int)$db->query('SELECT COUNT(*) FROM l_user WHERE userAtho=1')->fetchColumn() < 1) throw new RuntimeException('An approved admin account is required');
        echo "PHP and database smoke checks passed. Full schema/data acceptance remains required.\n";
        exit;
    }

    if ($mode === 'uninstall') {
        $saved = json_decode(file_get_contents($state . '/installation.json'), true, 512, JSON_THROW_ON_ERROR);
        if (strcasecmp($saved['app'], $app) !== 0 || array_diff($saved['services'], ['DoyelWeb', 'DoyelDB'])) throw new RuntimeException('Installation ownership mismatch');
        $owned = $saved['services'];
        foreach (array_reverse($owned) as $service) {
            $image = command(['reg.exe', 'query', 'HKLM\\SYSTEM\\CurrentControlSet\\Services\\' . $service, '/v', 'ImagePath']);
            $exe = $service === 'DoyelWeb' ? '/apache/bin/httpd.exe' : '/mysql/bin/mysqld.exe';
            if (stripos(str_replace('\\', '/', $image), $app . $exe) === false) throw new RuntimeException('Service executable ownership mismatch');
            remove_service($service);
            $owned = array_values(array_diff($owned, [$service]));
            store_state('uninstalling-data-retained');
        }
        store_state('uninstalled-data-retained');
        echo "Services removed; private data and backups retained.\n";
        exit;
    }

    if (file_exists($state)) throw new RuntimeException('Existing private state detected; first-install only. Do not delete it to retry.');
    $sql = realpath($argv[3] ?? '');
    if (!$sql || !is_file($sql) || filesize($sql) === 0) throw new RuntimeException('A prepared current-schema SQL export is required');
    // This is a trusted owner-supplied export, not arbitrary SQL from a web user.
    $fh = fopen($sql, 'rb');
    while (($line = fgets($fh)) !== false) {
        if (preg_match('/^\s*(USE\s|(?:CREATE|DROP)\s+DATABASE\b)/i', $line)) throw new RuntimeException('Export must not select/create/drop databases');
    }
    fclose($fh);
    foreach ([18080, 13306] as $port) {
        $socket = @stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $error);
        if (!$socket) throw new RuntimeException("Port $port unavailable");
        fclose($socket);
    }
    // Check service registry before claiming the private directory.
    foreach (['DoyelDB', 'DoyelWeb'] as $name) {
        $test = proc_open(['reg.exe', 'query', 'HKLM\\SYSTEM\\CurrentControlSet\\Services\\' . $name], [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $p, null, null, ['bypass_shell' => true]);
        if (!is_resource($test) || proc_close($test) === 0) throw new RuntimeException('Service name already in use: ' . $name);
    }
    if (!mkdir($state, 0700)) throw new RuntimeException('Cannot create private state');
    // Restrict before writing any secrets; use language-independent Windows SIDs.
    $acl = proc_open(['icacls.exe', str_replace('/', '\\', $state), '/inheritance:r', '/grant:r', '*S-1-5-18:(OI)(CI)F', '*S-1-5-32-544:(OI)(CI)F'], [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $p, null, null, ['bypass_shell' => true]);
    if (!is_resource($acl) || proc_close($acl) !== 0) throw new RuntimeException('Cannot restrict private state permissions');
    $createdState = true;
    foreach (['config', 'logs', 'maintenance', 'sessions', 'tmp', 'backups'] as $dir) mkdir($state . '/' . $dir, 0700);
    store_state('installing');
    write_file($state . '/setup.log', "First installation started. No client records or passwords are logged here.\n");
    write_file($state . '/config/php.ini', php_ini($app, $state));
    write_file($state . '/config/httpd.conf', apache_conf($app, $state));
    write_file($state . '/config/my.ini', mysql_conf($app, $state));
    $mysqld = $app . '/mysql/bin/mysqld.exe'; $mysql = $app . '/mysql/bin/mysql.exe';
    $httpd = $app . '/apache/bin/httpd.exe';
    if (!str_contains(command([$mysqld, '--no-defaults', '--version']), '8.4.')) throw new RuntimeException('MySQL 8.4 is required');
    command([$mysqld, '--no-defaults', '--initialize', '--basedir=' . $app . '/mysql', '--datadir=' . $state . '/data', '--log-error=' . $state . '/maintenance/initialize.log']);
    $init = file_get_contents($state . '/maintenance/initialize.log');
    if (!preg_match('/temporary password is generated for root@localhost: (\S+)/', $init, $match)) throw new RuntimeException('Cannot find secure initialization password');
    $client = $state . '/maintenance/root.cnf';
    private_client($client, $match[1]); unset($init, $match);
    command([$mysqld, '--install', 'DoyelDB', '--defaults-file=' . $state . '/config/my.ini']);
    $owned[] = 'DoyelDB'; store_state('installing');
    command(['sc.exe', 'config', 'DoyelDB', 'start=', 'auto']);
    command(['sc.exe', 'start', 'DoyelDB']);
    $ready = false;
    for ($i = 0; $i < 60; $i++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:13306', $errno, $error, 1);
        if ($socket) { fclose($socket); $ready = true; break; }
        usleep(500000);
    }
    if (!$ready) throw new RuntimeException('Database readiness timed out');
    $rootPassword = bin2hex(random_bytes(32)); $runtimePassword = bin2hex(random_bytes(32));
    $mysqlArgs = [$mysql, '--defaults-file=' . $client, '--protocol=TCP', '--connect-timeout=10', '--batch', '--binary-mode'];
    command([...$mysqlArgs, '--connect-expired-password'], "ALTER USER 'root'@'localhost' IDENTIFIED BY '$rootPassword';\n");
    private_client($client, $rootPassword); unset($rootPassword);
    unlink($state . '/maintenance/initialize.log');
    command($mysqlArgs, "CREATE DATABASE software CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
    command([...$mysqlArgs, '--default-character-set=utf8mb4', 'software'], null, $sql);
    command($mysqlArgs, "CREATE USER 'doyel_runtime'@'127.0.0.1' IDENTIFIED BY '$runtimePassword';\nGRANT SELECT,INSERT,UPDATE,DELETE ON software.* TO 'doyel_runtime'@'127.0.0.1';\n");
    $config = ['db_host' => '127.0.0.1', 'db_port' => 13306, 'db_name' => 'software', 'db_user' => 'doyel_runtime', 'db_pass' => $runtimePassword, 'env' => 'production', 'log_dir' => $state . '/logs'];
    write_file($state . '/config/runtime.php', "<?php\nreturn " . var_export($config, true) . ";\n"); unset($runtimePassword, $config);
    command([$app . '/php/php.exe', '-c', $state . '/config/php.ini', __FILE__, 'verify', $app]);
    command([$httpd, '-t', '-f', $state . '/config/httpd.conf']);
    command([$httpd, '-k', 'install', '-n', 'DoyelWeb', '-f', $state . '/config/httpd.conf']);
    $owned[] = 'DoyelWeb'; store_state('installing');
    command(['sc.exe', 'sidtype', 'DoyelWeb', 'unrestricted']);
    command(['sc.exe', 'config', 'DoyelWeb', 'obj=', 'NT AUTHORITY\\LocalService', 'password=', '', 'start=', 'auto']);
    // Grant only the web service SID, not all local users or all LocalService processes.
    foreach ([$state, $state . '/config'] as $dir) command(['icacls.exe', $dir, '/grant', 'NT SERVICE\\DoyelWeb:(RX)']);
    foreach (['runtime.php', 'httpd.conf', 'php.ini'] as $file) command(['icacls.exe', $state . '/config/' . $file, '/grant', 'NT SERVICE\\DoyelWeb:(R)']);
    foreach (['logs', 'sessions', 'tmp'] as $dir) command(['icacls.exe', $state . '/' . $dir, '/grant', 'NT SERVICE\\DoyelWeb:(OI)(CI)M']);
    command([$app . '/php/php.exe', '-c', $state . '/config/php.ini', $app . '/maintenance/tools/windows/maintenance.php', '--app=' . $app, '--provision']);
    $token = bin2hex(random_bytes(16));
    $probe = $app . '/www/installer-health-' . $token . '.php';
    write_file($probe, '<?php require __DIR__ . "/lib/bootstrap.php"; header("Content-Type: application/json"); echo json_encode(["token" => ' . var_export($token, true) . ', "db" => db_value("SELECT DATABASE()"), "php" => PHP_VERSION_ID]);');
    try {
        command(['sc.exe', 'start', 'DoyelWeb']);
        $healthy = false;
        $context = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
        for ($i = 0; $i < 30; $i++) {
            $body = @file_get_contents('http://127.0.0.1:18080/' . basename($probe), false, $context);
            $health = $body === false ? null : json_decode($body, true);
            if (is_array($health) && ($health['token'] ?? '') === $token && ($health['db'] ?? '') === 'software' && ($health['php'] ?? 0) >= 80400 && $health['php'] < 80500) { $healthy = true; break; }
            usleep(500000);
        }
        if (!$healthy) throw new RuntimeException('Apache/PHP/database HTTP check failed');
    } finally { if (is_file($probe)) unlink($probe); }
    store_state('installed');
    file_put_contents($state . '/setup.log', "Installation and HTTP smoke check passed. Windows acceptance and restore tests still required.\n", FILE_APPEND);
    echo "Installed. Open http://127.0.0.1:18080/login.php\n";
} catch (Throwable $e) {
    if ($createdState) {
        file_put_contents($state . '/setup.log', $e->getMessage() . "\n", FILE_APPEND);
        // Never remove database files on failure. Stop only services registered here.
        foreach (array_reverse($owned) as $service) {
            try {
                remove_service($service);
                $owned = array_values(array_diff($owned, [$service]));
            }
            catch (Throwable $cleanup) { file_put_contents($state . '/setup.log', 'Service cleanup needs attention: ' . $service . "\n", FILE_APPEND); }
        }
        store_state('failed-data-retained');
    }
    fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
}
