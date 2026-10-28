<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../lib/html.php';
require_once __DIR__ . '/../../lib/numeric.php';
try {
    $cases = [
        ['1.2345',12,3,'1.235'], ['-1.2345',12,3,'-1.235'],
        ['0.005',14,2,'0.01'], ['-0.005',14,2,'-0.01'],
        ['999999999.9994',12,3,'999999999.999'],
        ['1e-3',12,3,'0.001'], ['.0005',12,3,'0.001'],
        ['12e2',14,2,'1200.00'], ['-0',12,3,'0.000'],
        ['invalid',14,2,'0'], ['1.0049',14,2,'1.00'],
        ['999999999999.994',14,2,'999999999999.99'],
    ];
    foreach ($cases as [$in,$precision,$scale,$expected]) {
        if (input_decimal($in,$precision,$scale) !== $expected) throw new RuntimeException('Decimal input rounding failed');
    }
    foreach ([['1.235','0.334','0.41'],['3.000','0.333','1.00'],['-1.235','1.000','-1.24'],['99.999','99.999','9999.80'],['999999.999','999999.999','999999998000.00'],['0.001','-5.000','-0.01']] as [$a,$b,$expected]) {
        if (decimal_product($a,$b) !== $expected) throw new RuntimeException('Exact decimal multiplication failed');
    }
    foreach (['12.345'=>'12.345','12.300'=>'12.3','0.000'=>'0','Customer 12.00'=>'Customer 12.00','0012'=>'0012'] as $in=>$expected) {
        if (fmt_numeric($in) !== $expected) throw new RuntimeException('Lossless numeric formatter failed');
    }
    echo "PASS: half-away rounding, signs, exponents, maximum ranges, products beyond integer capacity, zero fallback, precision-preserving numeric formatter\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
