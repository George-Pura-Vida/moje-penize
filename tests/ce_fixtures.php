<?php
declare(strict_types=1);

require __DIR__ . '/../lib/calculation_engine.php';

$failed = 0;
foreach (glob(__DIR__ . '/fixtures/ce-1.0.0/T*.json') as $file) {
    $fixture = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $id = $fixture['id'];
    if ($id === 'T15') {
        foreach ($fixture['cases'] as $case) {
            try {
                CalculationEngineV100::calculate($case['input']);
                throw new RuntimeException('Expected rejection ' . $case['expected_error']);
            } catch (InvalidArgumentException $e) {
                if (explode(':', $e->getMessage(), 2)[0] !== $case['expected_error']) {
                    throw new RuntimeException($case['id'] . ': got ' . $e->getMessage());
                }
            }
        }
        echo "PASS T15 (" . count($fixture['cases']) . " errors)\n";
        continue;
    }
    try {
        $actual = CalculationEngineV100::calculate($fixture['input']);
        $check = static function (array $expected, array $actual, string $path) use (&$check, $fixture): void {
            if (array_keys($expected) !== array_keys($actual)) {
                throw new RuntimeException($path . ': output keys differ');
            }
            foreach ($expected as $key => $value) {
                $label = $path . '.' . $key;
                if (is_array($value)) {
                    $check($value, $actual[$key], $label);
                } elseif (is_float($value) || is_int($value)) {
                    $limit = $value == 0 && in_array($fixture['id'], ['T03', 'T10'], true)
                        ? $fixture['tolerance']['zero_abs'] : $fixture['tolerance']['money_abs'];
                    if (!is_numeric($actual[$key]) || !is_finite((float) $actual[$key]) || abs($value - $actual[$key]) > $limit) {
                        throw new RuntimeException($label . ': expected ' . $value . ', got ' . var_export($actual[$key], true));
                    }
                } elseif ($value !== $actual[$key]) {
                    throw new RuntimeException($label . ': value differs');
                }
            }
        };
        $check($fixture['expected'], $actual, $id);
        echo "PASS $id\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL $id: {$e->getMessage()}\n");
        ++$failed;
    }
}
exit($failed ? 1 : 0);
