<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/schema.php';
header('Cache-Control: no-store');
try {
    $pdo = db();
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    json_out(['ok' => false, 'database' => 'unavailable', 'release' => 'safe-install-v1'], 503);
}
try {
    $ready = schema_ready($pdo);
    json_out(['ok' => $ready, 'database' => 'connected', 'schema' => $ready ? 'ready' : 'incomplete', 'release' => 'safe-install-v1'], $ready ? 200 : 503);
} catch (Throwable $e) {
    json_out(['ok' => false, 'database' => 'connected', 'schema' => 'unavailable', 'release' => 'safe-install-v1'], 503);
}
