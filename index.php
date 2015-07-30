<?php
session_start();
require_once ('smarty3/Smarty.class.php');
require_once ('smarty3/SmartyValidate.class.php');
require_once ('include/class_obfuscator.php');
require_once ('include/php-iban.php');



function check_BIC($value, $empty, &$params, &$formvars) {
	return eregi("^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$", str_replace(' ', '', $value));
}
function check_ZIP($value, $empty, &$params, &$formvars) {
	return eregi("^([0-9]){5}$", $value);
}
function check_Date($value, $empty, &$params, &$formvars) {
	return eregi("^([0-9]){2}\/([0-9]){4}$", $value);
}

$smarty = & new Smarty;
$smarty->addPluginsDir('./smarty_plugins');
$smarty->force_compile = true;
$form_fields = array('firstname', 'lastname', 'street','addr_extra','zip','city','email','beitrag','nick','phone','pgpid','entry_date','account_owner','iban','bic','accept_satzung');
$obfuscator  = new Form_Obfuscator($form_fields);
$obfuscator	-> set_secret_key('BakOradIt7');

if( empty($_POST) ) {
	
	$fields 	 = $obfuscator	-> obfuscate();
	$enc_form = $obfuscator	-> encode_form();
	$_SESSION['fields']=$fields;
	$_SESSION['enc_form']=$enc_form;		
	SmartyValidate::connect($smarty, true);
	SmartyValidate::register_criteria('isValidZIP','check_ZIP');	
	SmartyValidate::register_criteria('isValidBIC','check_BIC');
	SmartyValidate::register_criteria('isValidIBAN','verify_iban');
	SmartyValidate::register_criteria('isValidEntryDate','check_Date');
        SmartyValidate::register_validator('firstname',$fields['firstname'],'notEmpty',false,false,'trim');
        SmartyValidate::register_validator('lastname',$fields['lastname'],'notEmpty',false,false,'trim');
        SmartyValidate::register_validator('street',$fields['street'],'notEmpty',false,false,'trim');
        SmartyValidate::register_validator('zip',$fields['zip'],'isValidZIP',false,false,'trim');
        SmartyValidate::register_validator('city',$fields['city'],'notEmpty',false,false,'trim');
        SmartyValidate::register_validator('email',$fields['email'],'isEmail',false,false,'trim');
		  SmartyValidate::register_validator('beitrag',$fields['beitrag'],'notEmpty',false,false,'trim');
        SmartyValidate::register_validator('iban',$fields['iban'],'isValidIBAN',false,false,'trim');
        SmartyValidate::register_validator('bic',$fields['bic'],'isValidBIC',false,false,'trim');
        SmartyValidate::register_validator('entry_date',$fields['entry_date'],'isValidEntryDate',false,false,'trim');
        SmartyValidate::register_validator('accept_satzung',$fields['accept_satzung'],'notEmpty',false,false,'trim');
        
        $smarty->assign('fields', $fields);
        $smarty->assign('enc_form', $enc_form);
        $smarty->display('index.tpl');
} else {
	
        SmartyValidate::connect($smarty);
        // validate after a POST
        if(SmartyValidate::is_valid($_POST)) {
        	// Load Mail_stuff
        	require_once ('include/mailconfig.inc.php');
        	require_once('Mail.php');
        	require_once('Mail/mime.php');
		$mimeparams['text_encoding']="8bit";
		$mimeparams['text_charset']="UTF-8";
		$mimeparams['html_charset']="UTF-8";
		
		
		$hash=base64_encode(password_hash(time().$form['firstname'].$form['lastname'],PASSWORD_BCRYPT));
        	
        	$mail_object =& Mail::factory('smtp', $mail_config);
        	$mime_object = new Mail_mime("\n");
        	$form = $obfuscator -> decode_form($_POST['__A'], $_POST);
        	$smarty->assign('form',$form);
        	$smarty->assign('url','http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/confirm.php?id='.$hash);
		
        	
        	$body_html = $smarty->fetch('email.tpl');
		
        	$headers['From']    = 'vorstand@freifunk-mainz.de';
        	$headers['To']      = $form['email'];
		$headers['Subject'] = 'Dein Mitgliedschaftsantrag bei Freifunk Mainz e.V.';
        	$mime_object->setHTMLBody($body_html);
		$mime_object->setTxtBody(ltrim(rtrim(html_entity_decode(strip_tags($body_html)))));
        	$body = $mime_object->get($mimeparams);
        	$hdrs = $mime_object->headers($headers);
        	
        	SmartyValidate::disconnect();
		
        	$f_handle=fopen('./pending_members/'.$hash.".csv",'w');    	
        	
        	fputs($f_handle, utf8_decode("Mitglieds_Nr;Personenart;Anrede;Titel;Nachname;Vorname;Adressierungszusatz;Strasse;Plz;Ort;Geburtsdatum;Geschlecht;BIC;IBAN;Bankleitzahl;Kontonummer;Mandat_Datum;Zahlungsart;Telefon_privat;Telefon_dienstlich;Email;Zahler;Eintritt;Beitragsart_1;Beitrag_1;Austritt;Kuendigung;pgpkeyid;nick;sonstiges;Sterbetag\n"));           
        	fputs($f_handle,utf8_decode(";".(($form['beitrag']==100)?"j":"n").";;;".$form['lastname'].";".$form['firstname'].";".$form['addr_extra'].";".$form['street'].";".$form['zip'].";".$form['city'].";;;".str_replace(' ','',$form['bic']).";".str_replace(' ','',$form['iban']).";;;".date("d.m.Y").";l;".$form['phone'].";;".$form['email'].";".$form['account_owner'].";"."01/".$form['entry_date'].";".(($form['beitrag']==100)? "Test":"Beitrag ".$form['beitrag']."€").";".$form['beitrag'].";;;".$form['pgpid'].";".$form['nick'].";;\n"));
        	fclose($f_handle);
        	
        	
        	$mail_object->send($form['email'], $hdrs, $body);
        	
		$message="Deine Daten sind bei uns angekommen. Für den nächsten Schritt prüfe bitte deine Emails und klicke auf den darin enthaltenen Bestätigungslink.";
		$smarty->assign('message', $message);
        	$smarty->display('success.tpl');
        } else {
        	// error, redraw the form
        	//print_r ($_POST);
        	$form = $obfuscator -> decode_form($_POST['__A'], $_POST);           
        	$smarty->assign('form',$form);
        	$smarty->assign('fields', $_SESSION['fields']);
        	$smarty->assign('enc_form', $_SESSION['enc_form']);
        	$smarty->display('index.tpl');
        }
}


?>
