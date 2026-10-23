<?php
declare(strict_types=1);
function db(): PDO {
    $host=getenv('DB_HOST'); $port=getenv('DB_PORT'); $name=getenv('DB_NAME');
    $user=getenv('DB_USER'); $password=getenv('DB_PASSWORD');
    if(!$host||!$port||!$name||!$user||!$password) throw new RuntimeException('Database environment variables are missing.');
    $ca=dirname(__DIR__).'/certs/ca.pem';
    if(!is_file($ca)) throw new RuntimeException('Aiven CA certificate missing at certs/ca.pem.');
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_SSL_CA=>$ca,PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=>true];
    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$password,$options);
}
function jsonResponse(array $data,int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($data,JSON_UNESCAPED_UNICODE); exit;
}
