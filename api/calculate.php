<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/calculation_engine.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** @param array<string,mixed> $payload */
function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, [
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Only POST is allowed.',
        ],
    ]);
}

$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
if ($contentType !== 'application/json') {
    respond(415, [
        'success' => false,
        'error' => [
            'code' => 'UNSUPPORTED_MEDIA_TYPE',
            'message' => 'Content-Type must be application/json.',
        ],
    ]);
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    respond(422, [
        'success' => false,
        'error' => [
            'code' => 'INVALID_JSON',
            'message' => 'Request body must contain a JSON object.',
        ],
    ]);
}

try {
    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    respond(422, [
        'success' => false,
        'error' => [
            'code' => 'INVALID_JSON',
            'message' => 'Request body contains invalid JSON.',
        ],
    ]);
}

if (!is_array($input) || array_is_list($input)) {
    respond(422, [
        'success' => false,
        'error' => [
            'code' => 'INVALID_INPUT',
            'message' => 'Top-level JSON value must be an object.',
        ],
    ]);
}

try {
    $result = CalculationEngineV100::calculate($input);
    respond(200, $result);
} catch (InvalidArgumentException $e) {
    $rawCode = $e->getMessage();
    $parts = explode(':', $rawCode, 2);
    $code = $parts[0] !== '' ? $parts[0] : 'VALIDATION_ERROR';

    respond(422, [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $rawCode,
        ],
        'engine_version' => CalculationEngineV100::VERSION,
    ]);
} catch (Throwable $e) {
    error_log(sprintf('CE-1.0.0 calculate failure: %s: %s', get_class($e), $e->getMessage()));
    respond(500, [
        'success' => false,
        'error' => [
            'code' => 'CALCULATION_FAILED',
            'message' => 'Calculation failed.',
        ],
        'engine_version' => CalculationEngineV100::VERSION,
    ]);
}
