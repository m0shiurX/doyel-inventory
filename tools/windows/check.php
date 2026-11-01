<?php
// Packaging acceptance using synthetic binaries; no Windows execution claimed.
if (PHP_SAPI !== 'cli') exit(1);
define('DOYEL_WINDOWS_CONFIG_TEST', true);
require __DIR__ . '/manage.php';
function expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function run_build(array $args): array {
    $p = proc_open([PHP_BINARY, __DIR__ . '/build-bundle.php', ...$args], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out . $error];
}
function remove_tree(string $path): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (new DirectoryIterator($path) as $entry) if (!$entry->isDot()) remove_tree($entry->getPathname());
        rmdir($path);
    } else unlink($path);
}
$testRoot = sys_get_temp_dir() . '/doyel-windows-check-' . bin2hex(random_bytes(6));
mkdir($testRoot, 0700);
try {
    $pe = 'MZ' . str_repeat("\0", 58) . pack('V', 64) . "PE\0\0" . pack('v', 0x8664);
    foreach (['apache/bin/httpd.exe', 'php/php.exe', 'php/php-win.exe', 'php/php8ts.dll', 'php/php8apache2_4.dll', 'mysql/bin/mysqld.exe', 'mysql/bin/mysql.exe'] as $file) {
        if (!is_dir(dirname($testRoot . '/' . $file))) mkdir(dirname($testRoot . '/' . $file), 0700, true);
        file_put_contents($testRoot . '/' . $file, $pe);
    }
    mkdir($testRoot . '/apache/conf');
    file_put_contents($testRoot . '/apache/conf/mime.types', 'text/html html');
    file_put_contents($testRoot . '/php/php.ini-production', '; test fixture');
    file_put_contents($testRoot . '/vc_redist.x64.exe', $pe);
    $args = [$testRoot . '/bundle', $testRoot . '/apache', $testRoot . '/php', $testRoot . '/mysql', $testRoot . '/vc_redist.x64.exe'];
    [$code, $output] = run_build($args);
    expect($code === 0, 'Assembly failed: ' . $output);
    $manifest = json_decode(file_get_contents($testRoot . '/bundle/SHA256SUMS.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifest as $file => $hash) expect(hash_file('sha256', $testRoot . '/bundle/' . $file) === $hash, 'Manifest mismatch');
    $allowlist = file(__DIR__ . '/../prod/runtime-files.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $actual = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot . '/bundle/payload/www', FilesystemIterator::SKIP_DOTS)) as $entry) {
        $actual[] = str_replace('\\', '/', substr($entry->getPathname(), strlen($testRoot . '/bundle/payload/www/')));
    }
    $expected = [...$allowlist, 'config.local.php']; sort($expected); sort($actual);
    expect($actual === $expected, 'Application payload must match the runtime allowlist exactly');
    $stub = file_get_contents($testRoot . '/bundle/payload/www/config.local.php');
    expect(str_contains($stub, "getenv('ProgramData')") && !str_contains($stub, $testRoot), 'Client config must not retain build-host paths');
    [$code] = run_build($args);
    expect($code !== 0, 'Existing output must never be overwritten');
    $args[0] = $testRoot . '/bad-bundle';
    file_put_contents($testRoot . '/php/php8apache2_4.dll', 'not a PE binary');
    [$code] = run_build($args);
    expect($code !== 0 && !file_exists($args[0]), 'Bad architecture/header must fail before output creation');
    $conf = apache_conf('C:/Program Files/Doyel', 'C:/ProgramData/Doyel');
    expect(substr_count($conf, 'Listen ') === 1 && str_contains($conf, 'Listen 127.0.0.1:18080'), 'Only the loopback web listener is allowed');
    expect(str_contains($conf, 'php8apache2_4.dll') && str_contains($conf, 'Require all denied') && str_contains($conf, 'Options -Indexes'), 'Missing PHP handler or deny rules');
    expect(str_contains(mysql_conf('C:/Program Files/Doyel', 'C:/ProgramData/Doyel'), "bind-address=127.0.0.1\nmysqlx=0"), 'Unused MySQL listeners must not be exposed');
    echo 'PASS: allowlisted packaging, target-side config, hashes, overwrite refusal, PE validation, and listener configuration.' . "\n";
} finally { remove_tree($testRoot); }
