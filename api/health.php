<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/schema.php';

header('Cache-Control: no-store');

const RELEASE_SHA = 'f11a0b27fc54ebfe18f6e35c174807033e383aba';
const RELEASE_VERSION = 'CE-1.0.0';

function health_payload(array $state): array
{
    return array_merge($state, [
        'release_sha' => RELEASE_SHA,
        'version' => RELEASE_VERSION,
        'release' => 'safe-install-v1',
    ]);
}

try {
    $pdo = db();
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    json_out(health_payload([
        'ok' => false,
        'database' => 'unavailable',
    ]), 503);
}

try {
    $ready = schema_ready($pdo);
    json_out(health_payload([
        'ok' => $ready,
        'database' => 'connected',
        'schema' => $ready ? 'ready' : 'incomplete',
    ]), $ready ? 200 : 503);
} catch (Throwable $e) {
    json_out(health_payload([
        'ok' => false,
        'database' => 'connected',
        'schema' => 'unavailable',
    ]), 503);
}
