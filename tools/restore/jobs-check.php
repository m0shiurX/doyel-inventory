<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/lib/restore_jobs.php';

$checks = 0;
function jobs_check(bool $condition, string $message): void {
    global $checks; $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}
function jobs_refuses(callable $operation, string $message): void {
    try { $operation(); } catch (Throwable $error) { jobs_check(true, $message); return; }
    jobs_check(false, $message);
}
function jobs_remove(string $path): void {
    if (is_link($path) || !is_dir($path)) { unlink($path); return; }
    foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') jobs_remove($path . '/' . $name);
    rmdir($path);
}
$temporary = realpath(sys_get_temp_dir()) . '/doyel-jobs-check-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
try {
    $root = $temporary . '/private'; mkdir($root, 0700); restore_jobs_initialize($root);
    $fixture = $temporary . '/fixture.doyel.gz'; file_put_contents($fixture, gzencode("data-only test fixture\n"));
    $nonce = str_repeat('a', 32);
    jobs_check(restore_jobs_path_key('C:\\Private\\restore/inbox', true) === restore_jobs_path_key('c:/private/restore/inbox', true), 'Windows mixed separators and case canonicalized');
    jobs_check(restore_jobs_path_key('/Private/restore', false) !== restore_jobs_path_key('/private/restore', false), 'Unix path case preserved');
    jobs_refuses(fn() => restore_jobs_initialize(dirname(__DIR__, 2)), 'public application path rejected');
    jobs_refuses(fn() => restore_jobs_initialize($root . '/../private'), 'traversal root rejected');
    symlink($root, $temporary . '/linked');
    jobs_refuses(fn() => restore_jobs_path($temporary . '/linked'), 'symlink root rejected');
    mkdir($root . '/nested', 0700);
    jobs_refuses(fn() => restore_jobs_path($temporary . '/linked/nested'), 'symlink parent rejected');
    jobs_refuses(fn() => restore_job_status($root, '../../escape'), 'job traversal rejected');
    jobs_refuses(fn() => restore_job_create_upload($root, ['error' => UPLOAD_ERR_OK, 'tmp_name' => $fixture, 'name' => 'test.doyel.gz'], 1, $nonce), 'local file cannot spoof PHP upload');
    jobs_refuses(fn() => restore_job_create_local($root, $fixture, 0, $nonce), 'invalid admin rejected');
    jobs_refuses(fn() => restore_job_create_local($root, $fixture, 1, 'bad'), 'weak nonce rejected');
    $receipt = restore_job_create_local($root, $fixture, 1, $nonce); $id = $receipt['id'];
    jobs_check($receipt['sha256'] === hash_file('sha256', $fixture) && $receipt['bytes'] === filesize($fixture), 'receipt matches actual bytes');
    jobs_check(restore_job_create_local($root, '/does-not-exist', 1, $nonce)['id'] === $id, 'duplicate request resumes same job without reading another file');
    jobs_check(restore_job_status($root, $id)['state'] === 'submitted', 'status exists before worker claim');
    $claim = restore_jobs_claim($root);
    jobs_check($claim['job']['id'] === $id && file_get_contents($claim['package']) === file_get_contents($fixture), 'owner seals exact package');
    jobs_check(restore_jobs_claim($root) === null, 'nonblocking worker lease prevents competing worker');
    $job = restore_job_transition($root, $id, 'submitted', 'preflight');
    jobs_refuses(fn() => restore_job_transition($root, $id, 'submitted', 'preflight'), 'stale journal transition rejected');
    jobs_refuses(fn() => restore_job_transition($root, $id, 'preflight', 'active'), 'skipped stages rejected');
    jobs_refuses(fn() => restore_job_transition($root, $id, 'preflight', 'creating', ['password' => 'secret']), 'secret fields rejected');
    jobs_refuses(fn() => restore_job_transition($root, $id, 'preflight', 'creating', ['error_ref' => 'SQL private data']), 'raw errors rejected');
    jobs_check(!isset(restore_job_status($root, $id)['admin_id']), 'public status omits administrator and nonce');
    jobs_refuses(fn() => restore_job_request($root, $id, 'accept', 1, str_repeat('b', 32)), 'fresh reauthentication required');
    $action = restore_job_request($root, $id, 'activation_request', 1, str_repeat('b', 32), true);
    jobs_check(restore_job_request($root, $id, 'activation_request', 1, str_repeat('b', 32), true) === $action, 'action nonce idempotent');
    jobs_refuses(fn() => restore_job_request($root, $id, 'rollback', 1, str_repeat('b', 32), true), 'nonce cannot authorize a different operation');
    jobs_refuses(fn() => restore_job_request($root, $id, 'execute', 1, str_repeat('c', 32), true), 'unknown operation rejected');
    jobs_check(count(restore_jobs_actions($root, $id)) === 1, 'owner reads validated fixed action');
    $actionFile = $root . '/inbox/' . $id . '/action-' . $action['nonce_hash'] . '.json';
    restore_jobs_write_json($actionFile, $action + ['host' => 'attacker']);
    jobs_refuses(fn() => restore_jobs_actions($root, $id), 'owner rejects unknown action headers');
    restore_jobs_write_json($actionFile, $action);
    restore_jobs_release($claim['lease']);
    $claim = restore_jobs_claim($root);
    jobs_check($claim['job']['state'] === 'preflight', 'restart resumes durable journal');
    restore_jobs_release($claim['lease']);
    file_put_contents($claim['package'], 'tampered');
    jobs_refuses(fn() => restore_jobs_claim($root), 'restart refuses tampered owner seal');
    copy($fixture, $claim['package']);
    restore_job_transition($root, $id, 'preflight', 'failed', ['error_ref' => 'test_ref']);
    $other = restore_job_create_local($root, $fixture, 2, $nonce);
    jobs_check($other['id'] !== $id, 'nonce scoped to admin identity');
    $requestFile = $root . '/inbox/' . $other['id'] . '/request.json';
    $malformed = $other + ['database' => 'arbitrary']; restore_jobs_write_json($requestFile, $malformed);
    jobs_refuses(fn() => restore_jobs_claim($root), 'unknown request headers refused');
    restore_jobs_write_json($requestFile, $other);
    $package = dirname($requestFile) . '/backup.doyel.gz'; file_put_contents($package, 'changed');
    jobs_refuses(fn() => restore_jobs_claim($root), 'upload tampering fails receipt before consume');
    jobs_check(!is_dir($root . '/owner/' . $other['id']), 'failed seal does not leave a resumable owner job');
    unlink($package); symlink($fixture, $package);
    jobs_refuses(fn() => restore_jobs_claim($root), 'symlink package refused');
    unlink($package); copy($fixture, $package);
    $claim = restore_jobs_claim($root); restore_jobs_release($claim['lease']);
    jobs_check($claim['job']['id'] === $other['id'], 'worker lease released after errors');
    restore_jobs_control_write($root, ['mode' => 'frozen', 'generation' => 1, 'active_job' => $other['id']]);
    jobs_check(restore_jobs_control_read($root)['mode'] === 'frozen', 'published control readable without owner secrets');
    jobs_refuses(fn() => restore_jobs_control_write($root, ['mode' => 'normal', 'generation' => 2, 'active_job' => null, 'password' => 'secret']), 'control rejects credentials');
    foreach (['frozen', 'backed_up', 'awaiting_acceptance'] as $rollbackState) {
        $job = restore_jobs_read_json($claim['directory'] . '/journal.json');
        $job['state'] = $rollbackState;
        restore_jobs_write_json($claim['directory'] . '/journal.json', $job);
        jobs_check(restore_job_transition($root, $other['id'], $rollbackState, 'rolled_back')['state'] === 'rolled_back', 'owner rollback journal from ' . $rollbackState);
        jobs_check(restore_jobs_claim($root) === null, 'rolled back job is terminal');
        jobs_refuses(fn() => restore_job_transition($root, $other['id'], 'rolled_back', 'active'), 'rolled back job cannot reactivate');
    }
    $job['state'] = 'active'; restore_jobs_write_json($claim['directory'] . '/journal.json', $job);
    jobs_refuses(fn() => restore_job_transition($root, $other['id'], 'active', 'rolled_back'), 'active job cannot roll back');
    $job['state'] = 'switching'; restore_jobs_write_json($claim['directory'] . '/journal.json', $job);
    jobs_refuses(fn() => restore_job_transition($root, $other['id'], 'switching', 'rolled_back'), 'unreconciled switching cannot roll back');
    $job['state'] = 'unknown'; restore_jobs_write_json($claim['directory'] . '/journal.json', $job);
    jobs_refuses(fn() => restore_jobs_claim($root), 'unknown journal state refused');
    $target = $temporary . '/atomic.json'; file_put_contents($target, 'original'); symlink($target, $temporary . '/atomic-link.json');
    jobs_refuses(fn() => restore_jobs_write_json($temporary . '/atomic-link.json', ['replace' => true]), 'atomic publisher refuses symlink target');
    jobs_check(file_get_contents($target) === 'original', 'failed atomic publication preserves prior file');
    file_put_contents($temporary . '/large.json', str_repeat(' ', RESTORE_JOB_JSON_LIMIT + 1));
    jobs_refuses(fn() => restore_jobs_read_json($temporary . '/large.json'), 'bounded JSON records');
    echo 'Restore job checks passed: ' . $checks . "\n";
} finally { jobs_remove($temporary); }
