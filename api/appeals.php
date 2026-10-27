<?php
declare(strict_types=1);
require_once __DIR__.'/../config/auth.php';
$pdo=db();$user=requireUser($pdo);$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
 $q=$pdo->prepare("SELECT ap.id,ap.ad_id,ap.reason,ap.status,ap.created_at,a.title FROM ad_appeals ap JOIN ads a ON a.id=ap.ad_id WHERE ap.user_id=? ORDER BY ap.created_at DESC");
 $q->execute([(int)$user['id']]);jsonResponse($q->fetchAll());
}
if($method!=='POST'){header('Allow: GET, POST');jsonResponse(['error'=>'Method not allowed.'],405);}
$in=json_decode(file_get_contents('php://input'),true);if(!is_array($in))jsonResponse(['error'=>'Invalid JSON body.'],400);
$adId=(int)($in['ad_id']??0);$reason=trim((string)($in['reason']??''));
if($reason===''||mb_strlen($reason)>2000)jsonResponse(['error'=>'Please provide an appeal reason (up to 2,000 characters).'],400);
$check=$pdo->prepare("SELECT id FROM ads WHERE id=? AND owner_id=? AND status='removed'");$check->execute([$adId,(int)$user['id']]);
if(!$check->fetchColumn())jsonResponse(['error'=>'You can appeal only your own removed ad.'],403);
$check=$pdo->prepare("SELECT id FROM ad_appeals WHERE ad_id=? AND status='pending'");$check->execute([$adId]);
if($check->fetchColumn())jsonResponse(['error'=>'An appeal for this ad is already awaiting review.'],409);
$q=$pdo->prepare("INSERT INTO ad_appeals(ad_id,user_id,reason,status) VALUES(?,?,?,'pending')");$q->execute([$adId,(int)$user['id'],$reason]);
jsonResponse(['message'=>'Your appeal was sent to the administrator.'],201);
