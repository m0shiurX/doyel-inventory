<?php
/** Read-only, local-only schema/EXPLAIN evidence for S1.1. */
require __DIR__ . '/characterize/common.php';
if (in_array($argv[1] ?? '', ['-h', '--help'], true)) {
    echo "Usage: php tools/phase1-check.php <schema|explain> <private-output.json>\n";
    echo "Reads local legacy software at 127.0.0.1:33057; output must be beneath DOYEL_FIXTURES.\n";
    exit;
}
try {
    if ($argc !== 3 || !in_array($argv[1], ['schema', 'explain'], true)) throw new RuntimeException('Invalid arguments; use -h');
    $fixtures = realpath(getenv('DOYEL_FIXTURES') ?: getenv('HOME') . '/.doyel-fixtures');
    $parent = realpath(dirname($argv[2]));
    $repo = realpath(__DIR__ . '/..');
    if (!$fixtures || !$parent || !str_starts_with($parent . '/', $fixtures . '/') || str_starts_with($fixtures . '/', $repo . '/') || !str_ends_with($argv[2], '.json') || is_link($argv[2])) throw new RuntimeException('Output must be a non-symlink JSON file inside the external fixture directory');
    umask(0077);
    $pdo = new PDO('mysql:host=127.0.0.1;port=33057;dbname=software;charset=utf8', 'root', 'doyel-local', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true, PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
    if ($argv[1] === 'schema') {
        $result = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $result[$table] = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
    } else {
        putenv('DOYEL_DB_HOST=127.0.0.1'); putenv('DOYEL_DB_PORT=33057'); putenv('DOYEL_DB_NAME=software'); putenv('DOYEL_DB_USER=root'); putenv('DOYEL_DB_PASS=doyel-local');
        $variables = characterization_variables();
        $one = static fn($sql) => $pdo->query($sql)->fetchColumn();
        $head = $one('SELECT HeadOfAccount FROM canteen_expenses ORDER BY PaymentID LIMIT 1');
        $values = array_merge($variables, [
            'Description' => $variables['customer_name'], 'Nickname' => $variables['customer_name'], 'CustomerName' => $variables['customer_name'],
            'UserName' => $one('SELECT UserName FROM canteen_sales GROUP BY UserName ORDER BY COUNT(*) DESC LIMIT 1'),
            'username' => 'rayhan', 'password' => md5('admin-test'), 'oPass' => md5('admin-test'),
            'SupplierName' => $one('SELECT SupplierName FROM canteen_supplier ORDER BY SupplierId LIMIT 1'),
            'Supplier' => $one('SELECT SupplierName FROM canteen_supplier ORDER BY SupplierId LIMIT 1'),
            'HeadOfAccount' => $head, 'FormDate' => '2016-01-01 00:00:00', 'ToDate' => '2026-10-25 23:59:59',
            'date2' => '2026-10-25', 'SlNo' => $variables['snapshot_id'], 'SalesID' => '7559', 'BillNo' => $variables['purchase_id'],
            'ItemID' => $variables['item_id'], 'ItemName' => $variables['item_name'], 'q' => $variables['item_name'],
            'search_term' => $variables['item_name'], 'ItemType' => $one("SELECT ItemType FROM canteen_item WHERE ItemType<>'' ORDER BY ItemID LIMIT 1"),
            'GenericName' => $one('SELECT GenericName FROM canteen_item ORDER BY ItemID LIMIT 1'), 'p' => '7559', 'ID' => $variables['item_id'],
        ]);
        // PHP tokens avoid comments and preserve interpolated SQL as one template.
        $templates = [];
        $tree = new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($repo, FilesystemIterator::SKIP_DOTS), static fn($entry) => !$entry->isDir() || !in_array($entry->getFilename(), ['.git', 'graft', 'tools'], true));
        $paths = new RecursiveIteratorIterator($tree);
        foreach ($paths as $path) {
            $relative = substr($path->getPathname(), strlen($repo) + 1);
            if ($path->getExtension() !== 'php' || preg_match('~^(?:tools|graft|\.git)/~', $relative)) continue;
            $tokens = token_get_all(file_get_contents($path->getPathname()));
            $interpolated = false; $text = ''; $line = 0;
            $record = static function (string $sql, int $line) use (&$templates, $relative): void {
                $sql = trim($sql);
                if (!preg_match('/^(?:SELECT|UPDATE|DELETE)\s/i', $sql)) return;
                if (preg_match('/^SELECT\s/i', $sql) && !preg_match('/\bFROM\b/i', $sql)) return;
                $shape = preg_replace('/\s+/', ' ', $sql);
                $templates[$shape]['template'] = $shape;
                $templates[$shape]['sources'][] = $relative . ':' . $line;
            };
            foreach ($tokens as $token) {
                if ($token === '"') {
                    if ($interpolated) { $record($text, $line); $interpolated = false; }
                    else { $interpolated = true; $text = ''; }
                    continue;
                }
                if (is_array($token)) {
                    if ($interpolated) {
                        if ($text === '') $line = $token[2];
                        $text .= $token[1];
                    } elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                        $record(substr($token[1], 1, -1), $token[2]);
                    }
                } elseif ($interpolated) $text .= $token;
            }
        }
        ksort($templates); $result = [];
        foreach ($templates as $shape => $entry) {
            // Login/password concatenate quoted fragments rather than interpolate.
            if (preg_match("/^SELECT \* FROM l_user WHERE UserName = '$/", $shape)) {
                $shape = "SELECT * FROM l_user WHERE UserName = '\$username' AND password = '\$password'";
            }
            $queryValues = $values;
            if (str_contains($shape, '$FormDate 00:00:00')) $queryValues['FormDate'] = '2016-01-01';
            if (str_contains($shape, '$ToDate 23:59:59')) $queryValues['ToDate'] = '2026-10-25';
            if (str_contains($shape, 'canteen_stock_detail')) $queryValues['SupplierName'] = $one('SELECT SupplierName FROM canteen_stock_detail WHERE SlNo=' . (int)$variables['snapshot_id'] . ' ORDER BY ItemID LIMIT 1');
            if (str_contains($shape, 'canteen_collection')) $queryValues['HeadOfAccount'] = $one('SELECT HeadOfAccount FROM canteen_collection GROUP BY HeadOfAccount ORDER BY COUNT(*) DESC LIMIT 1');
            if (str_contains($shape, 'canteen_expenses')) {
                $queryValues['HeadOfAccount'] = $one('SELECT HeadOfAccount FROM canteen_expenses GROUP BY HeadOfAccount ORDER BY COUNT(*) DESC LIMIT 1');
            }
            $sql = preg_replace_callback('/\$([A-Za-z_][A-Za-z0-9_]*)/', static function ($match) use ($queryValues) {
                return str_replace(["\\", "'"], ["\\\\", "\\'"], (string)($queryValues[$match[1]] ?? '1'));
            }, $shape);
            $id = 'Q' . str_pad((string)(count($result) + 1), 3, '0', STR_PAD_LEFT);
            $item = ['id' => $id, 'sources' => array_values(array_unique($entry['sources'])), 'template' => $entry['template'], 'sql' => $sql];
            try { $item['plan'] = $pdo->query('EXPLAIN ' . $sql)->fetchAll(PDO::FETCH_ASSOC); }
            catch (PDOException $error) { $item['error'] = $error->getCode(); $item['diagnostic'] = $error->getMessage(); }
            $result[] = $item;
        }
        // Selective real predicates also expose indexes when full-history scans
        // are correctly cheaper than range access on almost the entire table.
        foreach ([
            'sales-date-month' => "SELECT UserName,SUM(Amount) FROM canteen_sales WHERE Date BETWEEN '2026-09-01' AND '2026-09-30 23:59:59' GROUP BY UserName",
            'sales-post-month' => "SELECT * FROM canteen_sales WHERE Post=1 AND Date BETWEEN '2026-09-01' AND '2026-09-30 23:59:59'",
            'purchase-post-month' => "SELECT * FROM canteen_purchase WHERE Post=1 AND Date BETWEEN '2026-09-01' AND '2026-09-30 23:59:59'",
            'sales-detail-month' => "SELECT SUM(Qnty) FROM canteen_sales_detail WHERE Date BETWEEN '2026-09-01' AND '2026-09-30 23:59:59'",
            'collection-month' => "SELECT SUM(amount) FROM canteen_collection WHERE Date BETWEEN '2026-09-01' AND '2026-09-30 23:59:59'",
            'expenses-month' => "SELECT SUM(amount) FROM canteen_expenses WHERE Date BETWEEN '2026-09-01' AND '2026-09-30 23:59:59'",
        ] as $id => $sql) $result[] = ['id' => $id, 'sources' => ['selective variant of period-query shapes'], 'template' => $sql, 'sql' => $sql, 'plan' => $pdo->query('EXPLAIN ' . $sql)->fetchAll(PDO::FETCH_ASSOC)];
    }
    file_put_contents($argv[2], json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    printf("Wrote %d %s evidence entries to private output\n", count($result), $argv[1]);
} catch (Throwable $error) {
    fwrite(STDERR, $error instanceof PDOException ? "Local schema/EXPLAIN check failed\n" : $error->getMessage() . "\n"); exit(1);
}
