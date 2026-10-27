<?php
// Fixture and profiling tools must never run through the public document root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** DOM extraction intentionally compares displayed values, not HTML serialization. */
function characterization_extract(string $html, string $page = ''): string
{
    // The only normalization rules. Do not mask historical record dates or sums.
    $normalize = [
        // All root pages include menu.php: the title contains the signed-in user.
        'menu-title' => ['/^Doyel Eng\.\s*\|\s*.*$/u', 'Doyel Eng. | <user>'],
        // Report.php defaults these two input values to the day capture runs.
        'report-date-inputs' => ['start_date', 'end_date'],
        // Only the live closing report prints today's date; saved snapshot dates stay literal.
        'closing-current-date' => ['/^Closing Balance Date - \d{2}-[A-Za-z]{3}-\d{4}$/m', 'Closing Balance Date - <today>'],
        // Accounts.php uses the wall clock for these six live period summaries.
        'accounts-periods' => ['/(Cash (?:Collection|Expenses) (?:This Month|Previous Month|This Year))\n[^\n]*/u', '$1\n<current-period>'],
    ];
    // Errors are captured verbatim in private _errors.txt, independently of visible
    // business text. Removing them here lets Phase 2 fix warnings without hiding
    // unrelated output differences. Statuses are also a separate contract.
    $html = preg_replace('/<br\s*\/?>\s*<b>(?:Notice|Warning|Deprecated|Fatal error|Parse error)<\/b>:.*?<br\s*\/?>/is', '', $html);
    // Dictionary endpoints expose an array of suggestions, not an HTML page.
    // Legacy emits a trailing comma and spaces; modern emits JSON. Compare the
    // same ordered string values while retaining exact slash-protocol text below.
    if (in_array($page, ['dictionary/SalesItem.php', 'dictionary/PurItem.php', 'dictionary/supplier.php'], true)) {
        $array = json_decode(preg_replace('/,\s*\]/', ']', trim($html)), true);
        if (is_array($array)) return implode("\n", $array) . "\n";
    }
    if (trim($html) === '') return '';
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $collapse = static fn(string $value): string => trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $value) ?? $value);
    $inline = function (DOMNode $node) use (&$inline, $collapse, $page, $normalize): string {
        if ($node instanceof DOMText) return $node->nodeValue;
        if (!$node instanceof DOMElement) return '';
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'noscript'], true) || $node->hasAttribute('hidden')
            || preg_match('/display\s*:\s*none|visibility\s*:\s*hidden/i', $node->getAttribute('style'))) return '';
        if ($tag === 'input') {
            if (in_array(strtolower($node->getAttribute('type')), ['password', 'hidden'], true)) return '';
            $value = $node->getAttribute('value');
            if ($page === 'Report.php' && in_array($node->getAttribute('name'), $normalize['report-date-inputs'], true)) return '<today>';
            return $value;
        }
        if ($tag === 'select') {
            $options = $node->getElementsByTagName('option');
            $selected = [];
            foreach ($options as $option) if ($option->hasAttribute('selected')) $selected[] = $option->textContent;
            return implode(' ', $selected ?: ($options->length ? [$options->item(0)->textContent] : []));
        }
        $value = '';
        foreach ($node->childNodes as $child) $value .= $inline($child);
        return $tag === 'br' ? ' ' : $value;
    };
    $lines = [];
    foreach ($doc->getElementsByTagName('title') as $title) {
        $value = $collapse($title->textContent);
        if ($value !== '') $lines[] = preg_replace(...[$normalize['menu-title'][0], $normalize['menu-title'][1], $value]);
    }
    $buffer = '';
    $flush = static function () use (&$buffer, &$lines, $collapse): void {
        $value = $collapse($buffer);
        if ($value !== '') $lines[] = $value;
        $buffer = '';
    };
    $walk = function (DOMNode $node) use (&$walk, $inline, &$buffer, &$lines, $flush, $collapse): void {
        if ($node instanceof DOMText) { $buffer .= $node->nodeValue; return; }
        if (!$node instanceof DOMElement) return;
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'noscript', 'head', 'title'], true) || $node->hasAttribute('hidden')
            || preg_match('/display\s*:\s*none|visibility\s*:\s*hidden/i', $node->getAttribute('style'))) return;
        if ($tag === 'tr') {
            $flush();
            $cells = [];
            foreach ($node->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) $cells[] = $collapse($inline($cell));
            }
            if ($cells) $lines[] = implode(' | ', $cells);
            return;
        }
        if (in_array($tag, ['input', 'select', 'textarea'], true)) { $buffer .= ' ' . $inline($node) . ' '; return; }
        $block = in_array($tag, ['div', 'p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'form', 'table', 'ul', 'ol', 'br', 'hr'], true);
        if ($block) $flush();
        foreach ($node->childNodes as $child) $walk($child);
        if ($block) $flush();
    };
    // Malformed legacy markup often puts page content outside the body. Traverse
    // the document root but skip head/scripts; DOM has already repaired nesting.
    $walk($doc->documentElement);
    $flush();
    $text = implode("\n", $lines);
    if ($page === 'Accounts.php') $text = preg_replace($normalize['accounts-periods'][0], str_replace('\\n', "\n", $normalize['accounts-periods'][1]), $text);
    if ($page === 'report/closingBalance.php') $text = preg_replace($normalize['closing-current-date'][0], $normalize['closing-current-date'][1], $text);
    return $text === '' ? '' : $text . "\n";
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (in_array($argv[1] ?? '', ['-h', '--help'], true)) { echo "Usage: php tools/characterize/extract.php [page-path] < response.html\n"; exit; }
    echo characterization_extract(stream_get_contents(STDIN), $argv[1] ?? '');
}
