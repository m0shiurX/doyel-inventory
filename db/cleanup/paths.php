<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Pure classification also supports testing Windows syntax on a Unix host. */
function cleanup_absolute_path(string $path): bool {
    $posix = str_starts_with($path, '/');
    $path = str_replace('\\', '/', $path);
    return $posix || preg_match('~^[A-Za-z]:/~D', $path) === 1 || preg_match('~^//[^/]+/[^/]+(?:/|$)~D', $path) === 1;
}
function cleanup_path_key(string $path, bool $windows): string {
    $path = rtrim(str_replace('\\', '/', $path), '/');
    return $windows ? strtolower($path) : $path;
}
function private_path(string $path): void {
    if (!cleanup_absolute_path($path)) throw new RuntimeException('Report/backup must use an absolute private path');
    $normalized = str_replace('\\', '/', $path);
    $parent = realpath(dirname($normalized)); $repo = realpath(__DIR__ . '/../..');
    $windows = PHP_OS_FAMILY === 'Windows';
    if (!$parent || is_link($path) || str_starts_with(cleanup_path_key($parent, $windows) . '/', cleanup_path_key($repo, $windows) . '/')) throw new RuntimeException('Report/backup must be outside the repository in a private directory');
}
