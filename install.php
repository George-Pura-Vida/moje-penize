<?php
declare(strict_types=1);
// Public requests must never initialize the database, even without .installed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=utf-8');
    exit('Installer is locked.');
}
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/schema.php';
$locked = false;
$failed = false;
try {
    $pdo = db();
    if (schema_ready($pdo)) {
        echo "OK - schema already installed. No changes made.\n";
        exit(0);
    }
    if (($argv[1] ?? '') !== '--apply') {
        fwrite(STDERR, "Schema incomplete. Back up the database, then run php install.php --apply.\n");
        exit(1);
    }
    // MySQL DDL is not transactional. Serialize initialization and allow retries.
    $lockName = 'moje-penize:' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    $locked = (int)$lock->fetchColumn() === 1;
    if (!$locked) {
        throw new RuntimeException('Initialization already running.');
    }
    if (!schema_ready($pdo)) {
        $scripts = [];
        foreach (['001_schema', '002_modules', '003_dashboard'] as $version) {
            $sql = file_get_contents(__DIR__ . '/sql/mysql/' . $version . '.sql');
            if ($sql === false) {
                throw new RuntimeException('Missing migration.');
            }
            $scripts[] = $sql;
        }
        foreach ($scripts as $sql) {
            $pdo->exec($sql);
        }
        if (!schema_ready($pdo)) {
            throw new RuntimeException('Schema verification failed.');
        }
    }
    if (file_put_contents(__DIR__ . '/.installed', date(DATE_ATOM), LOCK_EX) === false) {
        throw new RuntimeException('Cannot write installation marker.');
    }
    echo "OK - database installed and verified. Web installer remains locked.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Initialization failed. Check configuration, SQL compatibility and file permissions.\n");
    $failed = true;
} finally {
    if ($locked) {
        $unlock = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $unlock->execute([$lockName]);
    }
}
exit($failed ? 1 : 0);
