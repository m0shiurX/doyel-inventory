<?php
// Helpers shared by read-only capture and the later perf runner.
function characterization_requests(): array
{
    $requests = [];
    foreach (file(__DIR__ . '/urls.txt', FILE_IGNORE_NEW_LINES) as $line) {
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) continue;
        $parts = preg_split('/\s+/', trim($line), 4);
        if (count($parts) < 3 || !preg_match('/^[a-z0-9-]+$/', $parts[0]) || !in_array($parts[1], ['GET', 'POST'], true)) {
            throw new RuntimeException('Invalid request manifest line');
        }
        [$label, $method, $path] = $parts;
        if (isset($requests[$label])) throw new RuntimeException('Duplicate request label');
        $requests[$label] = ['label' => $label, 'method' => $method, 'path' => $path, 'body' => $parts[3] ?? ''];
    }
    if (!$requests) throw new RuntimeException('Empty request manifest');
    return $requests;
}
function characterization_variables(): array
{
    // DOYEL_DB_* points at the same database as the server under test. Resolver
    // SELECTs only; private name-based parameters never appear in tracked files.
    $host = getenv('DOYEL_DB_HOST') ?: '127.0.0.1';
    $port = getenv('DOYEL_DB_PORT') ?: '33057';
    $name = getenv('DOYEL_DB_NAME') ?: 'software';
    if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !in_array($port, ['33057', '33084'], true)
        || !preg_match('/^(software|doyel_[A-Za-z0-9_]+)$/D', $name)) {
        throw new RuntimeException('Resolver only supports isolated local fixture databases');
    }
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8", getenv('DOYEL_DB_USER') ?: 'root', getenv('DOYEL_DB_PASS') ?: 'doyel-local', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $scalar = static fn(string $sql) => $pdo->query($sql)->fetchColumn();
    $customer = $pdo->query('SELECT c.MemberID,c.Nickname FROM canteen_customar c WHERE EXISTS (SELECT 1 FROM canteen_collection p WHERE p.Description=c.Nickname) AND EXISTS (SELECT 1 FROM canteen_sales s WHERE s.CustomerName=c.Nickname) ORDER BY c.MemberID LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $item = $pdo->query('SELECT i.ItemID,i.ItemName FROM canteen_item i WHERE EXISTS (SELECT 1 FROM canteen_sales_detail d WHERE d.ItemID=i.ItemID) ORDER BY i.ItemID LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $values = [
        'sale_unposted' => $scalar('SELECT SalesID FROM canteen_sales WHERE Post=0 ORDER BY SalesID DESC LIMIT 1'),
        'purchase_id' => $scalar('SELECT p.BillNo FROM canteen_purchase p WHERE Post=1 AND EXISTS (SELECT 1 FROM canteen_purchase_detail d WHERE d.BillNo=p.BillNo) ORDER BY p.BillNo DESC LIMIT 1'),
        'snapshot_id' => $scalar('SELECT SlNo FROM canteen_stock ORDER BY SlNo DESC LIMIT 1'),
        'customer_id' => $customer['MemberID'] ?? false, 'customer_name' => $customer['Nickname'] ?? false,
        'item_id' => $item['ItemID'] ?? false, 'item_name' => $item['ItemName'] ?? false,
    ];
    foreach ($values as $key => $value) {
        if ($value === false || $value === null || $value === '') throw new RuntimeException("Fixture lookup missing: $key");
    }
    return $values;
}
function characterization_resolve(array $request, array $values): array
{
    foreach (['path', 'body'] as $part) {
        $request[$part] = preg_replace_callback('/\{\{([a-z_]+)\}\}/', static function ($m) use ($values) {
            if (!array_key_exists($m[1], $values)) throw new RuntimeException('Unknown fixture placeholder');
            return rawurlencode((string)$values[$m[1]]);
        }, $request[$part]);
    }
    return $request;
}
function characterization_http(string $base, array $request, string $cookies): array
{
    $curl = curl_init($base . $request['path']);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookies, CURLOPT_COOKIEJAR => $cookies,
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 180, CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
        CURLOPT_USERAGENT => 'Doyel characterization/local-fixture']);
    if ($request['method'] === 'POST') curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $request['body']]);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException('HTTP request failed for ' . $request['label'] . ': ' . curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => $body];
}
function characterization_errors(string $html): array
{
    preg_match_all('/(?:<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(?:<\/b>)?:\s*(.*?)(?:<br\s*\/?>|\n|$)/is', $html, $matches, PREG_SET_ORDER);
    return array_map(static fn($m) => preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1] . ': ' . $m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $matches);
}
