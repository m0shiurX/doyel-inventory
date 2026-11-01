<?php
// Assemble on macOS, Linux or Windows. Compile the resulting .iss on Windows.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function require_x64_pe(string $path): void {
    $file = fopen($path, 'rb');
    $dos = fread($file, 64);
    if (strlen($dos) !== 64 || substr($dos, 0, 2) !== 'MZ') throw new RuntimeException('Not a Windows PE binary: ' . basename($path));
    $offset = unpack('Voffset', substr($dos, 60, 4))['offset'];
    if ($offset > filesize($path) - 6 || fseek($file, $offset) !== 0) throw new RuntimeException('Invalid PE header');
    $pe = fread($file, 6); fclose($file);
    if (substr($pe, 0, 4) !== "PE\0\0" || unpack('vmachine', substr($pe, 4, 2))['machine'] !== 0x8664) throw new RuntimeException('Windows x64 binary required: ' . basename($path));
}
try {
    if ($argc !== 6) throw new RuntimeException('Usage: php tools/windows/build-bundle.php <NEW-external-output> <extracted-Apache24> <extracted-PHP84-TS> <extracted-MySQL84> <vc_redist.x64.exe>');
    $root = realpath(__DIR__ . '/../..');
    $output = $argv[1];
    $parent = realpath(dirname($output));
    if (!$parent || file_exists($output) || is_link($output)) throw new RuntimeException('Output must be new, with an existing parent');
    $output = $parent . DIRECTORY_SEPARATOR . basename($output);
    $normalize = static fn(string $p): string => strtolower(str_replace('\\', '/', $p));
    if (str_starts_with($normalize($output) . '/', $normalize($root) . '/')) throw new RuntimeException('Keep generated bundles outside the repository');
    $sources = [];
    foreach (['apache' => [2, 'bin/httpd.exe'], 'php' => [3, 'php8apache2_4.dll'], 'mysql' => [4, 'bin/mysqld.exe']] as $name => [$arg, $required]) {
        $source = realpath($argv[$arg]);
        if (!$source || !is_file($source . '/' . $required)) throw new RuntimeException("Invalid $name directory: missing $required");
        if (str_starts_with($normalize($output) . '/', $normalize($source) . '/')) throw new RuntimeException('Output cannot be inside a runtime source');
        $sources[$name] = $source;
    }
    foreach (['php/php.exe', 'php/php8ts.dll', 'php/php.ini-production', 'apache/conf/mime.types', 'mysql/bin/mysql.exe'] as $required) {
        [$name, $path] = explode('/', $required, 2);
        if (!is_file($sources[$name] . '/' . $path)) throw new RuntimeException('Missing ' . $required);
    }
    if (!is_file($argv[5])) throw new RuntimeException('Missing offline VC++ redistributable');
    foreach (['apache/bin/httpd.exe', 'php/php.exe', 'php/php-win.exe', 'php/php8ts.dll', 'php/php8apache2_4.dll', 'mysql/bin/mysqld.exe', 'mysql/bin/mysql.exe'] as $binary) {
        [$name, $path] = explode('/', $binary, 2);
        require_x64_pe($sources[$name] . '/' . $path);
    }
    // Microsoft's x64 redistributable bootstrapper may itself be an x86 PE.
    if (file_get_contents($argv[5], false, null, 0, 2) !== 'MZ') throw new RuntimeException('Invalid VC++ redistributable executable');
    foreach ($sources as $source) {
        // Refuse developer installations containing database state or credentials.
        foreach (['data', 'www', 'config.local.php', 'my.ini'] as $forbidden) {
            if (file_exists($source . '/' . $forbidden)) throw new RuntimeException('Use a clean runtime extraction; found ' . $forbidden);
        }
    }
    umask(0077);
    mkdir($output, 0700);
    $copyTree = function (string $from, string $to) use (&$copyTree): void {
        if (is_link($from)) throw new RuntimeException('Symlinks are not accepted in payloads');
        if (is_dir($from)) {
            if (!is_dir($to) && !mkdir($to, 0700, true)) throw new RuntimeException('Cannot create payload directory');
            foreach (new DirectoryIterator($from) as $entry) {
                if (!$entry->isDot()) $copyTree($entry->getPathname(), $to . '/' . $entry->getFilename());
            }
        } else {
            if (!is_dir(dirname($to))) mkdir(dirname($to), 0700, true);
            if (!copy($from, $to)) throw new RuntimeException('Cannot copy payload file');
        }
    };
    foreach ($sources as $name => $source) $copyTree($source, $output . '/payload/' . $name);
    mkdir($output . '/payload/prerequisites', 0700, true);
    $copyTree($argv[5], $output . '/payload/prerequisites/vc_redist.x64.exe');
    $copyTree(__DIR__ . '/Doyel.iss', $output . '/Doyel.iss');
    $copyTree(__DIR__ . '/Build-Windows.bat', $output . '/Build-Windows.bat');
    $copyTree(__DIR__ . '/manage.php', $output . '/payload/installer/manage.php');
    $copyTree(__DIR__ . '/launch.php', $output . '/payload/installer/launch.php');
    mkdir($output . '/private-build', 0700);
    $process = proc_open([PHP_BINARY, $root . '/tools/prod/build-release.php', $output . '/payload/www', $output . '/private-build/runtime.php'], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Application release build failed');
    rmdir($output . '/private-build');
    // Resolve ProgramData on the client's PC, not the Mac/build machine.
    $stub = <<<'PHP'
<?php
$base = getenv('ProgramData');
if (!$base || !preg_match('~^[A-Za-z]:[\\\\/]~', $base)) throw new RuntimeException('ProgramData unavailable');
return require $base . '/Doyel/config/runtime.php';
PHP;
    file_put_contents($output . '/payload/www/config.local.php', $stub . "\n");
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($output . '/payload', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) if ($entry->isFile()) {
        $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($output) + 1));
        $hashes[$relative] = hash_file('sha256', $entry->getPathname());
    }
    foreach (['Doyel.iss', 'Build-Windows.bat'] as $file) $hashes[$file] = hash_file('sha256', $output . '/' . $file);
    ksort($hashes);
    file_put_contents($output . '/SHA256SUMS.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    echo "Bundle assembled: $output\nCompile Doyel.iss with Inno Setup on Windows. Runtime versions and Windows behavior are not verified by assembly.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
