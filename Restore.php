<?php
require __DIR__ . '/lib/bootstrap.php';
require_admin();
require_once __DIR__ . '/lib/app_version.php';
require_once __DIR__ . '/lib/backup_schema.php';
require_once __DIR__ . '/lib/restore_jobs.php';
header('Cache-Control: no-store');

function restore_ui_nonce(string $purpose): string
{
    $nonce = bin2hex(random_bytes(24));
    $_SESSION['restore_nonces'][$nonce] = $purpose;
    $_SESSION['restore_nonces'] = array_slice($_SESSION['restore_nonces'], -32, null, true);
    return $nonce;
}
function restore_ui_status(array $record): array
{
    $states = ['submitted','preflight','creating','importing','validating','ready','activation_requested','frozen','backed_up','switching','awaiting_acceptance','active','failed','cancelled','rolled_back'];
    $state = in_array($record['state'] ?? '', $states, true) ? $record['state'] : 'unavailable';
    $out = ['state' => $state];
    foreach (['submitted_at','updated_at','sha256'] as $key) {
        if (is_string($record[$key] ?? null) && strlen($record[$key]) <= 80) $out[$key] = $record[$key];
    }
    if (is_int($record['bytes'] ?? null)) $out['bytes'] = $record['bytes'];
    foreach (['candidate_ref','retained_ref','backup_ref','validation_ref','error_ref'] as $key) {
        $value = $record['evidence'][$key] ?? null;
        if (is_string($value) && preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $value)) $out[$key] = $value;
    }
    return $out;
}
function restore_ui_validation(string $root, string $id): ?array
{
    $file = restore_jobs_directory($root, 'published') . '/' . restore_jobs_id($id) . '.validation.json';
    return file_exists($file) ? restore_jobs_read_json($file) : null;
}
function restore_ui_text(mixed $value): string
{
    return is_scalar($value) ? substr((string)$value, 0, 180) : 'Unavailable';
}
$root = (string)(app_config()['restore_dir'] ?? '');
$message = ''; $status = null; $validation = null; $job = '';
$viewStatus = ($_GET['view'] ?? '') === 'status';
try {
    if (isset($_GET['job'])) {
        if (!is_string($_GET['job'])) throw new RuntimeException();
        $job = restore_jobs_id($_GET['job']);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        // Oversized requests can lose both POST and FILES at PHP's boundary.
        if ($_POST === [] && $_FILES === []) {
            http_response_code(413);
            $message = 'The upload was missing or exceeded the server upload limit. Choose a .doyel.gz file up to 64 MiB; the owner may need to raise PHP upload limits.';
        } else {
            require_csrf();
            $operation = $_POST['operation'] ?? '';
            if (!is_string($operation)) throw new RuntimeException();
            if (function_exists('restore_maintenance_read_only') && restore_maintenance_read_only() && !in_array($operation, ['accept','rollback'], true)) throw new RuntimeException();
            $nonce = $_POST['nonce'] ?? '';
            $purpose = $operation === 'upload' ? 'upload' : $operation . ':' . $job;
            if (!is_string($nonce) || ($_SESSION['restore_nonces'][$nonce] ?? null) !== $purpose) throw new RuntimeException();
            if ($root === '') throw new RuntimeException();
            $adminId = current_user_id();
            if ($adminId === null) throw new RuntimeException();
            if ($operation === 'upload') {
                $upload = $_FILES['backup'] ?? [];
                if (!is_array($upload) || !is_string($upload['name'] ?? null) || !str_ends_with(strtolower($upload['name']), '.doyel.gz')) {
                    $message = 'Choose a .doyel.gz restore backup. SQL backups require owner-assisted conversion; SQL is never executed here.';
                    http_response_code(400);
                } elseif (($upload['error'] ?? null) !== UPLOAD_ERR_OK) {
                    $message = 'The upload did not complete. Check the file size and server upload limit, then retry.';
                    http_response_code(400);
                } else {
                    $receipt = restore_job_create_upload($root, $upload, $adminId, $nonce);
                    redirect('Restore.php?job=' . $receipt['id']);
                }
            } elseif (in_array($operation, ['activation_request','accept','rollback'], true) && $job !== '') {
                $status = restore_ui_status(restore_job_status($root, $job));
                $allowed = $operation === 'activation_request' ? ['ready'] : ($operation === 'accept' ? ['awaiting_acceptance'] : ['frozen','backed_up','awaiting_acceptance']);
                if (!in_array($status['state'], $allowed, true) || ($_POST['confirm'] ?? '') !== 'yes') throw new RuntimeException();
                if ($operation === 'activation_request') {
                    $validation = restore_ui_validation($root, $job);
                    if ($validation === null || ($validation['schema']['compatible'] ?? null) !== true || ($validation['comparisons']['row_digests_match'] ?? null) !== true || ($validation['comparisons']['inventory_match'] ?? null) !== true || ($validation['comparisons']['foreign_keys_valid'] ?? null) !== true || !is_string($validation['backup_asof'] ?? null) || !is_string($validation['candidate_database'] ?? null) || !is_string($validation['expected_active_database'] ?? null) || ($_POST['work_gap'] ?? '') !== 'yes') throw new RuntimeException();
                }
                $password = $_POST['password'] ?? '';
                $user = db_one('SELECT password, userAtho FROM l_user WHERE user_id = ?', [$adminId]);
                $verified = is_string($password) && strlen($password) <= 4096 && $user !== null && (string)$user->userAtho === '1' && auth_password_matches($password, (string)$user->password);
                unset($password, $_POST['password'], $user);
                if (!$verified) {
                    http_response_code(403); $message = 'Administrator password verification failed. Enter your current administrator password again.';
                } else {
                    restore_job_request($root, $job, $operation, $adminId, $nonce, true);
                    $_SESSION['restore_requested'] = $job;
                    redirect('Restore.php?job=' . $job);
                }
            } else throw new RuntimeException();
        }
    }
    if ($job !== '') {
        $status = restore_ui_status(restore_job_status($root, $job));
        $validation = restore_ui_validation($root, $job);
    }
} catch (Throwable $error) {
    http_response_code(400);
    $message = 'Restore request unavailable or no longer valid. Reload this page and try again. Ask the owner to check private restore storage and the job reference if this continues.';
}
if ($viewStatus) {
    header('Content-Type: application/json; charset=UTF-8');
    if ($status === null || $message !== '') { http_response_code(400); echo json_encode(['error' => 'Status unavailable. Reopen the restore page.']); }
    else echo json_encode($status, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}
$requested = $job !== '' && ($_SESSION['restore_requested'] ?? null) === $job;
unset($_SESSION['restore_requested']);
$version = doyel_app_version(); $engine = 'Unavailable'; $compatible = false;
try {
    $server = (string)db()->query('SELECT VERSION()')->fetchColumn();
    if (preg_match('/\A(\d+\.\d+\.\d+)(?:[-.]|$)/D', $server, $m)) $engine = (stripos($server, 'MariaDB') !== false ? 'MariaDB ' : 'MySQL ') . $m[1];
    doyel_schema_assert(db()); $compatible = true;
} catch (Throwable $error) {}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Backup &amp; restore</title><link href="css/Style.css" rel="stylesheet"><link href="css/restore.css" rel="stylesheet"></head><body><div id="main" style="margin:0 auto">
<?php include __DIR__ . '/menu.php'; ?>
<main class="restore-panel"><h1>Backup &amp; restore</h1>
<p>Installed application <?= e($version['version']) ?> · build <?= e($version['build']) ?> · <?= e($engine) ?> · schema <?= e($version['schema_version']) ?>: <?= $compatible ? 'compatible' : 'unconfirmed; owner review required' ?>.</p>
<p><a href="backup.php?format=restore">Download restore backup (.doyel.gz)</a> · <a href="backup.php">Download SQL backup</a> · <a href="About.php">About Doyel</a></p>
<p>Restore prepares a new database. The old database and a verified pre-switch backup are retained. An older backup may omit recent work; newer work is never merged automatically. SQL backups need owner-assisted conversion.</p>
<?php if ($message !== ''): ?><p class="restore-message" role="alert"><?= e($message) ?></p><?php endif; ?>
<?php if ($requested): ?><p class="restore-message">Request recorded. Waiting for database maintenance helper and independent owner approval. No switch or acceptance is completed by this page.</p><?php endif; ?>
<?php if ($root === ''): ?>
<h2>Owner setup required</h2><p>The owner must provision private restore storage outside the website, its inbox and published status permissions, and the offline database maintenance helper; then configure restore_dir. This page cannot create owner storage or install privileged credentials.</p>
<?php elseif (function_exists('restore_maintenance_read_only') && restore_maintenance_read_only()): ?>
<p>Read-only acceptance is in progress. New uploads and activation requests wait until maintenance is complete.</p>
<?php else: ?>
<h2>Prepare a restore</h2><form method="post" enctype="multipart/form-data" action="Restore.php">
<?php csrf_field(); ?><input type="hidden" name="operation" value="upload"><input type="hidden" name="nonce" value="<?= e(restore_ui_nonce('upload')) ?>"><input type="hidden" name="MAX_FILE_SIZE" value="67108864">
<label>Restore backup (.doyel.gz, maximum 64 MiB)<input type="file" name="backup" accept=".doyel.gz" required></label><button type="submit">Upload and prepare</button></form>
<p>Preparation waits for the owner helper. Closing this page does not cancel the job. Keep its reference to reopen progress.</p>
<?php endif; ?>
<?php if ($status !== null): ?>
<section id="restore-job" data-job="<?= e($job) ?>" data-state="<?= e($status['state']) ?>"><h2>Restore job</h2><p>Reference: <code><?= e($job) ?></code></p><p>Stage: <strong id="restore-state"><?= e(str_replace('_', ' ', $status['state'])) ?></strong></p><p id="restore-poll" role="status">Waiting for database maintenance helper. The helper runs outside this website.</p>
<dl><?php foreach (['submitted_at'=>'Submitted (UTC)','updated_at'=>'Updated (UTC)','bytes'=>'Uploaded bytes','sha256'=>'Upload SHA256','candidate_ref'=>'Candidate reference','retained_ref'=>'Retained old database reference','backup_ref'=>'Pre-switch backup reference','error_ref'=>'Owner error reference'] as $key=>$label): if (isset($status[$key])): ?><dt><?= e($label) ?></dt><dd><?= e($status[$key]) ?></dd><?php endif; endforeach; ?></dl>
<?php if ($validation === null): ?><p>Validation evidence is not yet available. Activation requires completed helper validation.</p>
<?php else: ?><h3>Candidate review</h3><dl><?php foreach (['backup_asof'=>'Backup as of (UTC)','candidate_database'=>'Proposed candidate database','expected_active_database'=>'Previous/current database'] as $key=>$label): ?><dt><?= e($label) ?></dt><dd><?= e(restore_ui_text($validation[$key] ?? null)) ?></dd><?php endforeach; ?><dt>Current time (UTC)</dt><dd><?= e(gmdate(DATE_ATOM)) ?></dd></dl>
<p>Source application: <?= e(restore_ui_text($validation['app']['version'] ?? null)) ?> · build <?= e(restore_ui_text($validation['app']['build'] ?? null)) ?> · source schema <?= e(restore_ui_text($validation['schema']['version'] ?? null)) ?>.</p>
<?php $backupTime = is_string($validation['backup_asof'] ?? null) ? strtotime($validation['backup_asof']) : false; if ($backupTime !== false): $ageHours = max(0, (int)floor((time() - $backupTime) / 3600)); ?><p>Backup age now: <?= e(intdiv($ageHours, 24)) ?> days and <?= e($ageHours % 24) ?> hours (UTC comparison).</p><?php endif; ?>
<p>Potential work gap: all work after the backup time above and before the switch. The current database inventory may be larger than an older candidate; this difference alone does not indicate corruption. The helper must prove the candidate matches the source manifest, row digests, balances and relationships.</p>
<h3>Validation checks</h3><ul><?php foreach (['row_digests_match'=>'Source row counts and digests','inventory_match'=>'Financial inventory and customer balances','foreign_keys_valid'=>'Relationships'] as $key=>$label): ?><li><?= e($label) ?>: <?= ($validation['comparisons'][$key] ?? null) === true ? 'verified' : 'unconfirmed; owner review required' ?></li><?php endforeach; ?><li>Schema: <?= ($validation['schema']['compatible'] ?? null) === true ? 'compatible' : 'unconfirmed; owner review required' ?></li></ul>
<?php $balance = $validation['customer_balances'] ?? []; if (is_array($balance) && preg_match('/^\d+$/D', (string)($balance['count'] ?? '')) && is_string($balance['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $balance['sha256'])): ?><p>Customer balance inventory: <?= e($balance['count']) ?> records; SHA256 <code><?= e($balance['sha256']) ?></code>. Individual balances and names are not displayed.</p><?php endif; ?>
<p>Current database counts as of (UTC): <?= e(restore_ui_text($validation['active_counts_asof'] ?? null)) ?>. This snapshot does not freeze later work.</p>
<h3>Row inventory</h3><table><thead><tr><th>Table</th><th>Current database</th><th>Candidate</th></tr></thead><tbody><?php foreach (doyel_schema_tables() as $table=>$definition): ?><tr><th><?= e($table) ?></th><td><?= e(restore_ui_text($validation['active_row_counts'][$table] ?? null)) ?></td><td><?= e(restore_ui_text($validation['row_counts'][$table] ?? null)) ?></td></tr><?php endforeach; ?></tbody></table>
<h3>Financial totals</h3><dl><?php foreach ((is_array($validation['financial'] ?? null) ? $validation['financial'] : []) as $key=>$value): if (is_string($key) && preg_match('/^(canteen_[a-z_]+)\.([A-Za-z]+)$/D', $key, $financialKey) && isset(doyel_schema_tables()[$financialKey[1]]) && in_array($financialKey[2], doyel_schema_tables()[$financialKey[1]]['columns'], true) && is_scalar($value) && preg_match('/^-?\d+(?:\.\d+)?$/D', (string)$value)): ?><dt><?= e($key) ?></dt><dd><?= e($value) ?></dd><?php endif; endforeach; ?></dl>
<h3>Historical coverage</h3><dl><?php foreach (doyel_schema_tables() as $table=>$definition): $dates = $validation['date_coverage'][$table] ?? null; if (is_array($dates)): ?><dt><?= e($table) ?></dt><dd><?= e(restore_ui_text($dates['first'] ?? null)) ?> — <?= e(restore_ui_text($dates['last'] ?? null)) ?></dd><?php endif; endforeach; ?></dl>
<h3>Account roles</h3><p>Imported accounts and password hashes are preserved. No default account is added. The owner must verify a candidate administrator and ordinary-user access through the offline helper before switching.</p><ul><?php foreach ((is_array($validation['roles'] ?? null) ? $validation['roles'] : []) as $role): if (is_array($role) && preg_match('/^\d+$/D', (string)($role['userAtho'] ?? '')) && preg_match('/^\d+$/D', (string)($role['count'] ?? ''))): ?><li>Role <?= e($role['userAtho']) ?>: <?= e($role['count']) ?> accounts</li><?php endif; endforeach; ?></ul>
<?php endif; ?>
<?php $operations = $status['state'] === 'ready' && $validation !== null ? ['activation_request'=>'Request activation'] : ($status['state'] === 'awaiting_acceptance' ? ['accept'=>'Request owner acceptance','rollback'=>'Request rollback before reopening writes'] : (in_array($status['state'], ['frozen','backed_up'], true) ? ['rollback'=>'Request safe rollback'] : [])); foreach ($operations as $operation=>$label): ?>
<h3><?= e($label) ?></h3><form method="post" action="Restore.php?job=<?= e($job) ?>"><?php csrf_field(); ?><input type="hidden" name="operation" value="<?= e($operation) ?>"><input type="hidden" name="nonce" value="<?= e(restore_ui_nonce($operation . ':' . $job)) ?>">
<?php if ($operation === 'activation_request'): ?><p>Confirm the database identities and backup time above. The owner must separately verify candidate credentials in the offline helper before any switch.</p><label><input type="checkbox" name="work_gap" value="yes" required> I reviewed the backup age and potential work gap. Recent work stays in the retained old database and is not merged automatically.</label><?php endif; ?>
<label><input type="checkbox" name="confirm" value="yes" required> I confirm this request for the job shown above. The owner helper must independently approve it.</label><label>Current administrator password<input type="password" name="password" autocomplete="current-password" required maxlength="4096"></label><button type="submit"><?= e($label) ?></button></form><?php endforeach; ?>
<p>During maintenance the web service may be offline. Reopen this saved job URL when it returns and sign in again if requested; all old sessions expire when databases change. Business writes remain frozen until owner acceptance. Blind rollback is refused after new work begins.</p></section>
<script src="codes/restore.js" defer></script><?php endif; ?>
<p><a href="Accounts.php">Back to Accounts</a></p></main><?php include __DIR__ . '/footer.php'; ?></div></body></html>
