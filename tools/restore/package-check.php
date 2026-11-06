<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../lib/backup_package.php';
$path = $argv[1] ?? '';
if ($path === '' || in_array($path,['-h','--help'],true)) {
    echo "Usage: php tools/restore/package-check.php /private/path/backup.doyel.gz\nVerifies structure, supported schema and byte integrity without executing SQL.\n";
    exit($path === '' ? 2 : 0);
}
try {
    $receipt = doyel_backup_package_read($path);
    echo doyel_backup_data_encode(['verified'=>true,'sha256'=>$receipt['sha256'],'compressed_bytes'=>$receipt['compressed_bytes'],'uncompressed_bytes'=>$receipt['uncompressed_bytes'],'tables'=>$receipt['footer']['tables']]) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR,"Package verification failed.\n"); exit(1);
}
