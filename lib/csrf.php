<?php
/** Session-bound synchronizer token; never accept tokens from the query string. */
function csrf_token(): string
{
    if (!is_string($_SESSION['csrf_token'] ?? null)) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): void { echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid request token. Please reload the form and try again.');
    }
}
function csrf_check_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') require_csrf();
}
/** Reject ambiguous submissions before any business operation starts. */
function mutation_action(array $actions): string
{
    $selected = array_keys(array_filter($actions));
    if (count($selected) > 1) { http_response_code(400); exit('Submit one action at a time.'); }
    return $selected[0] ?? '';
}
/** A real HTML confirmation works in desktop shells without native JS dialogs. */
function confirm_delete(array $fields, string $cancel): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['confirm_delete'] ?? '') === 'yes') return;
    echo '<!doctype html><html><head><meta charset="UTF-8"><title>Confirm deletion</title></head><body><h1>Confirm deletion</h1><p>Delete this record?</p><form method="post" action="' . e($_SERVER['SCRIPT_NAME']) . '">';
    csrf_field();
    foreach ($fields as $key => $value) echo '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
    echo '<button type="submit" name="confirm_delete" value="yes">Delete</button> <a href="' . e($cancel) . '">Cancel</a></form></body></html>';
    exit;
}
function action_form(string $url, array $fields, string $label): string
{
    $html = '<form method="post" action="' . e($url) . '" style="display:inline"><input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    foreach ($fields as $key => $value) $html .= '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
    return $html . '<button type="submit" class="battun">' . e($label) . '</button></form>';
}
