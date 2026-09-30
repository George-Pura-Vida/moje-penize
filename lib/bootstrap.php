<?php
declare(strict_types=1);
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
function db(): PDO { static $pdo=null; if($pdo) return $pdo; $file=dirname(__DIR__).'/config.php'; if(!is_file($file)) throw new RuntimeException('Server configuration is not installed.'); $c=require $file; $d=$c['db']; $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$d['host'],$d['port'],$d['name'],$d['charset']??'utf8mb4'); return $pdo=new PDO($dsn,$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); }
function json_out(array $v,int $code=200): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
