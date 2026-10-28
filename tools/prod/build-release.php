<?php
// Build only a reviewed runtime allowlist. No credentials or client records copied.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    if ($argc !== 3) throw new RuntimeException('Usage: php tools/prod/build-release.php <NEW-external-www-directory> <absolute-private-runtime.php>');
    $root = realpath(__DIR__ . '/../..');
    $target = $argv[1]; $private = $argv[2];
    $absolute = static fn(string $p): bool => str_starts_with($p, '/') || (bool)preg_match('~^[A-Za-z]:[\\\\/]~', $p) || str_starts_with($p, '\\\\');
    $within = static fn(string $p, string $base): bool => $p === $base || str_starts_with($p, $base . '/');
    $canonical = static fn(string $p): string => rtrim(str_replace('\\', '/', $p), '/');
    if (!$absolute($target) || !$absolute($private) || preg_match('~(^|[\\\\/])\.\.?([\\\\/]|$)~', $target . '/' . $private)) throw new RuntimeException('Use absolute paths without dot segments');
    $parent = realpath(dirname($target));
    if (!$parent || is_link($target) || file_exists($target)) throw new RuntimeException('Target must be new with an existing parent');
    $dest = $canonical($parent . '/' . basename($target));
    $repo = $canonical($root);
    if (PHP_OS_FAMILY === 'Windows') { $dest = strtolower($dest); $repo = strtolower($repo); }
    if ($within($dest, $repo) || $within($repo, $dest)) throw new RuntimeException('Release must be outside the source checkout');
    $privateParent = realpath(dirname($private));
    if (!$privateParent || is_link($private)) throw new RuntimeException('Private config needs an existing private parent and no symlink');
    $resolvedPrivate = $canonical($privateParent . '/' . basename($private));
    if (PHP_OS_FAMILY === 'Windows') $resolvedPrivate = strtolower($resolvedPrivate);
    if ($within($resolvedPrivate, $dest) || $within($resolvedPrivate, $repo)) throw new RuntimeException('Private config must be outside source and release');
    $files = file(__DIR__ . '/runtime-files.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // Validate the entire input before creating any output.
    foreach ($files as $file) {
        if (!preg_match('~^[A-Za-z0-9_ /.-]+\.(php|css|js|gif|png|jpg)$~D', $file) || str_contains($file, '..')) throw new RuntimeException('Invalid manifest path');
        $source = $root . '/' . $file;
        if (!is_file($source) || is_link($source) || realpath($source) === false || (PHP_OS_FAMILY === 'Windows' ? strtolower($canonical(realpath($source))) !== strtolower($canonical($source)) : $canonical(realpath($source)) !== $canonical($source))) throw new RuntimeException('Manifest source missing or symlinked: ' . $file);
    }
    umask(0077);
    if (!mkdir($target, 0700)) throw new RuntimeException('Cannot create target');
    foreach ($files as $file) {
        $to = $target . '/' . $file;
        if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0700, true)) throw new RuntimeException('Cannot create runtime directory');
        if (!copy($root . '/' . $file, $to)) throw new RuntimeException('Runtime copy failed');
    }
    $stub = "<?php\n// Credentials live outside the document root. Never serve that private directory.\nreturn require " . var_export($private, true) . ";\n";
    if (file_put_contents($target . '/config.local.php', $stub) !== strlen($stub)) throw new RuntimeException('Cannot write private-config stub');
    echo 'PASS: ' . count($files) . " allowlisted runtime files plus private-config stub; no source config copied\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
