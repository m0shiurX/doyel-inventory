<?php
// Read-only owner CLI. Outputs counts/IDs only; private snapshots contain digests.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../lib/config.php';
require __DIR__ . '/../../db/cleanup/paths.php';
try {
    $opt = getopt('', ['preflight', 'snapshot:', 'compare:', 'normalized-snapshot:', 'normalized-compare:', 'md5']);
    if (!$opt || count($opt) !== 1) throw new RuntimeException('Usage: php tools/prod/inspect.php --preflight | --snapshot=<new-private.json> | --compare=<private.json> | --normalized-snapshot=<new-private.json> | --normalized-compare=<private.json> | --md5');
    if (PHP_VERSION_ID < 80400 || PHP_VERSION_ID >= 80500) throw new RuntimeException('Selected runtime must be PHP 8.4');
    foreach (['pdo_mysql','mbstring','dom'] as $ext) if (!extension_loaded($ext)) throw new RuntimeException('Missing extension: ' . $ext);
    if (!defined('PASSWORD_ARGON2ID')) throw new RuntimeException('Argon2id unavailable');
    foreach (["nul\0password", str_repeat('x', 73)] as $password) {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        if (!password_verify($password, $hash)) throw new RuntimeException('Argon2id verification failed');
    }
    $c = app_config();
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $c['db_name'])) throw new RuntimeException('Invalid database name');
    $p = new PDO('mysql:host=' . $c['db_host'] . ';port=' . $c['db_port'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_STRINGIFY_FETCHES=>true, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>false]);
    $tables = ['canteen_ac_head','canteen_collection','canteen_customar','canteen_expenses','canteen_item','canteen_purchase','canteen_purchase_detail','canteen_sales','canteen_sales_detail','canteen_stock','canteen_stock_detail','canteen_supplier','l_user'];
    if ($p->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_COLUMN) !== $tables) throw new RuntimeException('Expected exactly 13 Doyel tables');
    if (isset($opt['preflight'])) {
        echo 'PHP=' . PHP_VERSION . ' SAPI=' . PHP_SAPI . " extensions/Argon2id=PASS\n";
        echo json_encode($p->query('SELECT VERSION() AS version, @@port AS port, @@datadir AS datadir, @@sql_mode AS sql_mode, @@character_set_server AS charset, CURRENT_USER() AS account')->fetch(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        foreach ($p->query('SHOW GRANTS') as $r) echo implode('', $r) . "\n";
    } elseif (isset($opt['md5'])) {
        $ids = $p->query("SELECT user_id FROM l_user WHERE password REGEXP '^[0-9a-fA-F]{32}$' ORDER BY user_id")->fetchAll(PDO::FETCH_COLUMN);
        echo 'Remaining MD5 user IDs (' . count($ids) . '): ' . implode(',', $ids) . "\n";
    } else {
    $targets = json_decode('{"canteen_collection.amount":[14,2],"canteen_expenses.amount":[14,2],"canteen_item.PurPrice":[14,3],"canteen_item.SalesPrice":[14,3],"canteen_item.Qnty":[12,3],"canteen_purchase.Amount":[14,2],"canteen_purchase.Discount":[14,2],"canteen_purchase.Total":[14,2],"canteen_purchase_detail.Qnty":[12,3],"canteen_purchase_detail.QT":[12,3],"canteen_purchase_detail.Rate":[14,3],"canteen_purchase_detail.Amt":[14,2],"canteen_sales.Amount":[14,2],"canteen_sales.Discount":[14,2],"canteen_sales.Total":[14,2],"canteen_sales_detail.QT":[12,3],"canteen_sales_detail.Qnty":[12,3],"canteen_sales_detail.PurAmt":[14,2],"canteen_sales_detail.Rate":[14,3],"canteen_sales_detail.Amt":[14,2],"canteen_stock_detail.PurPrice":[14,3],"canteen_stock_detail.Qnty":[12,3]}', true, 512, JSON_THROW_ON_ERROR);
        $normalized = isset($opt['normalized-snapshot']) || isset($opt['normalized-compare']);
        $state = [];
        foreach ($tables as $table) {
            $fields = $p->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            $quote = static fn($v) => '`' . str_replace('`', '``', $v) . '`';
            $select = [];
            foreach ($fields as $field) {
                $key = $table . '.' . $field; $qf = $quote($field);
                if ($normalized && isset($targets[$key])) {
                    [$precision, $scale] = $targets[$key];
                    $value = "CAST(CAST($qf AS CHAR) AS DECIMAL(40,12))";
                    $limit = '1' . str_repeat('0', $precision - $scale);
                    if ($p->query("SELECT COUNT(*) FROM `$table` WHERE ABS($value)>=$limit OR ABS($value-ROUND($value,$scale))>0.000001")->fetchColumn() !== '0') throw new RuntimeException('Unsupported numeric precision/range: ' . $key);
                    $select[] = "CAST($value AS DECIMAL($precision,$scale)) AS $qf";
                } else $select[] = $qf;
            }
            $q = $p->query('SELECT ' . implode(',', $select) . " FROM `$table` ORDER BY " . implode(',', array_map($quote, $fields)));
            $hash = hash_init('sha256'); $count = 0;
            while ($row = $q->fetch()) { hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR) . "\n"); $count++; }
            $q->closeCursor();
            $state[$table] = ['count'=>$count, 'sha256'=>hash_final($hash)];
            echo "$table rows=$count\n";
        }
        $path = $opt['snapshot'] ?? $opt['compare'] ?? $opt['normalized-snapshot'] ?? $opt['normalized-compare']; private_path($path);
        if (isset($opt['snapshot']) || isset($opt['normalized-snapshot'])) {
            umask(0077); $f = fopen($path, 'x'); if (!$f) throw new RuntimeException('Snapshot must be new');
            $json = json_encode($state, JSON_THROW_ON_ERROR);
            if (fwrite($f, $json) !== strlen($json)) throw new RuntimeException('Incomplete snapshot');
            fclose($f); echo "PASS: private snapshot saved\n";
        } else {
            if ($state !== json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)) throw new RuntimeException('Table count/row bytes differ');
            echo "PASS: all 13 table counts and selected row bytes match\n";
        }
    }
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
