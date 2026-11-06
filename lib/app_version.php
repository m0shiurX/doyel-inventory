<?php
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

/** Build information is supplied by the reviewed packager, never a request or filename. */
function doyel_app_version(): array
{
    $build = 'development';
    $file = __DIR__ . '/build_metadata.php';
    if (is_file($file)) {
        $metadata = require $file;
        if (is_array($metadata) && is_string($metadata['build'] ?? null)
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._+-]{0,95}\z/D', $metadata['build']) === 1) {
            $build = $metadata['build'];
        }
    }
    return ['version' => '1.1.0', 'build' => $build, 'schema_version' => 1, 'backup_format' => 1];
}
