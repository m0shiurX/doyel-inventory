<?php
require __DIR__ . '/lib/bootstrap.php';
require_admin();
require_once __DIR__ . '/lib/app_version.php';
require_once __DIR__ . '/lib/backup_schema.php';
$version = doyel_app_version();
$engine = 'Unavailable';
$compatibility = 'Unable to verify. Contact the owner before restoring.';
try {
    $server = (string)db()->query('SELECT VERSION()')->fetchColumn();
    // Display only a recognized engine/version token; never a server-supplied path or diagnostic.
    if (preg_match('/\A(\d+\.\d+\.\d+)(?:[-.]|$)/D', $server, $match)) {
        $engine = (stripos($server, 'MariaDB') !== false ? 'MariaDB ' : 'MySQL ') . $match[1];
    }
    doyel_schema_assert(db());
    $compatibility = 'Compatible with schema ' . $version['schema_version'] . '.';
} catch (Throwable $error) {
    $compatibility = 'Schema compatibility could not be confirmed. Restore is blocked until owner review.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>About Doyel</title>
<link href="css/Style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div id="main" style="margin:0 auto">
<?php include __DIR__ . '/menu.php'; ?>
<div class="report" style="margin:40px 20px;padding:20px">
<h1>About Doyel</h1>
<dl>
<dt>Application version</dt><dd><?= e($version['version']) ?></dd>
<dt>Build</dt><dd><?= e($version['build']) ?></dd>
<dt>Backup package format</dt><dd><?= e($version['backup_format']) ?></dd>
<dt>Database engine</dt><dd><?= e($engine) ?></dd>
<dt>Schema compatibility</dt><dd><?= e($compatibility) ?></dd>
</dl>
<p>Schema 1 requires the reviewed numeric, index, username uniqueness and relationship migrations. Unknown schemas require owner review.</p>
<p>Updates and migrations are installed by the owner after backup and rehearsal. This page makes no changes.</p>
<p>Windows installation, service coordination and restore acceptance must pass on the actual target before operational use.</p>
<p><a href="Accounts.php">Back to Accounts</a></p>
</div>
<?php include __DIR__ . '/footer.php'; ?>
</div>
</body>
</html>
