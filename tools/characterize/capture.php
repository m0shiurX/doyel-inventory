<?php
// Fixture and profiling tools must never run through the public document root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/common.php';
require __DIR__ . '/extract.php';
try {
    if ($argc !== 3) throw new RuntimeException('Usage: capture.sh <base-url> <out-dir>');
    [$script, $base, $out] = $argv;
    $base = rtrim($base, '/');
    if (!preg_match('~^http://(?:127\.0\.0\.1|localhost):[0-9]+$~D', $base)) throw new RuntimeException('Capture supports local HTTP fixture servers only');
    $fixtures = realpath(getenv('DOYEL_FIXTURES') ?: getenv('HOME') . '/.doyel-fixtures');
    if ($fixtures === false) throw new RuntimeException('Missing external fixture directory');
    if (file_exists($out)) {
        if (!is_dir($out) || count(array_diff(scandir($out), ['.', '..'])) > 0) throw new RuntimeException('Output must be new or empty; do not mix capture runs');
    } elseif (!mkdir($out, 0700, true)) throw new RuntimeException('Cannot create output directory');
    $out = realpath($out);
    $repo = realpath(__DIR__ . '/../..');
    if (str_starts_with($out . '/', $repo . '/') || !str_starts_with($out . '/', $fixtures . '/')) throw new RuntimeException('Capture output must be within external DOYEL_FIXTURES');
    umask(0077);
    $variables = characterization_variables();
    $cookies = $out . '/_cookies.private';
    $errors = []; $statuses = []; $manifest = []; $count = 0;
    $record = static function (string $label, array $request, array $response) use (&$errors, &$statuses, &$manifest, &$count, $out): void {
        $path = ltrim(rawurldecode(parse_url($request['path'], PHP_URL_PATH)), '/');
        $statuses[] = "$label " . $response['status'];
        foreach (characterization_errors($response['body']) as $error) $errors[] = "$label $error";
        $manifest[] = $label . ' ' . $request['method'] . ' ' . $request['path'] . ($request['body'] !== '' ? ' ' . $request['body'] : '');
        file_put_contents($out . '/' . $label . '.txt', characterization_extract($response['body'], $path));
        $count++;
        if ($response['status'] >= 400) throw new RuntimeException("HTTP failure $label: " . $response['status']);
    };
    $requests = characterization_requests();
    // Default is the whole manifest. Phase 2 can explicitly capture an owned
    // subset while other pages are still unported; this never silently skips one.
    $scope = getenv('DOYEL_CAPTURE_LABELS') ?: '';
    $selected = $scope === '' ? array_keys($requests) : explode(',', $scope);
    if (array_diff($selected, array_keys($requests)) || count($selected) !== count(array_unique($selected))) throw new RuntimeException('Unknown or duplicate requested capture label');
    $wanted = static fn(string $label): bool => in_array($label, $selected, true);
    $fixtureJar = getenv('DOYEL_CAPTURE_COOKIE_JAR') ?: '';
    if ($fixtureJar !== '') {
        // Intermediate S2.1 only: a CLI-created private fixture session permits
        // owned-page checks before S2.2 ports login. Never an app auth endpoint.
        $fixtureJar = realpath($fixtureJar);
        if (PHP_SAPI !== 'cli' || !$fixtureJar || !is_file($fixtureJar)
            || !str_starts_with($fixtureJar, $fixtures . '/')
            || !preg_match('~^http://(?:127\.0\.0\.1|localhost):81[0-9]{2}$~D', $base)
            || $scope === '' || $wanted('login')) throw new RuntimeException('Private fixture-cookie mode requires explicit modern owned labels excluding login');
        if (!copy($fixtureJar, $cookies)) throw new RuntimeException('Cannot copy private fixture cookie jar');
    } else {
        $request = characterization_resolve($requests['login'], $variables);
        $response = characterization_http($base, $request, $cookies);
        if ($wanted('login')) $record('login', $request, $response);
        $login = characterization_http($base, ['label' => 'authenticate', 'method' => 'POST', 'path' => '/login.php', 'body' => http_build_query(['username' => getenv('DOYEL_TEST_USER') ?: 'rayhan', 'password' => getenv('DOYEL_TEST_PASS') ?: 'admin-test'])], $cookies);
        if ($login['status'] !== 302) throw new RuntimeException('Fixture authentication did not redirect; check fixture credentials');
    }
    foreach ($requests as $label => $request) {
        if (!$wanted($label) || $label === 'login' || $label === 'logout' || str_starts_with($label, 'report-') && $label !== 'report-controls') continue;
        $request = characterization_resolve($request, $variables);
        $record($label, $request, characterization_http($base, $request, $cookies));
    }
    $hasReports = (bool)array_filter($selected, static fn($label) => str_starts_with($label, 'report-') && $label !== 'report-controls');
    foreach ($hasReports ? ['full' => ['01-01-2016', '25-10-2026'], 'month' => ['01-09-2026', '30-09-2026']] : [] as $range => [$start, $end]) {
        $set = ['label' => 'range-' . $range, 'method' => 'POST', 'path' => '/Report.php', 'body' => http_build_query(['start_date' => $start, 'end_date' => $end, 'start_time' => '00:00:00', 'end_time' => '23:59:59'])];
        $response = characterization_http($base, $set, $cookies);
        if ($response['status'] !== 200) throw new RuntimeException('Failed to set report range');
        foreach ($requests as $label => $request) {
            if (!$wanted($label) || !str_starts_with($label, 'report-') || $label === 'report-controls') continue;
            $request = characterization_resolve($request, $variables);
            $record($label . '.' . $range, $request, characterization_http($base, $request, $cookies));
        }
    }
    $request = $requests['logout'];
    $response = characterization_http($base, $request, $cookies);
    if ($wanted('logout')) $record('logout', $request, $response);
    sort($statuses);
    file_put_contents($out . '/_status.txt', implode("\n", $statuses) . "\n");
    file_put_contents($out . '/_errors.txt', $errors ? implode("\n", $errors) . "\n" : '');
    file_put_contents($out . '/_requests.private', implode("\n", $manifest) . "\n");
    file_put_contents($out . '/_complete.json', json_encode(['labels' => $count, 'manifest_sha256' => hash_file('sha256', __DIR__ . '/urls.txt'), 'ranges' => $hasReports ? ['full', 'month'] : [], 'scope' => $scope === '' ? 'all' : $selected], JSON_PRETTY_PRINT) . "\n");
    unlink($cookies);
    printf("Captured %d labels; %d PHP diagnostics. Private output: %s\n", $count, count($errors), $out);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
