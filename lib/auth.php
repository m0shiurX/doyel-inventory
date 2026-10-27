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

// Legacy login hashed the MySQL-escaped bytes, not the original password.
function legacy_escape(string $password): string
{
    return strtr($password, ["\\" => "\\\\", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "'" => "\\'", '"' => '\\"', "\x1a" => "\\Z"]);
}
function auth_password_matches(string $password, string $stored): bool
{
    if ($password === '') return false;
    if (str_starts_with($stored, '$2y$')) {
        // Never accept bcrypt's truncated-prefix equivalence as a full password.
        if (strlen($password) > 72 || str_contains($password, "\0")) return false;
        return password_verify($password, $stored);
    }
    if (str_starts_with($stored, '$argon2')) return password_verify($password, $stored);
    return preg_match('/^[a-f0-9]{32}$/iD', $stored) === 1 && hash_equals(strtolower($stored), md5(legacy_escape($password)));
}
class UnsupportedPasswordHash extends RuntimeException {}
function auth_password_algorithm(string $password): string|int
{
    if (str_contains($password, "\0") || strlen($password) > 72) {
        if (!defined('PASSWORD_ARGON2ID') || !in_array(PASSWORD_ARGON2ID, password_algos(), true)) {
            throw new UnsupportedPasswordHash('This server cannot securely update this password. Please contact the administrator.');
        }
        return PASSWORD_ARGON2ID;
    }
    return PASSWORD_DEFAULT;
}
function auth_hash_password(string $password): string
{
    return password_hash($password, auth_password_algorithm($password));
}
function authenticate_user(string $username, string $password): ?object
{
    if ($username === '' || $password === '') return null;
    foreach (db_all('SELECT user_id, UserName, password, userAtho FROM l_user WHERE UserName = ? ORDER BY user_id', [$username]) as $user) {
        if (!auth_password_matches($password, $user->password)) continue;
        if (password_needs_rehash($user->password, auth_password_algorithm($password))) {
            // Match the verified hash too, so a concurrent password reset is never overwritten.
            $changed = db_exec('UPDATE l_user SET password = ? WHERE user_id = ? AND password = ?', [auth_hash_password($password), $user->user_id, $user->password]);
            if ($changed === 0) {
                $fresh = db_one('SELECT user_id, UserName, password, userAtho FROM l_user WHERE user_id = ?', [$user->user_id]);
                // Another login may have upgraded the hash, or a reset changed it.
                return $fresh !== null && auth_password_matches($password, $fresh->password) ? $fresh : null;
            }
        }
        return $user;
    }
    return null;
}
function current_user_id(): ?int
{
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? $id : null;
}
