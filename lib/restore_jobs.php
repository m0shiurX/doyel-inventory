<?php
// Storage only: no database credentials, SQL execution, service control or activation.
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404); exit;
}

const RESTORE_JOB_MAX_UPLOAD = 67108864;
const RESTORE_JOB_JSON_LIMIT = 16384;

function restore_jobs_path(string $root): string
{
    if ($root === '' || str_contains($root, "\0") || !preg_match('~^(?:/|[A-Za-z]:[\\\\/]|[\\\\]{2})~', $root)) {
        throw new RuntimeException('Restore storage must be an absolute private directory');
    }
    $normalized = str_replace('\\', '/', $root);
    foreach (explode('/', $normalized) as $part) {
        if ($part === '.' || $part === '..') throw new RuntimeException('Restore storage traversal refused');
    }
    // Reject even symlink parents rather than silently accepting their destinations.
    $probe = rtrim($root, '/\\');
    while ($probe !== dirname($probe)) {
        if (is_link($probe)) throw new RuntimeException('Restore storage symlink refused');
        $probe = dirname($probe);
    }
    $resolved = realpath($root);
    if ($resolved === false || !is_dir($resolved)) throw new RuntimeException('Restore storage is not provisioned');
    $key = static fn(string $p): string => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $p)) : str_replace('\\', '/', $p);
    foreach ([dirname(__DIR__), $_SERVER['DOCUMENT_ROOT'] ?? null] as $public) {
        if (!$public) continue;
        $public = realpath($public);
        if ($public !== false && ($key($resolved) === $key($public) || str_starts_with($key($resolved) . '/', rtrim($key($public), '/') . '/'))) {
            throw new RuntimeException('Restore storage must be outside the public application');
        }
    }
    return rtrim($resolved, '/\\');
}

function restore_jobs_path_key(string $path, ?bool $windows = null): string
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    return ($windows ?? PHP_OS_FAMILY === 'Windows') ? strtolower($path) : $path;
}

function restore_jobs_directory(string $root, string $area): string
{
    if (!in_array($area, ['inbox', 'published', 'owner'], true)) throw new RuntimeException('Invalid restore area');
    $root = restore_jobs_path($root);
    $path = $root . '/' . $area;
    if (is_link($path) || !is_dir($path) || realpath($path) === false || restore_jobs_path_key((string)realpath($path)) !== restore_jobs_path_key($path)) throw new RuntimeException('Restore area is not provisioned safely');
    return $path;
}

function restore_jobs_initialize(string $root): void
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Owner CLI required');
    $root = restore_jobs_path($root);
    foreach (['inbox', 'published', 'owner'] as $area) {
        $path = $root . '/' . $area;
        if (!file_exists($path) && !mkdir($path, 0700)) throw new RuntimeException('Cannot provision restore area');
        restore_jobs_directory($root, $area);
    }
    if (!file_exists($root . '/owner/control.json')) restore_jobs_control_write($root, ['mode' => 'normal', 'generation' => 0, 'active_job' => null]);
}

function restore_jobs_id(string $id): string
{
    if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new RuntimeException('Invalid restore job ID');
    return $id;
}

function restore_jobs_job_directory(string $root, string $area, string $id): string
{
    $path = restore_jobs_directory($root, $area) . '/' . restore_jobs_id($id);
    if (is_link($path) || !is_dir($path) || realpath($path) === false || restore_jobs_path_key((string)realpath($path)) !== restore_jobs_path_key($path)) throw new RuntimeException('Unsafe restore job directory');
    return $path;
}

function restore_jobs_read_json(string $file): array
{
    clearstatcache(true, $file);
    if (is_link($file) || !is_file($file) || filesize($file) > RESTORE_JOB_JSON_LIMIT) throw new RuntimeException('Invalid restore record');
    $handle = fopen($file, 'rb');
    if ($handle === false) throw new RuntimeException('Cannot read restore record');
    try { $bytes = stream_get_contents($handle, RESTORE_JOB_JSON_LIMIT + 1); } finally { fclose($handle); }
    if ($bytes === false || strlen($bytes) > RESTORE_JOB_JSON_LIMIT) throw new RuntimeException('Restore record too large');
    $value = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($value) || array_is_list($value)) throw new RuntimeException('Invalid restore record shape');
    return $value;
}

function restore_jobs_write_json(string $file, array $value): void
{
    if (is_link($file) || (file_exists($file) && !is_file($file))) throw new RuntimeException('Unsafe restore record');
    $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    if (strlen($json) > RESTORE_JOB_JSON_LIMIT) throw new RuntimeException('Restore record too large');
    $temporary = dirname($file) . '/.write-' . bin2hex(random_bytes(16));
    $handle = fopen($temporary, 'xb');
    if ($handle === false) throw new RuntimeException('Cannot create restore record');
    try {
        chmod($temporary, 0600);
        $offset = 0;
        while ($offset < strlen($json)) {
            $written = fwrite($handle, substr($json, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('Cannot write restore record');
            $offset += $written;
        }
        if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) throw new RuntimeException('Cannot sync restore record');
        fclose($handle); $handle = null;
        if (!rename($temporary, $file)) throw new RuntimeException('Cannot publish restore record');
    } finally {
        if (is_resource($handle)) fclose($handle);
        if (is_file($temporary)) unlink($temporary);
    }
}

function restore_jobs_lock(string $file, bool $nonblocking = false)
{
    if (is_link($file)) throw new RuntimeException('Unsafe restore lock');
    $handle = fopen($file, 'c+b');
    if ($handle === false) throw new RuntimeException('Cannot open restore lock');
    chmod($file, 0600);
    if (!flock($handle, LOCK_EX | ($nonblocking ? LOCK_NB : 0))) { fclose($handle); return null; }
    return $handle;
}

function restore_jobs_release($lease): void
{
    if (is_resource($lease)) { flock($lease, LOCK_UN); fclose($lease); }
}

function restore_jobs_identity(int $adminId, string $nonce): string
{
    if ($adminId < 1 || !preg_match('/^[a-f0-9]{32,128}$/D', $nonce)) throw new RuntimeException('Invalid restore request identity');
    return hash('sha256', $adminId . ':' . $nonce);
}

function restore_job_create_upload(string $root, array $upload, int $adminId, string $nonce): array
{
    if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('A completed PHP upload is required');
    }
    if (!is_string($upload['name'] ?? null) || !str_ends_with(strtolower($upload['name']), '.doyel.gz')) {
        throw new RuntimeException('Choose a .doyel.gz backup; SQL requires owner-assisted conversion');
    }
    return restore_jobs_submit($root, $upload['tmp_name'], $adminId, $nonce, true);
}

function restore_job_create_local(string $root, string $source, int $adminId, string $nonce): array
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Local fixture ingestion requires CLI');
    return restore_jobs_submit($root, $source, $adminId, $nonce, false);
}

function restore_jobs_submit(string $root, string $source, int $adminId, string $nonce, bool $uploaded): array
{
    $identity = restore_jobs_identity($adminId, $nonce);
    $inbox = restore_jobs_directory($root, 'inbox');
    $lock = restore_jobs_lock($inbox . '/.submit.lock');
    try {
        $index = $inbox . '/nonce-' . $identity . '.json';
        if (file_exists($index)) {
            $old = restore_jobs_read_json($index);
            if (array_keys($old) !== ['id']) throw new RuntimeException('Invalid restore nonce record');
            $dir = restore_jobs_job_directory($root, 'inbox', restore_jobs_id($old['id']));
            $request = restore_jobs_read_json($dir . '/request.json');
            restore_jobs_validate_request($request, $old['id']);
            if ($request['nonce_hash'] !== $identity) throw new RuntimeException('Restore nonce mismatch');
            return $request;
        }
        if (is_link($source) || !is_file($source)) throw new RuntimeException('Invalid restore package');
        $size = filesize($source);
        if ($size === false || $size < 1 || $size > RESTORE_JOB_MAX_UPLOAD) throw new RuntimeException('Restore package exceeds the 64 MiB upload budget');
        $id = bin2hex(random_bytes(16)); $dir = $inbox . '/' . $id;
        if (!mkdir($dir, 0700)) throw new RuntimeException('Cannot create restore job');
        // Reserve the nonce before publishing a consumable request. An interrupted
        // upload fails closed on replay instead of producing a second candidate.
        restore_jobs_write_json($index, ['id' => $id]);
        $destination = $dir . '/backup.doyel.gz';
        if ($uploaded ? !move_uploaded_file($source, $destination) : !copy($source, $destination)) throw new RuntimeException('Cannot quarantine restore package');
        chmod($destination, 0600);
        clearstatcache(true, $destination);
        $size = filesize($destination);
        if ($size < 1 || $size > RESTORE_JOB_MAX_UPLOAD) throw new RuntimeException('Invalid quarantined package size');
        $request = ['version' => 1, 'id' => $id, 'operation' => 'prepare', 'admin_id' => $adminId, 'nonce_hash' => $identity,
            'submitted_at' => gmdate(DATE_ATOM), 'bytes' => $size, 'sha256' => hash_file('sha256', $destination)];
        restore_jobs_write_json($dir . '/request.json', $request);
        return $request;
    } finally { restore_jobs_release($lock); }
}

function restore_jobs_validate_request(array $request, string $id): void
{
    $keys = ['version', 'id', 'operation', 'admin_id', 'nonce_hash', 'submitted_at', 'bytes', 'sha256'];
    if (array_diff(array_keys($request), $keys) || array_diff($keys, array_keys($request)) || $request['version'] !== 1 || $request['id'] !== $id || $request['operation'] !== 'prepare'
        || !is_int($request['admin_id']) || $request['admin_id'] < 1 || !is_int($request['bytes']) || $request['bytes'] < 1 || $request['bytes'] > RESTORE_JOB_MAX_UPLOAD
        || !is_string($request['sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $request['sha256'])
        || !is_string($request['nonce_hash']) || !preg_match('/^[a-f0-9]{64}$/D', $request['nonce_hash'])
        || !is_string($request['submitted_at']) || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/D', $request['submitted_at'])) {
        throw new RuntimeException('Invalid restore request contract');
    }
}

/** Hold the returned lease throughout owner work; null means another worker owns it or no jobs exist. */
function restore_jobs_claim(string $root): ?array
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Owner CLI required');
    $owner = restore_jobs_directory($root, 'owner');
    $lease = restore_jobs_lock($owner . '/worker.lock', true);
    if ($lease === null) return null;
    try {
        // Resume durable work before accepting another candidate.
        foreach (scandir($owner) as $id) {
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) continue;
            $dir = restore_jobs_job_directory($root, 'owner', $id);
            $job = restore_jobs_read_json($dir . '/journal.json');
            restore_jobs_validate_request(array_intersect_key($job, array_flip(['version', 'id', 'operation', 'admin_id', 'nonce_hash', 'submitted_at', 'bytes', 'sha256'])), $id);
            if (!in_array($job['state'] ?? '', ['submitted', 'preflight', 'creating', 'importing', 'validating', 'ready', 'activation_requested', 'frozen', 'backed_up', 'switching', 'awaiting_acceptance', 'active', 'failed', 'cancelled', 'rolled_back'], true)) throw new RuntimeException('Invalid restore journal state');
            restore_jobs_publish($root, $job); // Repair journal-before-publication interruptions, including terminal states.
            if (!in_array($job['state'] ?? '', ['active', 'failed', 'cancelled', 'rolled_back'], true)) {
                restore_jobs_verify_sealed($dir . '/backup.doyel.gz', $job);
                return ['lease' => $lease, 'job' => $job, 'directory' => $dir, 'package' => $dir . '/backup.doyel.gz'];
            }
        }
        foreach (scandir(restore_jobs_directory($root, 'inbox')) as $id) {
            if (!preg_match('/^[a-f0-9]{32}$/D', $id) || file_exists($owner . '/' . $id)) continue;
            $dir = restore_jobs_job_directory($root, 'inbox', $id);
            if (!file_exists($dir . '/request.json')) continue; // An interrupted upload is never consumed.
            $request = restore_jobs_read_json($dir . '/request.json'); restore_jobs_validate_request($request, $id);
            $package = $dir . '/backup.doyel.gz';
            if (is_link($package) || !is_file($package)) throw new RuntimeException('Unsafe restore package');
            $sealed = $owner . '/' . $id;
            if (!mkdir($sealed, 0700)) throw new RuntimeException('Cannot seal restore job');
            try {
                $before = lstat($package);
                $input = fopen($package, 'rb'); $output = fopen($sealed . '/backup.doyel.gz', 'xb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) fclose($input);
                    if (is_resource($output)) fclose($output);
                    throw new RuntimeException('Cannot seal restore package');
                }
                try {
                    chmod($sealed . '/backup.doyel.gz', 0600);
                    $opened = fstat($input);
                    clearstatcache(true, $package);
                    if (is_link($package) || $before === false || $opened === false || $before['ino'] !== $opened['ino'] || $before['dev'] !== $opened['dev'] || ($opened['mode'] & 0170000) !== 0100000) throw new RuntimeException('Restore package changed during claim');
                    $copied = stream_copy_to_stream($input, $output, RESTORE_JOB_MAX_UPLOAD + 1);
                    if (!fflush($output) || (function_exists('fsync') && !fsync($output))) throw new RuntimeException('Cannot sync sealed package');
                } finally { fclose($input); fclose($output); }
                if ($copied !== $request['bytes'] || hash_file('sha256', $sealed . '/backup.doyel.gz') !== $request['sha256']) throw new RuntimeException('Restore package receipt mismatch');
                $job = $request + ['state' => 'submitted', 'updated_at' => gmdate(DATE_ATOM), 'sequence' => 0, 'evidence' => [], 'history' => [['state' => 'submitted', 'at' => gmdate(DATE_ATOM)]]];
                restore_jobs_write_json($sealed . '/journal.json', $job);
                restore_jobs_publish($root, $job);
                return ['lease' => $lease, 'job' => $job, 'directory' => $sealed, 'package' => $sealed . '/backup.doyel.gz'];
            } catch (Throwable $error) {
                // Only our fixed, incomplete sealing artifacts are removed, never candidate databases.
                if (!file_exists($sealed . '/journal.json')) {
                    if (is_file($sealed . '/backup.doyel.gz')) unlink($sealed . '/backup.doyel.gz');
                    rmdir($sealed);
                }
                throw $error;
            }
        }
        restore_jobs_release($lease); return null;
    } catch (Throwable $error) { restore_jobs_release($lease); throw $error; }
}

function restore_jobs_publish(string $root, array $job): void
{
    $status = array_intersect_key($job, array_flip(['version', 'id', 'state', 'submitted_at', 'updated_at', 'bytes', 'sha256', 'sequence', 'evidence']));
    restore_jobs_write_json(restore_jobs_directory($root, 'published') . '/' . restore_jobs_id($job['id']) . '.json', $status);
}

function restore_job_status(string $root, string $id): array
{
    $file = restore_jobs_directory($root, 'published') . '/' . restore_jobs_id($id) . '.json';
    if (!file_exists($file)) {
        $request = restore_jobs_read_json(restore_jobs_job_directory($root, 'inbox', $id) . '/request.json');
        restore_jobs_validate_request($request, $id);
        return ['id' => $id, 'state' => 'submitted', 'bytes' => $request['bytes'], 'sha256' => $request['sha256'], 'submitted_at' => $request['submitted_at']];
    }
    return restore_jobs_read_json($file);
}

function restore_job_transition(string $root, string $id, string $expected, string $next, array $evidence = []): array
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Owner CLI required');
    $states = ['submitted', 'preflight', 'creating', 'importing', 'validating', 'ready', 'activation_requested', 'frozen', 'backed_up', 'switching', 'awaiting_acceptance', 'active'];
    $position = array_search($expected, $states, true);
    $allowed = $position !== false && (($states[$position + 1] ?? null) === $next
        || (!in_array($expected, ['switching', 'awaiting_acceptance', 'active'], true) && in_array($next, ['failed', 'cancelled'], true))
        || ($next === 'rolled_back' && in_array($expected, ['frozen', 'backed_up', 'awaiting_acceptance'], true)));
    if (!$allowed) throw new RuntimeException('Invalid restore state transition');
    foreach ($evidence as $key => $value) {
        if (!in_array($key, ['candidate_ref', 'retained_ref', 'backup_ref', 'validation_ref', 'error_ref'], true) || !is_string($value) || !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $value)) throw new RuntimeException('Invalid sanitized restore evidence');
    }
    $dir = restore_jobs_job_directory($root, 'owner', $id);
    $lock = restore_jobs_lock($dir . '/journal.lock');
    try {
        $job = restore_jobs_read_json($dir . '/journal.json');
        if (($job['id'] ?? null) !== $id || ($job['state'] ?? null) !== $expected) throw new RuntimeException('Stale restore transition');
        $job['state'] = $next; $job['sequence']++; $job['updated_at'] = gmdate(DATE_ATOM);
        $job['history'][] = ['state' => $next, 'at' => $job['updated_at']];
        $job['evidence'] = array_replace($job['evidence'], $evidence);
        restore_jobs_write_json($dir . '/journal.json', $job); restore_jobs_publish($root, $job);
        return $job;
    } finally { restore_jobs_release($lock); }
}

/** A request is an untrusted hint; the owner must independently approve before any privileged action. */
function restore_job_request(string $root, string $id, string $operation, int $adminId, string $nonce, bool $reauthenticated = false): array
{
    if (!in_array($operation, ['activation_request', 'accept', 'rollback'], true) || !$reauthenticated) throw new RuntimeException('Fresh administrator verification required');
    $identity = restore_jobs_identity($adminId, $nonce);
    $dir = restore_jobs_job_directory($root, 'inbox', $id);
    $request = ['version' => 1, 'id' => $id, 'operation' => $operation, 'admin_id' => $adminId, 'nonce_hash' => $identity, 'requested_at' => gmdate(DATE_ATOM), 'reauthenticated' => true];
    $file = $dir . '/action-' . $identity . '.json';
    $lock = restore_jobs_lock($dir . '/action.lock');
    try {
        if (file_exists($file)) {
            $old = restore_jobs_read_json($file);
            if (($old['operation'] ?? null) !== $operation || ($old['admin_id'] ?? null) !== $adminId) throw new RuntimeException('Restore action nonce already used');
            return $old;
        }
        restore_jobs_write_json($file, $request); return $request;
    } finally { restore_jobs_release($lock); }
}

function restore_jobs_control_read(string $root): array
{
    $value = restore_jobs_read_json(restore_jobs_directory($root, 'published') . '/control.json');
    restore_jobs_validate_control($value); return $value;
}

function restore_jobs_validate_control(array $value): void
{
    if (array_keys($value) !== ['mode', 'generation', 'active_job'] || !in_array($value['mode'], ['normal', 'frozen', 'acceptance'], true) || !is_int($value['generation']) || $value['generation'] < 0
        || ($value['active_job'] !== null && (!is_string($value['active_job']) || !preg_match('/^[a-f0-9]{32}$/D', $value['active_job'])))) throw new RuntimeException('Invalid restore control');
}

function restore_jobs_control_write(string $root, array $value): void
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Owner CLI required');
    restore_jobs_validate_control($value);
    restore_jobs_write_json(restore_jobs_directory($root, 'owner') . '/control.json', $value);
    // Published control contains no credentials. Web reads this copy, never the owner directory.
    restore_jobs_write_json(restore_jobs_directory($root, 'published') . '/control.json', $value);
}

/** Owner-only fixed-contract action reader; requests cannot establish owner authorization. */
function restore_jobs_actions(string $root, string $id): array
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Owner CLI required');
    $dir = restore_jobs_job_directory($root, 'inbox', $id);
    $actions = [];
    foreach (scandir($dir) as $name) {
        if (!str_starts_with($name, 'action-')) continue;
        if (!preg_match('/^action-([a-f0-9]{64})\.json$/D', $name, $match)) throw new RuntimeException('Invalid restore action filename');
        $action = restore_jobs_read_json($dir . '/' . $name);
        $keys = ['version', 'id', 'operation', 'admin_id', 'nonce_hash', 'requested_at', 'reauthenticated'];
        if (array_diff(array_keys($action), $keys) || array_diff($keys, array_keys($action)) || $action['version'] !== 1 || $action['id'] !== $id
            || !in_array($action['operation'], ['activation_request', 'accept', 'rollback'], true) || !is_int($action['admin_id']) || $action['admin_id'] < 1
            || $action['nonce_hash'] !== $match[1] || $action['reauthenticated'] !== true || !is_string($action['requested_at'])
            || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/D', $action['requested_at'])) throw new RuntimeException('Invalid restore action contract');
        $actions[] = $action;
    }
    return $actions;
}

function restore_jobs_verify_sealed(string $package, array $job): void
{
    clearstatcache(true, $package);
    if (is_link($package) || !is_file($package) || filesize($package) !== $job['bytes'] || hash_file('sha256', $package) !== $job['sha256']) throw new RuntimeException('Sealed restore package receipt mismatch');
}
