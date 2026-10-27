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
    $title=trim((string)($_POST['title']??'')); $desc=trim((string)($_POST['description']??''));
    $price=filter_var($_POST['price']??null,FILTER_VALIDATE_FLOAT); $cat=filter_var($_POST['category_id']??null,FILTER_VALIDATE_INT);
    $city=filter_var($_POST['city_id']??null,FILTER_VALIDATE_INT);
    if($title===''||mb_strlen($title)>200||$desc===''||$price===false||$price<0||!$cat||!$city) jsonResponse(['error'=>'Complete all ad fields correctly.'],400);
    $check=$pdo->prepare('SELECT (SELECT COUNT(*) FROM categories WHERE id=?) cat_ok,(SELECT COUNT(*) FROM cities WHERE id=?) city_ok');
    $check->execute([$cat,$city]); $valid=$check->fetch();
    if(!(int)$valid['cat_ok']||!(int)$valid['city_ok']) jsonResponse(['error'=>'Choose a valid category and city.'],400);
    $image='';
    if(isset($_FILES['image']) && $_FILES['image']['error']!==UPLOAD_ERR_NO_FILE) {
        $file=$_FILES['image'];
        if($file['error']!==UPLOAD_ERR_OK) jsonResponse(['error'=>'The image could not be uploaded. Please try again.'],400);
        if($file['size']>5*1024*1024) jsonResponse(['error'=>'The image must be 5 MB or smaller.'],400);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if(!isset($extensions[$mime])) jsonResponse(['error'=>'Choose a JPG, PNG, WEBP, or GIF image.'],400);
        $cloudName=getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey=getenv('CLOUDINARY_API_KEY');
        $apiSecret=getenv('CLOUDINARY_API_SECRET');
        if(!$cloudName||!$apiKey||!$apiSecret) jsonResponse(['error'=>'Cloudinary image storage is not configured.'],503);
        if(!function_exists('curl_init')) jsonResponse(['error'=>'Image storage is unavailable on this server.'],500);

        $publicId='offria/'.bin2hex(random_bytes(16));
        $timestamp=time();
        $signature=sha1('public_id='.$publicId.'&timestamp='.$timestamp.$apiSecret);
        $curl=curl_init('https://api.cloudinary.com/v1_1/'.rawurlencode($cloudName).'/image/upload');
        curl_setopt_array($curl,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>[
                'file'=>new CURLFile($file['tmp_name'],$mime,'image.'.$extensions[$mime]),
                'public_id'=>$publicId,
                'timestamp'=>$timestamp,
                'api_key'=>$apiKey,
                'signature'=>$signature,
            ],
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT=>45,
        ]);
        $response=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $curlError=curl_error($curl);
        curl_close($curl);
        $uploaded=is_string($response)?json_decode($response,true):null;
        if($curlError!==''||$status<200||$status>=300||!is_array($uploaded)||empty($uploaded['secure_url'])) {
            error_log('Cloudinary upload failed: '.($curlError!==''?$curlError:'HTTP '.$status.' '.substr((string)$response,0,500)));
            jsonResponse(['error'=>'The image could not be saved to Cloudinary. Please try again.'],502);
        }
        $image=(string)$uploaded['secure_url'];
    }
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare("INSERT INTO ads(owner_id,category_id,city_id,title,price,description,status) VALUES(?,?,?,?,?,?,'pending_review')");
        $q->execute([(int)$user['id'],$cat,$city,$title,$price,$desc]); $id=(int)$pdo->lastInsertId();
        if($image!==''){$im=$pdo->prepare('INSERT INTO ad_images(ad_id,image_url,sort_order) VALUES(?,?,0)');$im->execute([$id,$image]);}
        $pdo->commit(); jsonResponse(['id'=>$id,'message'=>'Your ad was submitted for administrator review.'],201);
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}
header('Allow: GET, POST'); jsonResponse(['error'=>'Method not allowed.'],405);
