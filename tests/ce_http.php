<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$port = 18765;
$host = "127.0.0.1:$port";
$cmd = sprintf('php -S %s -t %s >/tmp/moje-penize-ce-http.log 2>&1 & echo $!', escapeshellarg($host), escapeshellarg($root));
$pid = (int)trim((string)shell_exec($cmd));
usleep(400000);

function request(string $url, string $method = 'POST', ?string $body = null, string $contentType = 'application/json'): array {
    $headers = [$contentType !== '' ? "Content-Type: $contentType" : 'Accept: application/json'];
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body ?? '',
        'ignore_errors' => true,
        'timeout' => 5,
    ]]);
    $raw = file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $status = (int)$m[1];
    return [$status, json_decode((string)$raw, true), (string)$raw];
}

$failures = [];
$endpoint = "http://$host/api/modelations/calculate.php";

try {
    foreach (glob($root . '/tests/fixtures/ce-1.0.0/T*.json') as $file) {
        $fx = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (($fx['status'] ?? '') !== 'ACTIVE') continue;
        if (isset($fx['cases'])) {
            foreach ($fx['cases'] as $case) {
                [$status, $json] = request($endpoint, 'POST', json_encode($case['input'], JSON_THROW_ON_ERROR));
                $code = $json['error']['code'] ?? null;
                if ($status !== 422 || ($json['success'] ?? true) !== false || $code !== $case['expected_error']) {
                    $failures[] = "{$case['id']}: expected HTTP 422/{$case['expected_error']}, got HTTP $status/" . ($code ?? 'null');
                }
            }
            continue;
        }
        [$status, $json] = request($endpoint, 'POST', json_encode($fx['input'], JSON_THROW_ON_ERROR));
        if ($status !== 200 || !is_array($json) || ($json['success'] ?? false) !== true || ($json['engine_version'] ?? '') !== '1.0.0') {
            $failures[] = "{$fx['id']}: invalid HTTP success contract (HTTP $status)";
            continue;
        }
        $tol = (float)($fx['tolerance']['money_abs'] ?? 0.01);
        foreach ($fx['expected'] as $key => $expected) {
            if (is_numeric($expected) && (!isset($json[$key]) || abs((float)$json[$key] - (float)$expected) > $tol)) {
                $failures[] = "{$fx['id']}: $key outside tolerance";
            } elseif (is_string($expected) && ($json[$key] ?? null) !== $expected) {
                $failures[] = "{$fx['id']}: $key mismatch";
            } elseif (is_bool($expected) && ($json[$key] ?? null) !== $expected) {
                $failures[] = "{$fx['id']}: $key mismatch";
            }
        }
        $sumAssets = 0.0;
        foreach (($json['assets'] ?? []) as $asset) $sumAssets += (float)($asset['nominal_value'] ?? 0);
        if (abs($sumAssets - (float)$json['nominal_value']) > $tol) $failures[] = "{$fx['id']}: asset nominal invariant failed";
        if (abs(((float)$json['cumulative_entry_fees'] + (float)$json['cumulative_ongoing_fees'] + (float)$json['cumulative_fixed_fees']) - (float)$json['cumulative_total_fees']) > $tol) $failures[] = "{$fx['id']}: fee invariant failed";
    }

    [$status, $json] = request($endpoint, 'GET');
    if ($status !== 405 || ($json['error']['code'] ?? '') !== 'METHOD_NOT_ALLOWED') $failures[] = 'GET must fail closed with HTTP 405';
    [$status, $json] = request($endpoint, 'POST', '{broken');
    if ($status !== 422 || ($json['error']['code'] ?? '') !== 'INVALID_JSON') $failures[] = 'Malformed JSON must fail closed with HTTP 422';
    [$status, $json] = request($endpoint, 'POST', '{}', 'text/plain');
    if ($status !== 415 || ($json['error']['code'] ?? '') !== 'UNSUPPORTED_MEDIA_TYPE') $failures[] = 'Wrong media type must fail closed with HTTP 415';
} finally {
    if ($pid > 0) @posix_kill($pid, SIGTERM);
}

if ($failures) {
    fwrite(STDERR, "CE HTTP TEST FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "CE HTTP TEST PASS: T01-T15, HTTP 422, invariants, fail-closed\n";
