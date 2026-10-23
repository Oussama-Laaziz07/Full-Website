<?php
declare(strict_types=1);
require_once __DIR__.'/../config/db.php';
try { jsonResponse(db()->query('SELECT id,name FROM cities ORDER BY name')->fetchAll()); }
catch(Throwable $e){error_log((string)$e);jsonResponse(['error'=>'Could not load cities'],500);}
