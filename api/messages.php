<?php
declare(strict_types=1);
require_once __DIR__.'/../config/db.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');jsonResponse(['error'=>'Method not allowed'],405);}
try {
 $in=json_decode(file_get_contents('php://input'),true); if(!is_array($in))jsonResponse(['error'=>'Invalid JSON body'],400);
 $ad=filter_var($in['ad_id']??null,FILTER_VALIDATE_INT); $name=trim((string)($in['name']??''));
 $email=trim((string)($in['email']??'')); $message=trim((string)($in['message']??''));
 if(!$ad||$name===''||mb_strlen($name)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>254||$message==='')
  jsonResponse(['error'=>'Enter a valid name, email, and message.'],400);
 $stmt=db()->prepare('INSERT INTO messages(ad_id,sender_name,sender_email,message_text) VALUES(:ad,:name,:email,:message)');
 $stmt->execute([':ad'=>$ad,':name'=>$name,':email'=>$email,':message'=>$message]);
 jsonResponse(['message'=>'Message saved'],201);
} catch(Throwable $e){error_log((string)$e);jsonResponse(['error'=>'Server error. Check Vercel function logs.'],500);}
