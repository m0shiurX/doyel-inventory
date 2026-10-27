<?php
// Fixture and profiling tools must never run through the public document root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    if ($argc !== 3) throw new RuntimeException('Usage: compare.sh <golden-dir> <candidate-dir>');
    [$script, $golden, $candidate] = $argv;
    foreach ([$golden, $candidate] as $directory) {
        if (!is_dir($directory)) throw new RuntimeException('Missing capture directory');
        // Page captures require a completed manifest. Scenario outputs use the same
        // comparison API but have no page completion marker.
        if (is_file($directory . '/_status.txt') && !is_file($directory . '/_complete.json')) throw new RuntimeException('Incomplete page capture');
        if (is_file($directory . '/_complete.json')) {
            $complete = json_decode(file_get_contents($directory . '/_complete.json'), true, 512, JSON_THROW_ON_ERROR);
            $outputs = array_filter(glob($directory . '/*.txt'), static fn($path) => !str_starts_with(basename($path), '_'));
            $statusLines = file($directory . '/_status.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (count($outputs) !== ($complete['labels'] ?? -1) || count($statusLines) !== count($outputs)) throw new RuntimeException('Capture label count does not match completion manifest');
            $statusLabels = [];
            foreach ($statusLines as $line) {
                if (!preg_match('/^([a-z0-9.-]+) [1-5][0-9]{2}$/D', $line, $match)) throw new RuntimeException('Invalid HTTP status contract');
                $statusLabels[] = $match[1];
            }
            $fileLabels = array_map(static fn($path) => basename($path, '.txt'), $outputs);
            sort($statusLabels); sort($fileLabels);
            if ($statusLabels !== $fileLabels) throw new RuntimeException('HTTP status labels do not match output files');
        }
    }
    $labels = static function (string $directory): array {
        $files = glob($directory . '/*.txt');
        return array_values(array_filter(array_map('basename', $files), static fn($name) => $name !== '_errors.txt'));
    };
    $left = $labels($golden); $right = $labels($candidate);
    $all = array_unique(array_merge($left, $right)); sort($all);
    if (!$all) throw new RuntimeException('No output labels to compare');
    $same = 0; $differ = 0; $missing = 0;
    foreach ($all as $file) {
        if (!is_file($golden . '/' . $file) || !is_file($candidate . '/' . $file)) { echo "Missing: $file\n"; $missing++; continue; }
        if (hash_file('sha256', $golden . '/' . $file) === hash_file('sha256', $candidate . '/' . $file)) { $same++; continue; }
        $differ++;
        // Differences may contain private records. Caller must redirect output to
        // the external fixture directory; never paste the raw diff into reports.
        passthru('diff -u ' . escapeshellarg($golden . '/' . $file) . ' ' . escapeshellarg($candidate . '/' . $file));
    }
    foreach (['golden' => $golden, 'candidate' => $candidate] as $name => $directory) {
        $errors = is_file($directory . '/_errors.txt') ? count(file($directory . '/_errors.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
        printf("%s PHP diagnostics: %d (separate _errors.txt)\n", $name, $errors);
    }
    printf("%d same, %d differ, %d missing\n", $same, $differ, $missing);
    exit($differ || $missing ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n"); exit(1);
}
