<?php
declare(strict_types=1);
require_once __DIR__.'/../config/db.php';
$pdo=null;
try {
 $pdo=db(); $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET') {
  $sql="SELECT a.id,a.title,a.price,a.description,c.name AS category,ci.name AS city,
   COALESCE((SELECT ai.image_url FROM ad_images ai WHERE ai.ad_id=a.id ORDER BY ai.sort_order,ai.id LIMIT 1),'') AS image
   FROM ads a JOIN categories c ON c.id=a.category_id JOIN cities ci ON ci.id=a.city_id
   WHERE a.status='active' ORDER BY a.created_at DESC";
  jsonResponse($pdo->query($sql)->fetchAll());
 }
 if($method==='POST') {
  $in=json_decode(file_get_contents('php://input'),true);
  if(!is_array($in))jsonResponse(['error'=>'Invalid JSON body'],400);
  $title=trim((string)($in['title']??'')); $desc=trim((string)($in['description']??''));
  $price=filter_var($in['price']??null,FILTER_VALIDATE_FLOAT);
  $cat=filter_var($in['category_id']??null,FILTER_VALIDATE_INT);
  $city=filter_var($in['city_id']??null,FILTER_VALIDATE_INT); $image=trim((string)($in['image']??''));
  if($title===''||mb_strlen($title)>200||$desc===''||$price===false||$price<0||!$cat||!$city)
   jsonResponse(['error'=>'Complete all ad fields correctly.'],400);
  if($image!==''&&!str_starts_with($image,'https://')&&!str_starts_with($image,'/')&&!str_starts_with($image,'images/'))
   jsonResponse(['error'=>'Image must be an HTTPS URL or project image path.'],400);
  $chk=$pdo->prepare('SELECT EXISTS(SELECT 1 FROM categories WHERE id=?) AS cat_ok, EXISTS(SELECT 1 FROM cities WHERE id=?) AS city_ok');
  $chk->execute([$cat,$city]); $valid=$chk->fetch();
  if(!$valid||!(int)$valid['cat_ok']||!(int)$valid['city_ok'])jsonResponse(['error'=>'Choose a valid category and city.'],400);
  $pdo->beginTransaction();
  $ins=$pdo->prepare("INSERT INTO ads(category_id,city_id,title,price,description,status) VALUES(?,?,?,?,?,'active')");
  $ins->execute([$cat,$city,$title,$price,$desc]); $id=(int)$pdo->lastInsertId();
  if($image!==''){$im=$pdo->prepare('INSERT INTO ad_images(ad_id,image_url,sort_order) VALUES(?,?,0)');$im->execute([$id,$image]);}
  $pdo->commit(); jsonResponse(['id'=>$id,'message'=>'Ad saved'],201);
 }
 header('Allow: GET, POST'); jsonResponse(['error'=>'Method not allowed'],405);
} catch(Throwable $e) {
 if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
 error_log((string)$e); jsonResponse(['error'=>'Server error. Check Vercel function logs.'],500);
}
