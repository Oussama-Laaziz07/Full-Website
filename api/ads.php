<?php
declare(strict_types=1);
require_once __DIR__.'/../config/auth.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$select = "SELECT a.id,a.title,a.price,a.description,c.name AS category,ci.name AS city,
 COALESCE((SELECT ai.image_url FROM ad_images ai WHERE ai.ad_id=a.id ORDER BY ai.sort_order,ai.id LIMIT 1),'') AS image
 FROM ads a JOIN categories c ON c.id=a.category_id JOIN cities ci ON ci.id=a.city_id";

if ($method === 'GET') {
    jsonResponse($pdo->query($select." WHERE a.status='active' ORDER BY a.created_at DESC")->fetchAll());
}
if ($method === 'POST') {
    $user = requireUser($pdo);
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) jsonResponse(['error'=>'Invalid JSON body'],400);
    $title=trim((string)($in['title']??'')); $desc=trim((string)($in['description']??''));
    $price=filter_var($in['price']??null,FILTER_VALIDATE_FLOAT); $cat=filter_var($in['category_id']??null,FILTER_VALIDATE_INT);
    $city=filter_var($in['city_id']??null,FILTER_VALIDATE_INT); $image=trim((string)($in['image']??''));
    if($title===''||mb_strlen($title)>200||$desc===''||$price===false||$price<0||!$cat||!$city) jsonResponse(['error'=>'Complete all ad fields correctly.'],400);
    if($image!==''&&!preg_match('~^(https://|/|images/)~',$image)) jsonResponse(['error'=>'Image must be an HTTPS URL or project image path.'],400);
    $check=$pdo->prepare('SELECT (SELECT COUNT(*) FROM categories WHERE id=?) cat_ok,(SELECT COUNT(*) FROM cities WHERE id=?) city_ok');
    $check->execute([$cat,$city]); $valid=$check->fetch();
    if(!(int)$valid['cat_ok']||!(int)$valid['city_ok']) jsonResponse(['error'=>'Choose a valid category and city.'],400);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare("INSERT INTO ads(owner_id,category_id,city_id,title,price,description,status) VALUES(?,?,?,?,?,?,'pending_review')");
        $q->execute([(int)$user['id'],$cat,$city,$title,$price,$desc]); $id=(int)$pdo->lastInsertId();
        if($image!==''){$im=$pdo->prepare('INSERT INTO ad_images(ad_id,image_url,sort_order) VALUES(?,?,0)');$im->execute([$id,$image]);}
        $pdo->commit(); jsonResponse(['id'=>$id,'message'=>'Your ad was submitted for administrator review.'],201);
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}
header('Allow: GET, POST'); jsonResponse(['error'=>'Method not allowed.'],405);
