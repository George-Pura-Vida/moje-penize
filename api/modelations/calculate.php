<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/calculation_engine.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond(405, [
        'success' => false,
        'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Only POST is allowed.'],
        'engine_version' => CalculationEngineV100::VERSION,
    ]);
}

$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    respond(415, [
        'success' => false,
        'error' => ['code' => 'UNSUPPORTED_MEDIA_TYPE', 'message' => 'Content-Type must be application/json.'],
        'engine_version' => CalculationEngineV100::VERSION,
    ]);
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    respond(422, [
        'success' => false,
        'error' => ['code' => 'INVALID_JSON', 'message' => 'Request body must contain a JSON object.'],
        'engine_version' => CalculationEngineV100::VERSION,
    ]);
}

try {
    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input) || array_is_list($input)) {
        throw new InvalidArgumentException('INVALID_REQUEST_OBJECT');
    }

    $result = CalculationEngineV100::calculate($input);
    respond(200, $result);
} catch (JsonException $e) {
    respond(422, [
        'success' => false,
        'error' => ['code' => 'INVALID_JSON', 'message' => 'Malformed JSON request.'],
        'engine_version' => CalculationEngineV100::VERSION,
    ]);
} catch (InvalidArgumentException $e) {
    $message = $e->getMessage();
    $parts = explode(':', $message, 2);
    respond(422, [
        'success' => false,
        'error' => [
            'code' => $parts[0] !== '' ? $parts[0] : 'VALIDATION_ERROR',
            'message' => $message,
            'field' => $parts[1] ?? null,
        ],
        'engine_version' => CalculationEngineV100::VERSION,
    ]);
} catch (Throwable $e) {
    error_log('CE-1.0.0 calculation failure: ' . $e->getMessage());
    respond(500, [
        'success' => false,
        'error' => ['code' => 'CALCULATION_FAILED', 'message' => 'Calculation could not be completed.'],
        'engine_version' => CalculationEngineV100::VERSION,
    ]);
}
