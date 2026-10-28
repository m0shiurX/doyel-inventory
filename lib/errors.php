<?php
// Helpers are loaded by bootstrap; direct HTTP requests have no public response.
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function app_errors_default_directory(): string
{
    return sys_get_temp_dir() . '/doyel-private-' . substr(hash('sha256', dirname(__DIR__)), 0, 12) . '/logs';
}

function app_errors_private_directory(string $path): string
{
    // realpath resolves junctions/symlinks on the native host, including Windows.
    if ($path === '' || str_contains($path, "\0") || !preg_match('~^(?:/|[A-Za-z]:[\\\\/]|[\\\\]{2})~', $path)) {
        throw new RuntimeException('log_dir must be an absolute private directory');
    }
    $probe = $path;
    $tail = [];
    while (!file_exists($probe)) {
        $parent = dirname($probe);
        if ($parent === $probe) throw new RuntimeException('Cannot resolve log_dir');
        array_unshift($tail, basename($probe));
        $probe = $parent;
    }
    $resolved = realpath($probe);
    if ($resolved === false || !is_dir($resolved)) throw new RuntimeException('Cannot resolve log_dir');
    foreach ($tail as $part) {
        if ($part === '.' || $part === '..') throw new RuntimeException('log_dir must not contain traversal segments');
        $resolved .= DIRECTORY_SEPARATOR . $part;
    }
    app_errors_require_private_path($resolved);
    if (!is_dir($resolved) && !@mkdir($resolved, 0700, true) && !is_dir($resolved)) {
        throw new RuntimeException('Cannot create private log directory');
    }
    $resolved = realpath($resolved);
    if ($resolved === false) throw new RuntimeException('Cannot resolve private log directory');
    app_errors_require_private_path($resolved);
    if (!is_writable($resolved)) throw new RuntimeException('Private log directory is not writable');
    return $resolved;
}

function app_errors_require_private_path(string $path): void
{
    $roots = [realpath(dirname(__DIR__))];
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if (!empty($_SERVER['DOCUMENT_ROOT']) && $documentRoot !== false) $roots[] = $documentRoot;
    $path = str_replace('\\', '/', $path);
    if (PHP_OS_FAMILY === 'Windows') $path = strtolower($path);
    $path = rtrim($path, '/');
    foreach ($roots as $root) {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (PHP_OS_FAMILY === 'Windows') $root = strtolower($root);
        if ($path === $root || str_starts_with($path . '/', $root . '/')) {
            throw new RuntimeException('log_dir must be outside the public application directory');
        }
    }
}

function app_errors_configure(array $config): void
{
    $directory = app_errors_private_directory((string)$config['log_dir']);
    $GLOBALS['app_errors_directory'] = $directory;
    $GLOBALS['app_errors_development'] = $config['env'] === 'development';
    ini_set('display_errors', $GLOBALS['app_errors_development'] ? '1' : '0');
}

function app_errors_log(Throwable $error, string $reference): void
{
    static $logging = false;
    if ($logging) return;
    $logging = true;
    try {
        $directory = $GLOBALS['app_errors_directory'] ?? app_errors_private_directory(app_errors_default_directory());
        $user = $_SESSION['user_id'] ?? $_SESSION['Username'] ?? null;
        $entry = [
            'timestamp' => date(DATE_ATOM),
            'reference' => $reference,
            // Query strings and request bodies may contain passwords or CSRF tokens.
            'url' => explode('?', (string)($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? 'cli'), 2)[0],
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'user' => is_scalar($user) ? $user : null,
            'type' => get_class($error),
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ];
        $line = json_encode($entry, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";
        $file = $directory . '/app-' . date('Y-m') . '.log';
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            // Never leak exception details through a fallback server log.
            error_log('Doyel could not write private error log; reference ' . $reference);
        } else {
            @chmod($file, 0600);
        }
    } catch (Throwable $loggingError) {
        error_log('Doyel could not initialize private error log; reference ' . $reference);
    } finally {
        $logging = false;
    }
}

// Also used by download endpoints that must handle a failed stream themselves.
function app_errors_report(Throwable $error): string
{
    try {
        $reference = bin2hex(random_bytes(6));
    } catch (Throwable $randomError) {
        $reference = substr(hash('sha256', uniqid('', true)), 0, 12);
    }
    app_errors_log($error, $reference);
    return $reference;
}

function app_errors_handle(Throwable $error): never
{
    $GLOBALS['app_errors_handled'] = true;
    $reference = app_errors_report($error);
    $development = $GLOBALS['app_errors_development'] ?? false;
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, ($development ? (string)$error : 'Something went wrong. Reference: ' . $reference) . "\n");
        exit(1);
    }
    // Downloads may already have streamed SQL; an HTML suffix would corrupt them.
    if (headers_sent()) exit(1);
    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) break;
    }
    header_remove();
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    $details = $development ? (string)$error : null;
    require dirname(__DIR__) . '/errors/500.php';
    exit(1);
}

function app_errors_register(): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');
    ini_set('zend.exception_ignore_args', '1');
    set_exception_handler('app_errors_handle');
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) return true;
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    register_shutdown_function(static function (): void {
        $last = error_get_last();
        if (!($GLOBALS['app_errors_handled'] ?? false) && $last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            app_errors_handle(new ErrorException($last['message'], 0, $last['type'], $last['file'], $last['line']));
        }
    });
    if (PHP_SAPI !== 'cli' && ob_get_level() === 0) ob_start();
}
