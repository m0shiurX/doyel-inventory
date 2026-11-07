<?php
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/restore_jobs.php';

/** Owner provisioning only. Native deployment must grant web READ, never WRITE. */
function restore_maintenance_provision(string $root): void
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Owner CLI required');
    $path = restore_jobs_directory($root, 'published') . '/gate.lock';
    if (is_link($path)) throw new RuntimeException('Unsafe maintenance gate');
    if (!file_exists($path)) { $h = fopen($path, 'xb'); if ($h === false) throw new RuntimeException('Cannot provision gate'); fclose($h); }
    if (!is_file($path) || !chmod($path, 0640)) throw new RuntimeException('Cannot provision gate permissions');
}
function restore_maintenance_gate(string $root, bool $exclusive = false)
{
    $path = restore_jobs_directory($root, 'published') . '/gate.lock';
    if (is_link($path) || !is_file($path)) throw new RuntimeException('Maintenance gate not provisioned');
    $h = fopen($path, 'rb');
    if ($h === false || !flock($h, $exclusive ? LOCK_EX : LOCK_SH)) { if (is_resource($h)) fclose($h); throw new RuntimeException('Cannot acquire maintenance gate'); }
    return $h;
}
function restore_maintenance_read_only(): bool { return ($GLOBALS['restore_maintenance_mode'] ?? 'normal') !== 'normal'; }
function restore_maintenance_check(array $config, array $control, string $method, string $script): void
{
    if ($config['restore_generation'] !== $control['generation']) throw new RuntimeException('Runtime generation mismatch');
    if ($control['mode'] === 'frozen') throw new RuntimeException('Application maintenance');
    if ($control['mode'] === 'acceptance' && !in_array($method, ['GET', 'HEAD'], true)
        && !($method === 'POST' && in_array($script, ['login.php', 'Restore.php', 'Report.php'], true))) throw new RuntimeException('Read-only acceptance');
}
/** Called before session and PDO access. Retain SH through request shutdown. */
function restore_maintenance_enter(array $config): void
{
    if ($config['restore_dir'] === '') return;
    try {
        $lease = restore_maintenance_gate($config['restore_dir']);
        $control = restore_jobs_control_read($config['restore_dir']);
        restore_maintenance_check($config, $control, $_SERVER['REQUEST_METHOD'] ?? 'GET', basename($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $GLOBALS['restore_maintenance_mode'] = $control['mode'];
        $GLOBALS['restore_maintenance_lease'] = $lease;
        register_shutdown_function(static function () use ($lease): void { restore_jobs_release($lease); });
    } catch (Throwable $error) {
        http_response_code(503); header('Retry-After: 30'); header('Content-Type: text/plain; charset=UTF-8');
        echo 'Doyel is in maintenance. Please try again later.'; exit;
    }
}
function restore_maintenance_session_generation(array $config): void
{
    if (isset($_SESSION['Username']) && ($_SESSION['restore_generation'] ?? null) !== $config['restore_generation']) {
        $_SESSION = []; session_regenerate_id(true);
    }
}
