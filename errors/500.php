<?php
if (!isset($reference, $error) || !$error instanceof Throwable) {
    http_response_code(404);
    exit;
}
?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<title>Something went wrong</title>
<h1>Something went wrong</h1>
<p>Reference: <?= htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php if ($details !== null): ?>
<pre><?= htmlspecialchars($details, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
<?php endif; ?>
</html>
