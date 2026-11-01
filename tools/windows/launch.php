<?php
// php-win.exe keeps routine use free of console windows and elevation prompts.
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Windows') exit(1);
$url = 'http://127.0.0.1:18080/login.php';
$ready = false;
$context = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
for ($attempt = 0; $attempt < 30; $attempt++) {
    $socket = @stream_socket_client('tcp://127.0.0.1:13306', $errno, $error, 1);
    if ($socket) {
        fclose($socket);
        $body = @file_get_contents($url, false, $context);
        if ($body !== false && preg_match('~^HTTP/\S+ 200\b~', $http_response_header[0] ?? '') && !str_contains($body, '<?php')) {
            $ready = true; break;
        }
    }
    usleep(500000);
}
$powershell = getenv('SystemRoot') . '/System32/WindowsPowerShell/v1.0/powershell.exe';
$script = $ready
    ? "Start-Process 'http://127.0.0.1:18080/login.php'"
    : "Add-Type -AssemblyName PresentationFramework; [System.Windows.MessageBox]::Show('Doyel did not become ready. Wait a moment and try again. If this continues, contact your support person to check DoyelWeb and DoyelDB.', 'Doyel') | Out-Null";
$process = proc_open([$powershell, '-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden', '-Command', $script], [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $pipes, null, null, ['bypass_shell' => true, 'create_no_window' => true]);
if (is_resource($process)) exit(proc_close($process));
exit(1);
