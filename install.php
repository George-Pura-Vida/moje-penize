<?php
declare(strict_types=1); require __DIR__.'/lib/bootstrap.php';
$lock=__DIR__.'/.installed'; if(is_file($lock)){http_response_code(403);exit('Installer is locked.');}
try{$pdo=db();foreach(['sql/mysql/001_schema.sql','sql/mysql/002_modules.sql','sql/mysql/003_dashboard.sql'] as $f){$sql=file_get_contents(__DIR__.'/'.$f);$pdo->exec($sql);}file_put_contents($lock,date(DATE_ATOM));echo 'OK - database installed. Delete install.php or keep .installed lock.';}catch(Throwable $e){http_response_code(500);echo 'Installation failed. Check server configuration and MySQL compatibility.';}
