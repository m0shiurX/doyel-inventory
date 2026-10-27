<?php
// Fixture and profiling tools must never run through the public document root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/characterize/common.php';

/** Observe the local DB without including the observer's SQL in page counts. */
final class PerfLog
{
    private bool $restored = false;
    private int $observer;
    private array $original;

    public function __construct(private PDO $pdo, private string $database)
    {
        $this->observer = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        $this->original = $pdo->query('SELECT @@GLOBAL.general_log AS enabled, @@GLOBAL.log_output AS output')->fetch(PDO::FETCH_ASSOC);
        // Never truncate the log: previous diagnostic data may be useful.
        try {
            $pdo->exec('SET GLOBAL general_log=OFF');
            $pdo->exec("SET GLOBAL log_output='TABLE'");
        } catch (Throwable $error) {
            $this->restore();
            throw $error;
        }
    }

    public function restore(): void
    {
        if ($this->restored) return;
        $this->pdo->exec('SET GLOBAL general_log=OFF');
        $this->pdo->exec('SET GLOBAL log_output=' . $this->pdo->quote($this->original['output']));
        $this->pdo->exec('SET GLOBAL general_log=' . ((int)$this->original['enabled'] ? 'ON' : 'OFF'));
        $this->restored = true;
    }

    public function measure(string $base, array $request, string $cookies, string $logFile): array
    {
        $this->pdo->exec('SET GLOBAL general_log=ON');
        $start = $this->pdo->query('SELECT CURRENT_TIMESTAMP(6)')->fetchColumn();
        try {
            $clock = hrtime(true);
            $response = characterization_http($base, $request, $cookies);
            $milliseconds = (hrtime(true) - $clock) / 1e6;
        } finally {
            $this->pdo->exec('SET GLOBAL general_log=OFF');
        }
        $statement = $this->pdo->prepare('SELECT event_time, thread_id, command_type, argument FROM mysql.general_log WHERE event_time >= ? AND thread_id <> ? ORDER BY event_time, thread_id');
        $statement->execute([$start, $this->observer]);
        $events = $statement->fetchAll(PDO::FETCH_ASSOC);
        $threads = [];
        foreach ($events as $event) {
            if (($event['command_type'] === 'Init DB' && $event['argument'] === $this->database)
                || ($event['command_type'] === 'Connect' && preg_match('/ on ' . preg_quote($this->database, '/') . '(?: using |$)/', $event['argument']))) {
                $threads[(string)$event['thread_id']] = true;
            }
        }
        if (count($threads) > 1) throw new RuntimeException('More than one app connection observed; stop concurrent fixture traffic before profiling');
        $appEvents = array_values(array_filter($events, static fn($event) => isset($threads[(string)$event['thread_id']])));
        file_put_contents($logFile, json_encode($appEvents, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
        $count = count(array_filter($appEvents, static fn($event) => in_array($event['command_type'], ['Query', 'Execute'], true)));
        // Zero-SQL pages (e.g. logout) legitimately have no database connection.
        // A query on an unrecognized thread is evidence that DB routing is wrong.
        foreach ($events as $event) {
            if (in_array($event['command_type'], ['Query', 'Execute'], true) && !isset($threads[(string)$event['thread_id']])) {
                throw new RuntimeException('SQL on an unexpected database connection observed; check server/database routing and exclusive access');
            }
        }
        if ($response['status'] >= 400) throw new RuntimeException('HTTP error for ' . $request['label'] . ': ' . $response['status']);
        if (preg_match('/(?:Fatal error|Parse error)/i', $response['body'])) throw new RuntimeException('Fatal PHP error in ' . $request['label']);
        return ['ms' => $milliseconds, 'queries' => $count, 'status' => $response['status'], 'diagnostics' => count(characterization_errors($response['body']))];
    }
}

function perf_login(string $base, string $cookies): void
{
    $response = characterization_http($base, ['label' => 'authenticate', 'method' => 'POST', 'path' => '/login.php', 'body' => http_build_query(['username' => getenv('DOYEL_TEST_USER') ?: 'rayhan', 'password' => getenv('DOYEL_TEST_PASS') ?: 'admin-test'])], $cookies);
    if ($response['status'] !== 302) throw new RuntimeException('Fixture login failed');
}

function perf_range(string $base, string $cookies): void
{
    $response = characterization_http($base, ['label' => 'report-range', 'method' => 'POST', 'path' => '/Report.php', 'body' => http_build_query(['start_date' => '01-01-2016', 'end_date' => '25-10-2026', 'start_time' => '00:00:00', 'end_time' => '23:59:59'])], $cookies);
    if ($response['status'] !== 200) throw new RuntimeException('Report range setup failed');
}

$logger = null;
$lock = null;
$exitCode = 0;
try {
    $arguments = array_slice($argv, 1);
    $snapshot = ($arguments[0] ?? '') === '--snapshot';
    if ($snapshot) array_shift($arguments);
    if (count($arguments) < 2 || count($arguments) > 3) throw new RuntimeException('Usage: tools/perf.sh [--snapshot] <base-url> <legacy|modern> [dbname]');
    [$base, $target] = $arguments;
    $base = rtrim($base, '/');
    $database = $arguments[2] ?? 'software';
    if (!preg_match('~^http://(?:127\.0\.0\.1|localhost):[0-9]+$~D', $base)
        || !in_array($target, ['legacy', 'modern'], true)
        || !preg_match('/^(software|doyel_[A-Za-z0-9_]+)$/D', $database)) throw new RuntimeException('Only local fixture servers and databases are supported');
    // Always select the local profile's fixed connection, not arbitrary DB env.
    putenv('DOYEL_DB_HOST=127.0.0.1');
    putenv('DOYEL_DB_PORT=' . ($target === 'legacy' ? '33057' : '33084'));
    putenv('DOYEL_DB_NAME=' . $database);
    putenv('DOYEL_DB_USER=root');
    putenv('DOYEL_DB_PASS=doyel-local');
    $fixtures = realpath(getenv('DOYEL_FIXTURES') ?: getenv('HOME') . '/.doyel-fixtures');
    if ($fixtures === false) throw new RuntimeException('External fixture directory is missing');
    $repo = realpath(__DIR__ . '/..');
    if (str_starts_with($fixtures . '/', $repo . '/')) throw new RuntimeException('Fixtures must remain outside the repository');
    umask(0077);
    $perfRoot = $fixtures . '/perf';
    if (!is_dir($perfRoot) && !mkdir($perfRoot, 0700, true)) throw new RuntimeException('Cannot create private perf directory');
    $perfRoot = realpath($perfRoot);
    if ($perfRoot === false || !str_starts_with($perfRoot . '/', $fixtures . '/') || str_starts_with($perfRoot . '/', $repo . '/')) throw new RuntimeException('Performance output must remain inside external fixtures');
    $lock = fopen($perfRoot . '/.' . $target . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another profiler owns this test database');
    $out = getenv('DOYEL_PERF_OUT') ?: $perfRoot . '/' . $target . '-' . ($snapshot ? 'snapshot-' : '') . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (file_exists($out) || !is_dir(dirname($out)) || realpath(dirname($out)) !== realpath($perfRoot)) throw new RuntimeException('Output must be a new direct child directory under DOYEL_FIXTURES/perf');
    if (!mkdir($out, 0700)) throw new RuntimeException('Cannot create private output directory');
    if ($snapshot) {
        $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['file', $out . '/reset.private', 'w'], 2 => ['file', $out . '/reset-errors.private', 'w']];
        $process = proc_open([__DIR__ . '/db-reset.sh', $target, $database], $descriptors, $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Snapshot fixture reset failed; inspect private reset logs');
    }
    $pdo = new PDO('mysql:host=127.0.0.1;port=' . ($target === 'legacy' ? '33057' : '33084') . ';dbname=' . $database . ';charset=utf8', 'root', 'doyel-local', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);
    $variables = characterization_variables();
    $requests = characterization_requests();
    $scope = getenv('DOYEL_PERF_LABELS') ?: '';
    $selected = $scope === '' ? array_keys($requests) : explode(',', $scope);
    if (array_diff($selected, array_keys($requests)) || count($selected) !== count(array_unique($selected))) throw new RuntimeException('Unknown or duplicate performance labels');
    if ($snapshot && $scope !== '') throw new RuntimeException('Snapshot mode does not accept read-only label selection');
    $cookies = $out . '/cookies.private';
    perf_login($base, $cookies);
    $server = $pdo->query('SELECT VERSION()')->fetchColumn();
    $initialSnapshots = $snapshot ? (int)$pdo->query('SELECT COUNT(*) FROM canteen_stock')->fetchColumn() : null;
    $logger = new PerfLog($pdo, $database);
    register_shutdown_function(static function () use (&$logger): void {
        if ($logger !== null) {
            try { $logger->restore(); } catch (Throwable $error) { fwrite(STDERR, "Could not restore local MySQL logger settings; inspect the test service\n"); }
        }
    });
    if (function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM] as $signal) pcntl_signal($signal, static fn() => throw new RuntimeException('Profiling interrupted'));
    }
    // A wrong HTTP port must not yield a false zero-query baseline or cause the
    // snapshot write to hit another local fixture. Prove the authenticated
    // server queries this observer's DB using a read-only setup request.
    $route = $logger->measure($base, ['label' => 'routing-check', 'method' => 'GET', 'path' => '/index.php', 'body' => ''], $cookies, $out . '/routing-check.sql.private.json');
    if ($route['status'] !== 200 || $route['queries'] < 1) throw new RuntimeException('HTTP server does not query the selected fixture database; profiling cancelled');
    $results = [];
    $work = $snapshot ? ['stock-save' => ['label' => 'stock-save', 'method' => 'GET', 'path' => '/SavePrestntStock.php', 'body' => '']] : array_intersect_key($requests, array_flip($selected));
    foreach ($work as $label => $request) {
        $samples = [];
        for ($sample = 1; $sample <= ($snapshot ? 1 : 5); $sample++) {
            // Report controls GET resets the range, and logout destroys cookies:
            // setup stays outside the logging/timing window for every sample.
            if ($label === 'logout' && $sample > 1) perf_login($base, $cookies);
            if (str_starts_with($label, 'report-') && $label !== 'report-controls') perf_range($base, $cookies);
            $samples[] = $logger->measure($base, characterization_resolve($request, $variables), $cookies, $out . '/' . $label . '-' . $sample . '.sql.private.json');
        }
        $times = array_column($samples, 'ms'); sort($times);
        $counts = array_column($samples, 'queries'); sort($counts);
        $results[$label] = ['median_ms' => $times[(int)floor(count($times) / 2)], 'queries' => $counts[(int)floor(count($counts) / 2)], 'queries_min' => min($counts), 'queries_max' => max($counts), 'samples' => $samples];
        printf("%s: %.3f ms; %d queries (%d samples)\n", $label, $results[$label]['median_ms'], $results[$label]['queries'], count($samples));
        file_put_contents($out . '/samples.private.json', json_encode($results, JSON_PRETTY_PRINT) . "\n");
    }
    $logger->restore();
    if ($snapshot && (int)$pdo->query('SELECT COUNT(*) FROM canteen_stock')->fetchColumn() !== $initialSnapshots + 1) throw new RuntimeException('Snapshot request did not create exactly one stock snapshot');
    if (isset($results['payment-list']) && $target === 'legacy' && $results['payment-list']['queries'] < 600) throw new RuntimeException('Legacy Payment count unexpectedly low: validate logger correlation');
    uasort($results, static fn($a, $b) => $b['median_ms'] <=> $a['median_ms']);
    $metadata = ['target' => $target, 'database' => $database, 'host' => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'), 'host_php' => PHP_VERSION, 'mysql' => $server, 'recorded_utc' => gmdate('c'), 'samples' => $snapshot ? 1 : 5, 'scope' => $snapshot ? 'snapshot' : ($scope === '' ? 'all' : $selected), 'manifest_sha256' => hash_file('sha256', __DIR__ . '/characterize/urls.txt')];
    file_put_contents($out . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT) . "\n");
    $table = "| Label | Median ms | Queries | Query range |\n|---|---:|---:|---:|\n";
    foreach ($results as $label => $result) $table .= sprintf("| %s | %.3f | %d | %d–%d |\n", $label, $result['median_ms'], $result['queries'], $result['queries_min'], $result['queries_max']);
    file_put_contents($out . '/summary.md', $table);
    file_put_contents($out . '/complete.json', json_encode(['labels' => count($results), 'snapshot' => $snapshot]) . "\n");
    unlink($cookies);
    echo $table . "Private performance output: $out\n";
} catch (Throwable $error) {
    // PDO messages can quote private SQL/values; keep exception details private.
    $message = $error instanceof PDOException ? 'Local database profiling failed; inspect configuration and test service logs' : $error->getMessage();
    fwrite(STDERR, $message . "\n");
    $exitCode = 1;
} finally {
    if ($logger !== null) {
        try { $logger->restore(); }
        catch (Throwable $error) { fwrite(STDERR, "Could not restore local MySQL logger settings; inspect the test service\n"); $exitCode = 1; }
    }
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
exit($exitCode);
