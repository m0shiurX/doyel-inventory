<?php
function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url): never
{
    if (!headers_sent()) header('Location: ' . $url);
    else echo '<script type="text/javascript">window.location.href=' . json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
    exit;
}
function redirectTo(string $url): never { redirect($url); }
