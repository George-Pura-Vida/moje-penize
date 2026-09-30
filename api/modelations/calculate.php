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

function assetErrorContext(array $input, string $code, ?string $field): array {
    $assets = $input['assets'] ?? null;
    if (!is_array($assets)) return [];

    $predicate = match ($code) {
        'ASSET_ID_REQUIRED' => static fn(array $a): bool => trim((string)($a['id'] ?? '')) === '',
        'DUPLICATE_ASSET_ID' => null,
        'INITIAL_CONTRIBUTION_NEGATIVE' => static fn(array $a): bool => isset($a['initial_contribution']) && is_numeric($a['initial_contribution']) && (float)$a['initial_contribution'] < 0,
        'MONTHLY_CONTRIBUTION_NEGATIVE' => static fn(array $a): bool => isset($a['monthly_contribution']) && is_numeric($a['monthly_contribution']) && (float)$a['monthly_contribution'] < 0,
        'ANNUAL_RETURN_OUT_OF_RANGE' => static fn(array $a): bool => isset($a['annual_return']) && is_numeric($a['annual_return']) && (float)$a['annual_return'] <= -1,
        'ENTRY_FEE_INITIAL_OUT_OF_RANGE' => static fn(array $a): bool => isset($a['entry_fee_initial_pct']) && is_numeric($a['entry_fee_initial_pct']) && ((float)$a['entry_fee_initial_pct'] < 0 || (float)$a['entry_fee_initial_pct'] > 1),
        'ENTRY_FEE_MONTHLY_OUT_OF_RANGE' => static fn(array $a): bool => isset($a['entry_fee_monthly_pct']) && is_numeric($a['entry_fee_monthly_pct']) && ((float)$a['entry_fee_monthly_pct'] < 0 || (float)$a['entry_fee_monthly_pct'] > 1),
        'ONGOING_FEE_OUT_OF_RANGE' => static fn(array $a): bool => isset($a['ongoing_fee_pct_pa']) && is_numeric($a['ongoing_fee_pct_pa']) && ((float)$a['ongoing_fee_pct_pa'] < 0 || (float)$a['ongoing_fee_pct_pa'] >= 1),
        'FIXED_FEE_NEGATIVE' => static fn(array $a): bool => isset($a['fixed_fee_monthly']) && is_numeric($a['fixed_fee_monthly']) && (float)$a['fixed_fee_monthly'] < 0,
        'NON_FINITE_NUMBER' => $field ? static fn(array $a): bool => !array_key_exists($field, $a) || !is_numeric($a[$field]) || !is_finite((float)$a[$field]) : null,
        default => null,
    };

    if ($code === 'DUPLICATE_ASSET_ID') {
        $seen = [];
        foreach ($assets as $index => $asset) {
            if (!is_array($asset)) continue;
            $id = (string)($asset['id'] ?? '');
            if ($id !== '' && isset($seen[$id])) return ['asset_index' => $index, 'asset_id' => $id];
            if ($id !== '') $seen[$id] = true;
        }
        return [];
    }

    if ($predicate !== null) {
        foreach ($assets as $index => $asset) {
            if (!is_array($asset)) continue;
            if ($predicate($asset)) return ['asset_index' => $index, 'asset_id' => (string)($asset['id'] ?? '')];
        }
    }
    return [];
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

$input = [];
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
    $code = $parts[0] !== '' ? $parts[0] : 'VALIDATION_ERROR';
    $field = $parts[1] ?? null;
    $error = ['code' => $code, 'message' => $message, 'field' => $field];
    if (is_array($input)) $error += assetErrorContext($input, $code, $field);
    respond(422, [
        'success' => false,
        'error' => $error,
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
