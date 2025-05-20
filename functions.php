<?php

function logMailSend($to, $subject, $body, $from, $status, $error = '', $type = '') {
	$db = openConnect2025();
	$sql = 'INSERT INTO mail_log (to_address, subject, body, from_address, status, error_message, mail_type) VALUES (:to, :subject, :body, :from, :status, :error, :type)';
	$stmt = $db->prepare($sql);
	$stmt->execute([
		':to' => $to,
		':subject' => $subject,
		':body' => $body,
		':from' => $from,
		':status' => $status,
		':error' => $error,
		':type' => $type
	]);
}

function sendMail($to, $subject, $body, $from_email,$from_name){
	$headers  = "MIME-Version: 1.0 \n" ;
	mb_language("ja"); 
	$from_name = mb_convert_encoding($from_name,"ISO-2022-JP","UTF-8");
	$headers .= "From: " .
		   "".mb_encode_mimeheader($from_name) ."" .
		   "<".$from_email."> \n";
	$headers .= "Reply-To: " .
		   "".mb_encode_mimeheader($from_name) ."" .
		   "<".$from_email."> \n";

	   
	$headers .= "Content-Type: text/plain;charset=ISO-2022-JP \n";

		  
	$body = mb_convert_encoding($body, "ISO-2022-JP-MS","UTF-8");
	   
	$sendmail_params  = "-f $from_email";
	   
	mb_language("ja"); 
	$subject = mb_convert_encoding($subject, "ISO-2022-JP","UTF-8");
	$subject = mb_encode_mimeheader($subject);

	$result = mail($to, $subject, $body, $headers, $sendmail_params);
		  
	return $result;
}

function putThumPics($img,$uri,$size=300){
	$thum_size = $size;

	list($original_width, $original_height) = getimagesize($img);

	$proportion = $original_width / $original_height;

	if($proportion == 1){
		//正方形のとき
		$new_height = $thum_size;
		$new_width = $thum_size;
	}elseif($proportion > 1){
		//横長のとき
		$new_width = $thum_size;
		$new_height = round($thum_size / $proportion ,2);	
	}elseif($proportion < 1){
		//縦長のとき
		$new_height = $thum_size;
		$new_width = round($thum_size * $proportion ,2);
	}
	try{
		$original_image = ImageCreateFromJPEG($img); //JPEGファイルを読み込む
		$thum = ImageCreateTrueColor($new_width, $new_height); // 画像作成

		ImageCopyResampled($thum,$original_image,0,0,0,0,$new_width,$new_height,$original_width,$original_height);
		imagejpeg($thum,$uri,60); //画像圧縮&&保存

		imagedestroy($original_image);
		imagedestroy($thum);
		
		return true;
	}catch(Exception $e){
		return false;
	}
}





function imageOrientation($filename, $orientation){
	try {
		// 画像が存在するか確認
		if (!file_exists($filename)) {
			throw new Exception("画像ファイルが見つかりません: {$filename}");
		}

		// 画像ロード
		$image = @imagecreatefromjpeg($filename);
		if (!$image) {
			throw new Exception("JPEG画像の読み込みに失敗しました: {$filename}");
		}

		$degrees = 0;

		switch($orientation) {
			case 1:
				return; // 回転不要
			case 8:
				$degrees = 90;
				break;
			case 3:
				$degrees = 180;
				break;
			case 6:
				$degrees = 270;
				break;
			case 2:
				$mode = IMG_FLIP_HORIZONTAL;
				break;
			case 7:
				$degrees = 90;
				$mode = IMG_FLIP_HORIZONTAL;
				break;
			case 4:
				$mode = IMG_FLIP_VERTICAL;
				break;
			case 5:
				$degrees = 270;
				$mode = IMG_FLIP_HORIZONTAL;
				break;
			default:
				throw new Exception("未対応のorientation値です: {$orientation}");
		}

		if (isset($mode)) {
			if (!imageflip($image, $mode)) {
				throw new Exception("画像の反転処理に失敗しました");
			}
		}

		if ($degrees > 0) {
			$rotated = @imagerotate($image, $degrees, 0);
			if (!$rotated) {
				throw new Exception("画像の回転処理に失敗しました");
			}
			imagedestroy($image);
			$image = $rotated;
		}

		if (!imagejpeg($image, $filename)) {
			throw new Exception("画像の保存に失敗しました");
		}

		imagedestroy($image);

	} catch (Exception $e) {
		error_log("画像処理エラー: " . $e->getMessage());
		throw $e; // 呼び出し元で処理させたい場合
	}
}
    

function putReducingPics($img,$uri,$ratio){

	list($original_width, $original_height) = getimagesize($img);

	$return_width = $original_width * $ratio;
	$return_height = $original_height * $ratio;
	
	$proportion = $original_width / $original_height;

	if($proportion == 1){
		//正方形のとき
		$new_height = $return_height;
		$new_width = $return_width;
	}elseif($proportion > 1){
		//横長のとき
		$new_width = $return_width;
		$new_height = round($return_width / $proportion ,2);	
	}elseif($proportion < 1){
		//縦長のとき
		$new_height = $return_height;
		$new_width = round($return_height * $proportion ,2);
	}
    
    $orientationFlag = false;
    $exif_data = exif_read_data($img);
   if(is_array($exif_data) && array_key_exists('Orientation',$exif_data)){
        $orientation = $exif_data['Orientation'];
        $orientationFlag = true;
    }
    
	try{
        if($orientationFlag){
            imageOrientation($img,$orientation);
        }
		$original_image = ImageCreateFromJPEG($img); //JPEGファイルを読み込む
		$thum = ImageCreateTrueColor($new_width, $new_height); // 画像作成

		ImageCopyResampled($thum,$original_image,0,0,0,0,$new_width,$new_height,$original_width,$original_height);
		imagejpeg($thum,$uri,60); //画像圧縮&&保存

		imagedestroy($original_image);
		imagedestroy($thum);
		
		return true;
	}catch(Exception $e){
		return false;
	}
    
    
    
}



?>
