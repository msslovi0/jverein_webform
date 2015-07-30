<?php
require_once ('smarty3/Smarty.class.php');
$smarty = & new Smarty;


if (file_exists ( './pending_members/'.$_GET['id'].".csv")){
rename('./pending_members/'.$_GET['id'].".csv",'./new_members/'.$_GET['id'].".csv");
chmod('./new_members/'.$_GET['id'].".csv",0660);
$message="Deine Mitgliedschaftsantrag wurde bestätigt. Wir bearbeiten diesen so schnell wie möglich...";

/*load member data*/
$tmp=file('./new_members/'.$_GET['id'].".csv");
$member_data=str_getcsv($tmp[1], ';');
unset($tmp);

/* Send mail to vorstand@*/
require_once ('config/mailconfig.inc.php');
require_once('Mail.php');

$headers['From']    = 'vorstand@freifunk-mainz.de';
$headers['To']      = 'vorstand@freifunk-mainz.de';
$headers['Subject'] = 'Ein neues Mitglied möchte zum '.$member_data[22]." aufgenommen werden";


$mail_object =& Mail::factory('smtp', $mail_config);
$mail_object->send('vorstand@freifunk-mainz.de', $headers, "Name: ".$member_data[5]." ".$member_data[4]);


}
elseif (file_exists ( './new_members/'.$_GET['id'].".csv")){
$message="Deine Mitgliedschaftsantrag ist bereits bei uns eingetroffen. Bitte gib uns noch etwas Zeit den Antrag zu bearbeiten.";
}
else{
$message="Falsche Id angegeben.";
}

$smarty->assign('message', $message);
$smarty->display('confirm.tpl');


?>
