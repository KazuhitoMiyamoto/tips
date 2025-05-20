<?php
session_start();
include('include.php');

define('MAX_CAPACITY', 50);

$master_mail = MASTER_MAIL;

//try~catchで例外が発生したらここに格納。
$eMsgk = "";

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

if(!is_ajax()){
    $success = false;
	$eMsg = 'アクセスエラーです。再度入力をお願いします。';
	$result =  array('success'=>$success, 'eMsg'=>$eMsg);
	$json = json_encode($result);
	echo $json;
	exit();
}

$_POST = array_map('h',$_POST);

$allowed_keys = ['firstName',
'lastName',
'firstNameKana',
'lastNameKana',
'phone',
'mail',
'birth',
'address',
'sex',
'emer',
'notice',
'parName',
'school',
'grade',
'aid',
'bloodtype',
'disName',
'detailNotice',
'detailDescription'];

foreach ($allowed_keys as $key) {
    if (isset($_POST[$key])) {
        $$key = $_POST[$key];
    }
}

$gender = showSex($sex);
$s_bloodtype = showBloodtype($bloodtype);

$name = $firstName . ' ' .$lastName;
$nameKana = $firstNameKana . ' ' . $lastNameKana;


$overCapactyFlag = false;
$appSuccessFlag = false;


try{
    $pdo = openConnect2025();
    $pdo->beginTransaction();

	// カウンターの行をロック
	$stmt = $pdo->prepare("SELECT current_count FROM entry_status WHERE id = 1 FOR UPDATE");
	$stmt->execute();
	$count = $stmt->fetchColumn();
	
	if ($count >= MAX_CAPACITY) {
	    if ($pdo && $pdo->inTransaction()) {
		$pdo->rollBack();
	    }
		$overCapactyFlag = true;
	    throw new Exception("定員オーバー");
	}

// challenger テーブルに追加
	
	$sql = "INSERT INTO `challenger` (`fName`, `lName`, `fNameKana`, `lNameKana`, `school`, `grade`, `gender`,`bloodtype`,`birthday`) VALUES " . "(:fName,:lName,:fNameKana,:lNameKana,:school,:grade,:gender,:bloodtype,:birthday)";
	$stmt = $pdo->prepare($sql);
	
	$insert = array();
	
	$insert = array(
	    ':fName' => $firstName,
	    ':lName' => $lastName,
	    ':fNameKana' => $firstNameKana,
	    ':lNameKana' => $lastNameKana,
	    ':school' => $school,
	    ':grade' => $grade,
	    ':gender' => $sex,
		':bloodtype' => $bloodtype,
		':birthday' => $birth
	);
	
	$stmt->execute($insert);
	$id = $pdo->lastInsertId();   
	
	$insert = array();
	
	//  detail テーブルに追加
	
	$sql = "INSERT INTO `detail`(`cid`,`mailAddress`, `pname`, `address`, `tel`, `emer`, `pnotice`) VALUES (:cid,:mail,:parName,:address,:phone,:emer,:detailNotice)";
	$stmt = $pdo->prepare($sql);
	
	$insert = array(
	    ':cid' => $id,
	    ':mail' => $mail,
	    ':parName' => $parName,
	    ':address' => $address,
	    ':phone' => $phone,
	    ':emer' => $emer,
	    ':detailNotice' => $detailNotice
	);
	
	$stmt->execute($insert);
	
	// カウント更新
	$stmt = $pdo->prepare("UPDATE entry_status SET current_count = current_count + 1 WHERE id = 1");
	$stmt->execute();
	
	$pdo->commit();
	
	$appSuccessFlag = true;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
    	$pdo->rollBack();
    }
	$eMsgk = $e->getMessage();
}




if($appSuccessFlag){


	$content = <<<H
	氏名：$name
	氏名（カナ）：$nameKana
	氏名(保護者)：$parName
	誕生日：$birth
	血液型：$bloodtype
	性別：$sex
	学校・学年：$grade 年生  $school
	電話番号：$phone
	緊急連絡先：$emer
	メール：$mail
	住所：$address


	質問やご不安に思うこと：$detailNotice
		
H;


	$content4parent = <<<h

	$parName 様

$successTextForParents

$content


	※登録の覚えがない場合
	間違えて登録されてしまった可能性があります。
	大変お手数をおかけしますが、そのまま御返信頂ければ幸いです。

$mail_footer

h;

	$content4webmaster = <<<g

	お疲れ様です。
	下記、登録がありました。
	検索コード【CcGGfd1Um】

	登録番号：　$id
	アクセス番号：　$aid

$content

$mail_footer
g;


	$subject4parent = 'お申込みありがとうございます。';
	$subject4webmaster = '申込がありました【'. $name .'さん】';


	$from_name = FROM_NAME;

	$sendmail2parent = sendMail($mail, $subject4parent, $content4parent, $FROM_MAIL,$from_name);
	$sendmail2webmaster = sendMail($master_mail, $subject4webmaster, $content4webmaster, $FROM_MAIL,$from_name);
	
	logMailSend($mail, $subject4parent, $content4parent, $FROM_MAIL, $sendmail2parent ? 'success' : 'fail', $err, 'Cparent');
	logMailSend($master_mail, $subject4webmaster, $content4webmaster, $FROM_MAIL, $sendmail2webmaster ? 'success' : 'fail', $err, 'Cwebmaster');


	if($sendmail2parent && $sendmail2webmaster){
		$success = true;
		$html = <<<h
			<div id="successReg">
				<p>お申し込みを受け付けました。</p>
				<p>ご登録頂いたメールアドレスにメールを送信しましたので、ご確認下さい。</p>
				<p>メールを受信できない方は、ウェブサイトの下部にある問い合わせフォームでお問い合わせください。</p>
				<p>その際に連絡先の電話番号をご記入頂ければ、スムーズにご対応できるかと思います。</p>
				<p></p>
			</div>
	h;
		$result =  array('success'=>$success, 'html'=>$html);
		$json = json_encode($result);
		echo $json;
		exit();
	}



}elseif($overCapactyFlag === true){

	
	$content = <<<H
	氏名：$name
	氏名（カナ）：$nameKana
	氏名(保護者)：$parName
	誕生日：$birth
	血液型：$bloodtype
	性別：$sex
	学校・学年：$grade 年生  $school
	電話番号：$phone
	緊急連絡先：$emer
	メール：$mail
	住所：$address


	質問やご不安に思うこと：$detailNotice
		
H;


	$content4parent = <<<h

	$parName 様

$capOverTextForParents

$content


	※登録の覚えがない場合
	間違えて登録されてしまった可能性があります。
	大変お手数をおかけしますが、そのまま御返信頂ければ幸いです。

	$mail_footer

h;





	$content4webmaster = <<<g

	お疲れ様です。
	定員オーバー（ギリギリで申し込みがあった人です。）
	検索コード【CcGGfd1Um】

	登録番号：　$id

	$content

	$mail_footer
g;


	$subject4parent = '【※定員オーバー】お申込みありがとうございます。';
	$subject4webmaster = '【※定員オーバー】申込がありました【'. $name .'さん】';


	$from_name = 'チャリチャレプロジェクト宮本';

	$sendmail2parent = sendMail($mail, $subject4parent, $content4parent, $FROM_MAIL,$from_name);
	$sendmail2webmaster = sendMail($master_mail, $subject4webmaster, $content4webmaster, $FROM_MAIL,$from_name);
	
	logMailSend($mail, $subject4parent, $content4parent, $FROM_MAIL, $sendmail2parent ? 'success' : 'fail', $err, 'Cparent');
	logMailSend($master_mail, $subject4webmaster, $content4webmaster, $FROM_MAIL, $sendmail2webmaster ? 'success' : 'fail', $err, 'Cwebmaster');

	if($sendmail2parent && $sendmail2webmaster){
		$success = true;
		$html = <<<h
			<div id="successReg">
				<p>申し訳ありませんが、定員オーバーになってしまい、申し込みを受け付けることができませんでした。</p>
			</div>
	h;
		$result =  array('success'=>$success, 'html'=>$html);
		$json = json_encode($result);
		echo $json;
		exit();
	}



}else{
	$success = false;
    if($eMsgk === ""){
    	$eMsg = '何か問題が発生したようです。お手数ですが、再度ご登録をお願いします。';
    }else{
        $eMsg = $eMsgk;
    }
	$result =  array('success'=>$success, 'eMsg'=>$eMsg);
	$json = json_encode($result);
	echo $json;
	exit();
}

?>
