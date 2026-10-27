<?php
// Read-only local fixture inspection. Never emit record contents or hashes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    $options = getopt('', ['expect:', 'snapshot:', 'compare:']);
    $name = getenv('DOYEL_DB_NAME') ?: '';
    if ((getenv('DOYEL_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1'
        || (getenv('DOYEL_DB_PORT') ?: '33084') !== '33084'
        || !preg_match('/^doyel_[A-Za-z0-9_]+$/D', $name)) {
        throw new RuntimeException('Only isolated doyel_* fixtures on 127.0.0.1:33084 are supported');
    }
    $expect = $options['expect'] ?? 'original';
    if (!in_array($expect, ['original', 'converted'], true)) throw new RuntimeException('Expected original or converted');
    $pdo = new PDO("mysql:host=127.0.0.1;port=33084;dbname=$name;charset=utf8mb4", getenv('DOYEL_DB_USER') ?: 'root', getenv('DOYEL_DB_PASS') ?: 'doyel-local', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_STRINGIFY_FETCHES => true]);
    $quote = static fn($v) => '`' . str_replace('`', '``', $v) . '`';
    $tables = ['canteen_ac_head','canteen_collection','canteen_customar','canteen_expenses','canteen_item','canteen_purchase','canteen_purchase_detail','canteen_sales','canteen_sales_detail','canteen_stock','canteen_stock_detail','canteen_supplier','l_user'];
    $actual = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
    if ($actual !== $tables) throw new RuntimeException('Expected exactly 13 Doyel fixture tables');
    $columns = $pdo->query('SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,CHARACTER_SET_NAME,COLLATION_NAME,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION')->fetchAll();
    $columnMap = []; $bad = 0; $textCount = 0; $nonAscii = 0;
    foreach ($columns as $column) {
        $table = $column['TABLE_NAME']; $field = $column['COLUMN_NAME'];
        $columnMap[$table][$field] = $column;
        if ($column['CHARACTER_SET_NAME'] === null) continue;
        $textCount++;
        if (in_array($table . '.' . $field, ['canteen_customar.address','canteen_customar.MemberName','canteen_sales.Note','l_user.FullName'], true)
            && $column['DATA_TYPE'] !== 'text') throw new RuntimeException('Original TEXT capacity changed');
        $where = 'HEX(' . $quote($field) . ") REGEXP '^(..)*[89ABCDEF]'";
        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . $quote($table) . ' WHERE ' . $where)->fetchColumn();
        echo "$table.$field non_ascii=$count\n";
        if ($count) {
            $nonAscii += $count;
            $primary = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX");
            $primary->execute([$table]); $keys = $primary->fetchAll(PDO::FETCH_COLUMN);
            if ($keys) {
                $ids = $pdo->query('SELECT ' . implode(',', array_map($quote, $keys)) . ' FROM ' . $quote($table) . ' WHERE ' . $where . ' ORDER BY ' . implode(',', array_map($quote, $keys)))->fetchAll(PDO::FETCH_NUM);
                echo 'IDs: ' . json_encode($ids, JSON_THROW_ON_ERROR) . "\n";
            }
        }
        $latin = in_array($table, ['canteen_expenses','canteen_stock','canteen_stock_detail'], true);
        $target = 'utf8mb4';
        $source = $column['CHARACTER_SET_NAME'];
        if (!in_array($source, ['latin1','utf8','utf8mb3','utf8mb4'], true)) throw new RuntimeException('Unsupported source charset');
        $invalid = $latin ? $count : (int)$pdo->query('SELECT COUNT(*) FROM ' . $quote($table) . ' WHERE BINARY ' . $quote($field) . ' <> BINARY CONVERT(CONVERT(' . $quote($field) . ' USING ' . $target . ') USING ' . $source . ')')->fetchColumn();
        $bad += $invalid;
        echo "$table.$field unproven_conversion=$invalid\n";
        $wanted = $expect === 'converted'  ? 'utf8mb4_unicode_ci' : ($latin ? 'latin1_swedish_ci' : 'utf8mb3_unicode_ci');
        if ($column['COLLATION_NAME'] !== $wanted) throw new RuntimeException("Unexpected collation at $table.$field");
    }
    if ($bad) throw new RuntimeException('Non-ASCII latin1 or unproven Unicode conversion; stop before DDL');
    $dbCollation = $pdo->query('SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE()')->fetchColumn();
    if ($dbCollation !== ($expect === 'converted' ? 'utf8mb4_unicode_ci' : 'utf8mb3_unicode_ci')) throw new RuntimeException('Unexpected database collation');
    foreach ($pdo->query('SELECT TABLE_NAME,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()') as $table) {
        $latin = in_array($table['TABLE_NAME'], ['canteen_expenses','canteen_stock','canteen_stock_detail'], true);
        $wanted = $expect === 'converted' ? 'utf8mb4_unicode_ci' : ($latin ? 'latin1_swedish_ci' : 'utf8mb3_unicode_ci');
        if ($table['TABLE_COLLATION'] !== $wanted) throw new RuntimeException('Unexpected table collation');
    }
    $indexes = []; $added = 0;
    foreach ($pdo->query('SELECT TABLE_NAME,INDEX_NAME,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX') as $index) {
        $column = $columnMap[$index['TABLE_NAME']][$index['COLUMN_NAME']];
        $key = $index['TABLE_NAME'] . '.' . $index['INDEX_NAME'];
        // Conservative: 4 bytes per character, 32 per nontext field (all actual numeric/date fields <= 8).
        $indexes[$key] = ($indexes[$key] ?? 0) + ($column['CHARACTER_MAXIMUM_LENGTH'] === null ? 32 : 4 * (int)($index['SUB_PART'] ?? $column['CHARACTER_MAXIMUM_LENGTH']));
    }
    foreach ($indexes as $key => $bytes) {
        if ($bytes >= 3072) throw new RuntimeException('Index exceeds conservative key budget');
        if (str_contains($key, '.idx_')) $added++;
        echo "$key upper_bound_bytes=$bytes\n";
    }
    if ($added !== 19) throw new RuntimeException('Expected all 19 migration 001 indexes');
    $snapshot = [];
    foreach ($tables as $table) {
        $context = hash_init('sha256'); $count = 0;
        // All columns/order included; text bytes and numeric formatting must remain identical.
        $query = $pdo->query('SELECT * FROM ' . $quote($table) . ' ORDER BY ' . implode(',', array_map($quote, array_keys($columnMap[$table]))));
        while ($row = $query->fetch(PDO::FETCH_NUM)) { hash_update($context, json_encode($row, JSON_THROW_ON_ERROR) . "\n"); $count++; }
        $snapshot[$table] = ['rows' => $count, 'digest' => hash_final($context)];
        echo "$table rows=$count\n";
    }
    foreach (['snapshot','compare'] as $option) {
        if (!isset($options[$option])) continue;
        $path = $options[$option];
        $parent = realpath(dirname($path));
        $repo = realpath(__DIR__ . '/../..');
        if (!$parent || is_link($path) || !str_starts_with($path, '/') || str_starts_with($parent . '/', $repo . '/')) throw new RuntimeException('Snapshot path must exist outside the repository');
        if ($option === 'snapshot') {
            if (file_exists($path)) throw new RuntimeException('Snapshot already exists');
            umask(0077);
            $file = fopen($path, 'x');
            if ($file === false) throw new RuntimeException('Cannot create private snapshot');
            fwrite($file, json_encode($snapshot, JSON_THROW_ON_ERROR)); fclose($file);
        } elseif (json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) !== $snapshot) {
            throw new RuntimeException('Data snapshot mismatch');
        } else echo "All 13 table counts and exact row bytes unchanged\n";
    }
    echo "PASS: 13 tables, $textCount text columns ($nonAscii non-ASCII rows, lossless roundtrips), 19 added indexes, max key upper bound=" . max($indexes) . " bytes; $expect schema\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error instanceof PDOException ? "Local fixture charset check failed; inspect local DB configuration\n" : $error->getMessage() . "\n");
    exit(1);
}
