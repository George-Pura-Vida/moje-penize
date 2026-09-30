<?php
declare(strict_types=1); require dirname(__DIR__).'/lib/bootstrap.php';
try{$pdo=db();$pdo->query('SELECT 1');$tables=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();json_out(['ok'=>true,'database'=>'connected','tables'=>$tables]);}catch(Throwable $e){json_out(['ok'=>false,'database'=>'not_configured'],503);}
