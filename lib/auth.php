<?php
function current_user(): ?string
{
    $user = $_SESSION['Username'] ?? null;
    return is_string($user) && $user !== '' ? $user : null;
}
function is_admin(): bool { return ($_SESSION['admin'] ?? 0) == 1; }
// Resolve relative to the actual entrypoint, so nested AJAX/report routes work
// even when the application itself lives below a URL subdirectory.
function app_relative_path(string $path): string
{
    $root = dirname(__DIR__);
    $entry = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $directory = str_replace('\\', '/', dirname($entry));
    $base = str_replace('\\', '/', $root);
    $relative = str_starts_with($directory . '/', $base . '/') ? trim(substr($directory, strlen($base)), '/') : '';
    $depth = $relative === '' ? 0 : count(explode('/', $relative));
    return str_repeat('../', $depth) . $path;
}
function require_login(): void
{
    if (current_user() === null) redirect(app_relative_path('login.php'));
}
function require_admin(): void
{
    require_login();
    if (!is_admin()) redirect(app_relative_path('index.php'));
}
function require_login_json(): void
{
    if (current_user() !== null) return;
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}
