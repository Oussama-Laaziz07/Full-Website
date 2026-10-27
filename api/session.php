<?php
declare(strict_types=1);
require_once __DIR__.'/../config/auth.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';$pdo=db();
if($method==='GET'){
 $user=currentUser($pdo); if(!$user)jsonResponse(['user'=>null]);
 $q=$pdo->prepare("SELECT a.id,a.title,a.status,ap.status appeal_status FROM ads a LEFT JOIN ad_appeals ap ON ap.id=(SELECT MAX(ap2.id) FROM ad_appeals ap2 WHERE ap2.ad_id=a.id) WHERE a.owner_id=? AND a.status='removed' ORDER BY a.created_at DESC");
 $q->execute([(int)$user['id']]);jsonResponse(['user'=>$user,'removed_ads'=>$q->fetchAll()]);
}
if($method==='DELETE'){
 setcookie('offria_auth','',['expires'=>1,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https','httponly'=>true,'samesite'=>'Lax']);
 jsonResponse(['message'=>'Signed out.']);
}
header('Allow: GET, DELETE');jsonResponse(['error'=>'Method not allowed.'],405);
