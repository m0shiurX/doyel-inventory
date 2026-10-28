<?php
// Exact decimal input rounding, half away from zero (MySQL DECIMAL policy).
// Invalid ordinary input retains the old zero fallback. Range errors stop before writes.
function input_decimal(mixed $value, int $precision = 14, int $scale = 2): string
{
    $text = is_scalar($value) ? trim((string)$value) : '';
    if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', $text, $m)
        || ($m[2] === '' && ($m[3] ?? '') === '')) return '0';
    $exponent = (int)($m[4] ?? 0);
    if (strlen($text) > 200 || abs($exponent) > 100) {
        http_response_code(400); exit('Numeric value out of range.');
    }
    $digits = $m[2] . ($m[3] ?? '');
    $point = strlen($m[2]) + $exponent;
    if ($point < 0) { $digits = str_repeat('0', -$point) . $digits; $point = 0; }
    $digits = str_pad($digits, $point + $scale + 1, '0');
    $rounded = substr($digits, 0, $point + $scale);
    if ($digits[$point + $scale] >= '5') {
        $carry = 1;
        for ($i = strlen($rounded) - 1; $i >= 0 && $carry; $i--) {
            $n = (int)$rounded[$i] + 1; $rounded[$i] = (string)($n % 10); $carry = intdiv($n, 10);
        }
        if ($carry) $rounded = '1' . $rounded;
    }
    $rounded = ltrim($rounded, '0');
    if (strlen($rounded) > $precision) { http_response_code(400); exit('Numeric value out of range.'); }
    $rounded = str_pad($rounded, $scale + 1, '0', STR_PAD_LEFT);
    $result = $scale ? substr($rounded, 0, -$scale) . '.' . substr($rounded, -$scale) : $rounded;
    return ($m[1] === '-' && trim($rounded, '0') !== '' ? '-' : '') . $result;
}

// Multiply normalized decimal strings without binary floats or an optional extension.
function decimal_product(string $left, string $right): string
{
    $negative = str_starts_with($left, '-') !== str_starts_with($right, '-');
    $left = ltrim($left, '-'); $right = ltrim($right, '-');
    $places = strlen(strchr($left, '.') ?: '') + strlen(strchr($right, '.') ?: '');
    if (str_contains($left, '.')) $places--;
    if (str_contains($right, '.')) $places--;
    $a = str_replace('.', '', $left); $b = str_replace('.', '', $right);
    $digits = array_fill(0, strlen($a) + strlen($b), 0);
    for ($i = strlen($a) - 1; $i >= 0; $i--) {
        for ($j = strlen($b) - 1; $j >= 0; $j--) {
            $position = $i + $j + 1;
            $sum = $digits[$position] + (int)$a[$i] * (int)$b[$j];
            $digits[$position] = $sum % 10;
            $digits[$position - 1] += intdiv($sum, 10);
        }
    }
    $text = implode('', $digits);
    if ($places) $text = substr($text, 0, -$places) . '.' . substr($text, -$places);
    return input_decimal(($negative ? '-' : '') . $text);
}
