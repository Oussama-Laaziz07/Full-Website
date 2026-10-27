<?php
declare(strict_types=1);
require_once __DIR__.'/../config/auth.php';
$pdo=db(); $admin=requireAdmin($pdo);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET') {
    $ads=$pdo->query("SELECT a.id,a.title,a.price,a.description,a.status,a.created_at,c.name category,ci.name city,u.full_name owner_name,u.email owner_email,COALESCE((SELECT ai.image_url FROM ad_images ai WHERE ai.ad_id=a.id ORDER BY ai.sort_order,ai.id LIMIT 1),'') image FROM ads a JOIN categories c ON c.id=a.category_id JOIN cities ci ON ci.id=a.city_id LEFT JOIN users u ON u.id=a.owner_id WHERE a.status IN ('pending_review','removed') ORDER BY a.created_at ASC")->fetchAll();
    $appeals=$pdo->query("SELECT ap.id,ap.ad_id,ap.reason,ap.status,ap.created_at,a.title,u.full_name owner_name,u.email owner_email FROM ad_appeals ap JOIN ads a ON a.id=ap.ad_id JOIN users u ON u.id=ap.user_id WHERE ap.status='pending' ORDER BY ap.created_at ASC")->fetchAll();
    $users=$pdo->query("SELECT id,full_name,email,role,status,created_at FROM users WHERE role='user' ORDER BY created_at DESC")->fetchAll();
    $cities=$pdo->query('SELECT id,name FROM cities ORDER BY name')->fetchAll();
    $categories=$pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
    jsonResponse(['ads'=>$ads,'appeals'=>$appeals,'users'=>$users,'cities'=>$cities,'categories'=>$categories]);
}
if($method!=='POST'){header('Allow: GET, POST');jsonResponse(['error'=>'Method not allowed.'],405);}
$in=json_decode(file_get_contents('php://input'),true); if(!is_array($in))jsonResponse(['error'=>'Invalid JSON body.'],400);
$action=(string)($in['action']??''); $id=(int)($in['id']??0);
try {
 switch($action) {
  case 'add-city': case 'add-category':
   $name=trim((string)($in['name']??'')); if($name===''||mb_strlen($name)>100)jsonResponse(['error'=>'Enter a name up to 100 characters.'],400);
   $table=$action==='add-city'?'cities':'categories'; $q=$pdo->prepare("INSERT INTO {$table}(name) VALUES(?)"); $q->execute([$name]);
   jsonResponse(['message'=>'Added successfully.']);
  case 'delete-city': case 'delete-category':
   $table=$action==='delete-city'?'cities':'categories'; $q=$pdo->prepare("DELETE FROM {$table} WHERE id=?"); $q->execute([$id]);
   if(!$q->rowCount())jsonResponse(['error'=>'Item not found.'],404); jsonResponse(['message'=>'Deleted successfully.']);
  case 'approve-ad':
   $q=$pdo->prepare("UPDATE ads SET status='active' WHERE id=? AND status='pending_review'");$q->execute([$id]);
   if(!$q->rowCount())jsonResponse(['error'=>'Pending ad not found.'],404);jsonResponse(['message'=>'Ad approved and published.']);
  case 'remove-ad':
   $q=$pdo->prepare("UPDATE ads SET status='removed' WHERE id=? AND status IN ('active','pending_review')");$q->execute([$id]);
   if(!$q->rowCount())jsonResponse(['error'=>'Ad could not be removed.'],404);jsonResponse(['message'=>'Ad moved to the administrator review list.']);
  case 'resolve-appeal':
   $decision=(string)($in['decision']??''); if(!in_array($decision,['approve','reject'],true))jsonResponse(['error'=>'Choose approve or reject.'],400);
   $pdo->beginTransaction();
   $q=$pdo->prepare("SELECT ad_id FROM ad_appeals WHERE id=? AND status='pending' FOR UPDATE");$q->execute([$id]);$appeal=$q->fetch();
   if(!$appeal){$pdo->rollBack();jsonResponse(['error'=>'Pending appeal not found.'],404);}
   $status=$decision==='approve'?'approved':'rejected';$q=$pdo->prepare('UPDATE ad_appeals SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?');$q->execute([$status,(int)$admin['id'],$id]);
   if($decision==='approve'){$q=$pdo->prepare("UPDATE ads SET status='active' WHERE id=? AND status='removed'");$q->execute([(int)$appeal['ad_id']]);}
   $pdo->commit();jsonResponse(['message'=>$decision==='approve'?'Appeal approved; the ad is live again.':'Appeal rejected.']);
  case 'delete-user':
   if($id===(int)$admin['id'])jsonResponse(['error'=>'You cannot disable your own account.'],400);
   $pdo->beginTransaction();
   $q=$pdo->prepare("UPDATE users SET status='disabled' WHERE id=? AND role='user' AND status='active'");$q->execute([$id]);
   if(!$q->rowCount()){$pdo->rollBack();jsonResponse(['error'=>'User not found or unavailable.'],404);}
   $q=$pdo->prepare("UPDATE ads SET status='removed' WHERE owner_id=? AND status IN ('active','pending_review')");$q->execute([$id]);
   $pdo->commit();jsonResponse(['message'=>'Account disabled and its ads removed from public listings.']);
  default: jsonResponse(['error'=>'Unknown administrator action.'],400);
 }
} catch(PDOException $e) {
 if($pdo->inTransaction())$pdo->rollBack();
 if($e->getCode()==='23000')jsonResponse(['error'=>'This item already exists or is still used by an ad.'],409);
 error_log((string)$e);jsonResponse(['error'=>'Could not complete that administrator action.'],500);
}
