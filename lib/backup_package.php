<?php
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
/** Data-only restore package. No uploaded content is ever interpreted as SQL. */
require_once __DIR__ . '/app_version.php';
require_once __DIR__ . '/backup_schema.php';

function doyel_backup_data_encode(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function doyel_backup_package_limits(array $overrides = []): array
{
    $defaults = ['compressed_bytes'=>134217728, 'uncompressed_bytes'=>1073741824, 'line_bytes'=>8388608, 'rows'=>10000000];
    foreach ($overrides as $key=>$value) {
        if (!isset($defaults[$key]) || !is_int($value) || $value < 1) throw new InvalidArgumentException('Invalid package limit');
        $defaults[$key] = $value;
    }
    return $defaults;
}

function doyel_backup_package_keys(array $record, array $keys): void
{
    if (array_keys($record) !== $keys) throw new RuntimeException('Unsupported package structure');
}

/** Same-snapshot business evidence; all decimal sums remain database strings. */
function doyel_backup_package_inventory(PDO $pdo): array
{
    $financial = [];
    $targets = ['canteen_collection'=>['amount'], 'canteen_expenses'=>['amount'], 'canteen_purchase'=>['Amount','Discount','Total'], 'canteen_sales'=>['Amount','Discount','Total'], 'canteen_purchase_detail'=>['Qnty','Amt'], 'canteen_sales_detail'=>['Qnty','Amt','PurAmt'], 'canteen_stock_detail'=>['Qnty']];
    foreach ($targets as $table=>$columns) foreach ($columns as $column) {
        $financial[$table . '.' . $column] = (string)$pdo->query("SELECT CAST(COALESCE(SUM(`$column`),0) AS CHAR) FROM `$table`")->fetchColumn();
    }
    $dates = [];
    foreach (doyel_schema_tables() as $table=>$definition) {
        if (in_array('Date', $definition['columns'], true)) {
            $dates[$table] = $pdo->query("SELECT MIN(`Date`) AS first, MAX(`Date`) AS last FROM `$table`")->fetch(PDO::FETCH_ASSOC);
        }
    }
    $roles = $pdo->query('SELECT `userAtho`, COUNT(*) AS count FROM `l_user` GROUP BY `userAtho` ORDER BY `userAtho` LIMIT 1025')->fetchAll(PDO::FETCH_ASSOC);
    if (count($roles) > 1024) throw new RuntimeException('Unsupported role inventory size');
    $buffered = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $duesHash = hash_init('sha256'); $duesCount = 0; $cursor = null;
    try {
        $cursor = $pdo->query('SELECT c.MemberID, CAST(COALESCE(s.due,0)-COALESCE(p.paid,0) AS CHAR) AS balance FROM canteen_customar c LEFT JOIN (SELECT CustomerName,SUM(Total) AS due FROM canteen_sales GROUP BY CustomerName) s ON s.CustomerName=c.Nickname LEFT JOIN (SELECT Description,SUM(amount) AS paid FROM canteen_collection GROUP BY Description) p ON p.Description=c.Nickname ORDER BY c.MemberID');
        while (($balance = $cursor->fetch(PDO::FETCH_ASSOC)) !== false) { hash_update($duesHash,doyel_backup_data_encode($balance)."\n"); $duesCount++; }
    } finally { if ($cursor !== null) $cursor->closeCursor(); $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered); }
    $dues = ['count'=>$duesCount,'sha256'=>hash_final($duesHash)];
    return ['financial'=>$financial, 'dates'=>$dates, 'roles'=>$roles, 'customer_balances'=>$dues];
}

/** Writes to a new path or an owner-supplied writable stream; caller owns stream lifecycle. */
function doyel_backup_package_write(PDO $pdo, mixed $destination, array $limits = []): array
{
    $limits = doyel_backup_package_limits($limits);
    $owned = is_string($destination);
    $output = $owned ? fopen($destination, 'xb') : $destination;
    if (!is_resource($output)) throw new RuntimeException('Cannot create package');
    if ($owned) chmod($destination, 0600);
    $statement = null;
    $buffered = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    $stringify = $pdo->getAttribute(PDO::ATTR_STRINGIFY_FETCHES);
    $compressed = 0; $uncompressed = 0;
    $deflate = deflate_init(ZLIB_ENCODING_GZIP, ['level'=>6]);
    if ($deflate === false) throw new RuntimeException('Compression unavailable');
    $emit = static function (array $record) use ($output, $deflate, &$compressed, &$uncompressed, $limits): string {
        $line = doyel_backup_data_encode($record) . "\n";
        $uncompressed += strlen($line);
        if (strlen($line) > $limits['line_bytes'] || $uncompressed > $limits['uncompressed_bytes']) throw new RuntimeException('Package exceeds size limit');
        $data = deflate_add($deflate, $line, ZLIB_NO_FLUSH);
        if ($data === false) throw new RuntimeException('Compression failed');
        $compressed += strlen($data);
        if ($compressed > $limits['compressed_bytes']) throw new RuntimeException('Package exceeds compressed limit');
        doyel_backup_package_write_bytes($output, $data);
        return $line;
    };
    try {
        if ($pdo->inTransaction()) throw new RuntimeException('Package requires its own snapshot');
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        $pdo->exec("SET SESSION time_zone = '+00:00'");
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $snapshot = gmdate('Y-m-d\TH:i:s\Z');
        $schema = doyel_schema_tables();
        foreach ($schema as $table=>$definition) $pdo->query("SELECT * FROM `$table` LIMIT 0")->closeCursor();
        doyel_schema_assert($pdo);
        $tables = [];
        foreach ($schema as $table=>$definition) $tables[$table] = ['columns'=>$definition['columns'], 'count'=>(string)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()];
        $status = $pdo->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($status as $entry) {
            $next = $entry['Auto_increment'];
            if ($next !== null && (!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$next) || (int)$next > 2147483647)) throw new RuntimeException('Unsupported next identifier');
            $tables[$entry['Name']]['next_id'] = $next === null ? null : (string)$next;
        }
        $inventory = doyel_backup_package_inventory($pdo);
        $header = ['kind'=>'header','format'=>'doyel-data','format_version'=>1,'schema_version'=>1,'app'=>doyel_app_version(),'snapshot_utc'=>$snapshot,'timezone'=>'+00:00','schema_fingerprint'=>doyel_schema_expected_fingerprint(),'tables'=>$tables];
        $hash = hash_init('sha256'); hash_update($hash, $emit($header));
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $actual = []; $total = 0;
        foreach ($schema as $table=>$definition) {
            $tableHash = hash_init('sha256'); $count = 0;
            $order = implode(',', array_map(static fn($column)=>'`'.$column.'`', (array)$definition['primary_key']));
            if ($order === '') throw new RuntimeException('Package table requires primary key');
            $statement = $pdo->query("SELECT * FROM `$table` ORDER BY $order");
            while (($values = $statement->fetch(PDO::FETCH_NUM)) !== false) {
                if (++$total > $limits['rows']) throw new RuntimeException('Package exceeds row limit');
                foreach ($values as $value) if ($value !== null && !is_string($value)) throw new RuntimeException('Non-string database value');
                $line = $emit(['kind'=>'row','table'=>$table,'values'=>$values]);
                hash_update($hash,$line); hash_update($tableHash,$line); $count++;
            }
            $statement->closeCursor(); $statement = null;
            if ((string)$count !== $tables[$table]['count']) throw new RuntimeException('Snapshot row count mismatch');
            $actual[$table] = ['count'=>$count,'sha256'=>hash_final($tableHash)];
        }
        $footer = ['kind'=>'footer','tables'=>$actual,'payload_sha256'=>hash_final($hash),'inventory'=>$inventory];
        $emit($footer);
        $tail = deflate_add($deflate, '', ZLIB_FINISH);
        if ($tail === false || $compressed + strlen($tail) > $limits['compressed_bytes']) throw new RuntimeException('Package finalization failed');
        doyel_backup_package_write_bytes($output,$tail); $compressed += strlen($tail);
        if (!fflush($output)) throw new RuntimeException('Package flush failed');
        $pdo->commit();
        return ['header'=>$header,'footer'=>$footer,'compressed_bytes'=>$compressed,'uncompressed_bytes'=>$uncompressed];
    } catch (Throwable $error) {
        if ($statement !== null) $statement->closeCursor();
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    } finally {
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,$stringify);
        if ($owned) fclose($output);
    }
}

function doyel_backup_package_write_bytes(mixed $output, string $bytes): void
{
    $offset = 0;
    while ($offset < strlen($bytes)) {
        $written = fwrite($output, substr($bytes,$offset));
        if ($written === false || $written === 0) throw new RuntimeException('Package write failed');
        $offset += $written;
    }
}

/** Callback receives untrusted values before final integrity verification; use only a disposable candidate. */
function doyel_backup_package_read(string $path, ?callable $row = null, array $limits = []): array
{
    $limits = doyel_backup_package_limits($limits);
    if (is_link($path) || !is_file($path)) throw new RuntimeException('Package must be a regular private file');
    $input = fopen($path,'rb');
    if ($input === false) throw new RuntimeException('Cannot read package');
    $inflate = inflate_init(ZLIB_ENCODING_GZIP);
    $fileHash = hash_init('sha256'); $payloadHash = hash_init('sha256');
    $header = null; $footer = null; $buffer = ''; $compressed = 0; $uncompressed = 0; $total = 0;
    $schema = doyel_schema_tables(); $names = array_keys($schema); $current = 0; $counts = []; $hashes = [];
    foreach ($names as $table) { $counts[$table] = 0; $hashes[$table] = hash_init('sha256'); }
    $consume = static function (string $line) use (&$header,&$footer,&$current,&$total,&$counts,&$hashes,$payloadHash,$schema,$names,$row,$limits): void {
        if ($footer !== null) throw new RuntimeException('Data follows package footer');
        try { $record = json_decode(substr($line,0,-1),true,32,JSON_THROW_ON_ERROR); }
        catch (JsonException $error) { throw new RuntimeException('Invalid package JSON'); }
        if (!is_array($record) || doyel_backup_data_encode($record)."\n" !== $line) throw new RuntimeException('Noncanonical package record');
        if ($header === null) {
            doyel_backup_package_keys($record,['kind','format','format_version','schema_version','app','snapshot_utc','timezone','schema_fingerprint','tables']);
            if ($record['kind'] !== 'header' || $record['format'] !== 'doyel-data' || $record['format_version'] !== 1 || $record['schema_version'] !== 1 || $record['schema_fingerprint'] !== doyel_schema_expected_fingerprint() || $record['timezone'] !== '+00:00' || !is_string($record['snapshot_utc']) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$record['snapshot_utc']) || !is_array($record['tables']) || array_keys($record['tables']) !== $names) throw new RuntimeException('Unsupported package header');
            if (!is_array($record['app']) || array_keys($record['app']) !== ['version','build','schema_version','backup_format'] || !is_string($record['app']['version']) || !is_string($record['app']['build']) || strlen($record['app']['version']) > 96 || strlen($record['app']['build']) > 96 || ($record['app']['schema_version'] ?? null) !== 1 || ($record['app']['backup_format'] ?? null) !== 1) throw new RuntimeException('Unsupported source application');
            foreach ($schema as $table=>$definition) {
                if (!is_array($record['tables'][$table])) throw new RuntimeException('Invalid table inventory');
                doyel_backup_package_keys($record['tables'][$table],['columns','count','next_id']);
                if ($record['tables'][$table]['columns'] !== $definition['columns'] || !is_string($record['tables'][$table]['count']) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D',$record['tables'][$table]['count'])) throw new RuntimeException('Unsupported table inventory');
                $next = $record['tables'][$table]['next_id'];
                if ($next !== null && (!is_string($next) || !preg_match('/^[1-9][0-9]{0,9}$/D',$next) || (int)$next > 2147483647)) throw new RuntimeException('Invalid next identifier');
            }
            $header = $record; hash_update($payloadHash,$line); return;
        }
        if (($record['kind'] ?? null) === 'row') {
            doyel_backup_package_keys($record,['kind','table','values']);
            $table = $record['table'];
            if (!is_string($table) || !isset($schema[$table]) || !is_array($record['values']) || !array_is_list($record['values']) || count($record['values']) !== count($schema[$table]['columns'])) throw new RuntimeException('Invalid package row');
            while ($current < count($names) && $names[$current] !== $table) $current++;
            if ($current === count($names)) throw new RuntimeException('Package table order mismatch');
            foreach ($record['values'] as $value) if ($value !== null && !is_string($value)) throw new RuntimeException('Package fields must be strings or null');
            if (++$total > $limits['rows'] || ++$counts[$table] > (int)$header['tables'][$table]['count']) throw new RuntimeException('Package row limit/count mismatch');
            hash_update($payloadHash,$line); hash_update($hashes[$table],$line);
            if ($row !== null) $row($table,$record['values']);
            return;
        }
        doyel_backup_package_keys($record,['kind','tables','payload_sha256','inventory']);
        if ($record['kind'] !== 'footer' || !is_array($record['tables']) || array_keys($record['tables']) !== $names || !is_array($record['inventory'])) throw new RuntimeException('Invalid package footer');
        doyel_backup_package_keys($record['inventory'],['financial','dates','roles','customer_balances']);
        foreach ($record['inventory'] as $part) if (!is_array($part)) throw new RuntimeException('Invalid business inventory');
        foreach ($names as $table) {
            $expected = ['count'=>$counts[$table],'sha256'=>hash_final($hashes[$table])];
            if ($record['tables'][$table] !== $expected || (string)$counts[$table] !== $header['tables'][$table]['count']) throw new RuntimeException('Package table integrity mismatch');
        }
        if (!is_string($record['payload_sha256']) || !hash_equals(hash_final($payloadHash),$record['payload_sha256'])) throw new RuntimeException('Package payload integrity mismatch');
        $footer = $record;
    };
    try {
        while (!feof($input)) {
            $chunk = fread($input,4096);
            if ($chunk === false) throw new RuntimeException('Package read failed');
            if ($chunk === '') break;
            $compressed += strlen($chunk);
            if ($compressed > $limits['compressed_bytes']) throw new RuntimeException('Compressed package limit exceeded');
            hash_update($fileHash,$chunk);
            $plain = @inflate_add($inflate,$chunk,ZLIB_SYNC_FLUSH);
            if ($plain === false) throw new RuntimeException('Invalid gzip package');
            $uncompressed += strlen($plain);
            if ($uncompressed > $limits['uncompressed_bytes']) throw new RuntimeException('Expanded package limit exceeded');
            $buffer .= $plain;
            while (($newline = strpos($buffer,"\n")) !== false) {
                if ($newline + 1 > $limits['line_bytes']) throw new RuntimeException('Package line limit exceeded');
                $line = substr($buffer,0,$newline+1); $buffer = substr($buffer,$newline+1); $consume($line);
            }
            if (strlen($buffer) > $limits['line_bytes']) throw new RuntimeException('Package line limit exceeded');
            if (inflate_get_status($inflate) === ZLIB_STREAM_END) {
                if (inflate_get_read_len($inflate) !== $compressed || fread($input,1) !== '') throw new RuntimeException('Trailing gzip bytes or members');
                break;
            }
        }
        if (inflate_get_status($inflate) !== ZLIB_STREAM_END || $buffer !== '' || $footer === null) throw new RuntimeException('Incomplete package');
        return ['header'=>$header,'footer'=>$footer,'compressed_bytes'=>$compressed,'uncompressed_bytes'=>$uncompressed,'sha256'=>hash_final($fileHash)];
    } finally { fclose($input); }
}
