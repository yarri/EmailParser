<?php
namespace Yarri;

require_once(__DIR__ . "/pear/Mail/mimeDecode.php");

/*
function set_input(&$input)
function _set_input_by_id($user_id,$email_id)
function get_email($user_id,$email_id)

function get_part($id) //vraci cely zaznam ze struct vcetne tela (! oprava!!! telo jenom nekdy)
function get_body($id) //vraci telo
*/
class ParsedEmail {

	const VERSION = 0.1;

	/* DEKLARACE HODNOT NUTNYCH PRO EMAIL: ZACATEK */
	var $attachment = false;
	var $first_readable_text = false;
	var $size = 0;

	var $headers = array(
		"reply_to" => "",
		"return_path" => "",
		"reply_to" => "",
		"return_path" => "",
		"from" => "",
		"sender" => "",
		"to" => "",
		"cc" => "",
		"bcc" => "",
		"subject" => "",
		"date" => NULL,
		"message_id" => "",
		"in_reply_to" => "",
		"references" => "",
		"list_id" => "",
		"mailing_list" => "",
		"x_mailinglist" => "",
		"list_post" => "",
		"list_help" => "",
		"list_unsubscribe" => "",
		"list_subscribe" => "",
		"x_priority" => NULL,
		"x_smile" => NULL,
		"x_spam_status" => NULL, //"Y","N",NULL
		"x_spamdetected" => "", //"0","1" to nastavuje centrum.cz
	);
	
	//vytazene hlavicky, jsou nakonec spojeny odkazem do pole $this->headers
	var $reply_to = "";
	var $return_path = "";
	var $from = "";
	var $sender = "";
	var $to = "";
	var $cc = "";
	var $bcc = "";
	var $subject = "";
	var $date = NULL;
	var $message_id = "";
	var $in_reply_to = "";
	var $references = "";
	var $list_id = "";
	var $mailing_list = "";
	var $x_mailinglist = "";
	var $list_post = "";
	var $list_help = "";
	var $list_unsubscribe = "";
	var $list_subscribe = "";
	var $x_priority = NULL;
	var $x_smile = NULL;
	var $x_spam_status = NULL;
	var $x_spamdetected = "";

	var $received_from_host = "";
	
	//struktura celeho emailu, mimo hlavicek
	var $struct = array();
	/* DEKLARACE HODNOT NUTNYCH PRO EMAIL: KONEC */
	var $bodies = array(); // pak se uklada do cache!!!
	
	var $input = "";

	var $id_counter = 1;
	var $level_counter = 0;

	var $email_id = null;
	var $user_id = null;

	var $mail_parser_version = self::VERSION;
	var $mail_cache_dir = MAIL_CACHE_DIR;

	protected $parser;

	function __construct(EmailParser $parser){
		$this->parser = $parser;

		//napojeni hlavicek na odkazy do $this->headers
		$this->reply_to = &$this->headers["reply_to"];
		$this->return_path = &$this->headers["return_path"];
		$this->from = &$this->headers["from"];
		$this->sender = &$this->headers["sender"];
		$this->to = &$this->headers["to"];
		$this->cc = &$this->headers["cc"];
		$this->bcc = &$this->headers["bcc"];
		$this->date = &$this->headers["date"];
		$this->subject = &$this->headers["subject"];
		$this->message_id = &$this->headers["message_id"];
		$this->references = &$this->headers["references"];
		$this->in_reply_to = &$this->headers["in_reply_to"];
		$this->list_id = &$this->headers["list_id"];
		$this->mailing_list = &$this->headers["mailing_list"];
		$this->x_mailinglist = &$this->headers["x_mailinglist"];
		$this->list_post = &$this->headers["list_post"];
		$this->list_help = &$this->headers["list_help"];
		$this->list_unsubscribe = &$this->headers["list_unsubscribe"];
		$this->list_subscribe = &$this->headers["list_subscribe"];
		$this->x_priority = &$this->headers["x_priority"];
		$this->x_smile = &$this->headers["x_smile"];
		$this->x_spam_status = &$this->headers["x_spam_status"];
		$this->x_spamdetected = &$this->headers["x_spamdetected"];
	}

	function set_input(&$input){
		$this->_reset();
		$this->_set_input($input);
	}

	function set_input_by_email_obj($email){
		$this->_set_input_by_id($email->getUserId(),$email->getId());
	}

	function _set_input_by_id($user_id,$email_id){
		$this->_reset();
		$this->user_id = $user_id;
		$this->email_id = $email_id;
		$email_filename = $this->_getEmailFilename($email_id);
		if(!file_exists($email_filename)){
			$content = "";
			$this->_set_input($content);
			return;
		}
		$content = Files::GetFileContent($email_filename,$err,$err_str);
		myAssert(!$err,$err_str);
		if(preg_match('/\.gz$/',$email_filename)){
			$content = gzdecode($content);
			myAssert($content!==false,"gzdecode failed");
		}
		$this->_set_input($content);
		return;
	}

	function _set_input(&$input){
		//preparing object's values
		//$this->_reset();
		$this->size = strlen($input);

		$params = array(
			'input'          => &$input,
			'crlf'           => "\n",
			'include_bodies' => TRUE,
			'decode_headers' => TRUE,
			'decode_bodies'  => TRUE
		);
		
		$Mail_mimeDecode = new \Mail_mimeDecode($input,"\n");

		$structure = $Mail_mimeDecode->decode($params);

		//myslim, ze bude dobre po padu Mail_mimeDecode, prhlasit email za text/plain a zobrazit! :)
		//nebo alespon vyparsovat hlavicky!

		//if($Mail_mimeDecode->_error){
		//echo $Mail_mimeDecode->_error;
		//exit;
		//}
		
		$first = true;
		foreach($structure->headers as $key => $value){
			
			if(is_array($value)){
				$value = array_map(function($item){ return \Yarri\Utf8Cleaner::Clean($item); },$value);
				$this->headers[$key] = $value;
				continue;
			}

			$key = trim($key);
			$key = strtolower($key);
			$value = trim($value);
			$value = \Yarri\Utf8Cleaner::Clean($value);

			$this->headers[$key] = $value;
			continue;

			//$this->headers .= "$key: ".$value;

			$KEY = strtoupper($key);

			if($KEY == "FROM"){
				$this->from = trim($value);
			}

			if($KEY == "SUBJECT"){
				$this->subject = trim($value);
			}
			if($KEY =="TO"){
				$this->to = trim($value);
			}
			if($KEY == "CC"){
				$this->cc = trim($value);
			}
			if($KEY == "BCC"){
				$this->bcc = trim($value);
			}
			if($KEY == "DATE"){
				$_temp = date("Y-m-d H:i:s",strtotime($value));
				if($_temp){
					$this->date = $_temp;
				}
			}

			if($KEY == "MESSAGE-ID"){
				$this->message_id = trim($value);
			}

			if($KEY == "REFERENCES"){
				$this->references = trim($value);
			}

			if($KEY == "IN-REPLY-TO"){
				$this->in_reply_to = trim($value);
			}

			if($KEY == "REPLY-TO"){
				$this->reply_to = trim($value);
			}

			if($KEY == "RETURN-PATH"){
				$this->return_path = trim($value);
			}


			if($KEY == "LIST-ID"){
				$this->list_id = trim($value);
			}
			if($KEY == "MAILING-LIST"){
				$this->mailing_list = trim($value);
			}
			if($KEY == "X-MAILINGLIST" || $KEY == "X-MAILING-LIST"){
				$this->x_mailinglist = trim($value);
			}

			if($KEY == "LIST-POST"){
				$this->list_post = trim($value);
			}
			if($KEY == "LIST-HELP"){
				$this->list_help = trim($value);
			}
			if($KEY == "LIST-UNSUBSCRIBE"){
				$this->list_unsubscribe = trim($value);
			}
			if($KEY == "LIST-SUBSCRIBE"){
				$this->list_subscribe = trim($value);
			}

			if($KEY == "X-PRIORITY"){
				$this->x_priority = (int) (trim($value) + 0);
				settype($this->x_priority,"integer");
			}
			if($KEY == "X-SMILE"){
				$this->x_smile = (int) (trim($value) + 0);
				settype($this->x_smile,"integer");
			}
			if($KEY == "RECEIVED"){
				if(preg_match('/^\\([^\\)]+from network\\);[^;]+from[^;]*?\\(([0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3})\\)/',trim($value),$pieces)){
					$this->received_from_host = $pieces[1];
					//echo $this->received_from_host;
				}
				//echo $value;
				//exit;
			}
			if($KEY == "X-SPAM-STATUS"){
				if(preg_match('/^yes/i',trim($value))){
					$this->x_spam_status = "Y";
				}elseif(preg_match('/^no/i',trim($value))){
					$this->x_spam_status = "N";
				}	
			}

			if($KEY == "X-SPAMDETECTED"){
				$this->x_spamdetected = trim($value);
				//ocekavame, ze hodnota bude 0 nebo 1
				if(strlen($this->x_spamdetected)>1){
					//pokud se tato hlavicka objevi ve zprave vicekrat, budeme uvazovat pouze prvni vyskyt
					$this->x_spamdetected = $this->x_spamdetected[0];
				}
			}
		}

		//kraceni vsech hlavicek na 1000 znaku
		/*
		foreach($this->headers as $key => $value){
			if(strlen($this->headers[$key])>1000){
				$this->headers[$key] = substr($this->headers[$key],0,1000);
			}
		}
		*/
		
		$this->fill_struct($structure);
		//NOVINKA - TOHLE MA NAPR. ZACHYTIT MEIL, KTERY MA JENOM text/html
		if(is_bool($this->first_readable_text) && $this->first_readable_text==false && sizeof($this->struct)>0){
			$this->attachment = true;
		}
		//var_dump($this->struct);
	}

	function get_email($user_id,$email_id){
		//getting from cache
		$this->_reset();
		
		$this->user_id = $user_id;
		$this->email_id = $email_id;


		$cache_stat = $this->_get_cache();
		if($cache_stat){
			return;
		}
		$this->_set_input_by_id($user_id,$email_id);

		$this->_put_cache();
	}

	function _reset(){
		$this->attachment = false;
		$this->first_readable_text = false;
		$this->size = 0;
		
		$this->headers["reply_to"] = "";
		$this->headers["return_path"] = "";
		$this->headers["from"] = "";
		$this->headers["sender"] = "";
		$this->headers["to"] = "";
		$this->headers["cc"] = "";
		$this->headers["bcc"] = "";
		$this->headers["date"] = NULL;
		$this->headers["subject"] = "";
		$this->headers["message_id"] = "";
		$this->headers["references"] = "";
		$this->headers["in_reply_to"] = "";
		$this->headers["list_id"] = "";
		$this->headers["mailing_list"] = "";
		$this->headers["x_mailinglist"] = "";
		$this->headers["list_post"] = "";
		$this->headers["list_help"] = "";
		$this->headers["list_unsubscribe"] = "";
		$this->headers["list_subscribe"] = "";
		$this->headers["x_priority"] = NULL;
		$this->headers["x_smile"] = NULL;
		$this->headers["x_spam_status"] = NULL;

		$this->received_from_host = "";

		$this->struct = array();

		$this->bodies = array();

		$this->id_counter = 1;
		$this->level_counter = 0;

		$this->user_id = null;
		$this->email_id = null;
	}


	function fill_struct(&$structure){
		$this->level_counter++;
		$object = true;// :-) - TO JE VZDYCKU

		$mime_type = "";
		$charset = "";
		$name = "";
		$level = $this->level_counter;
		$id = false;
		$body = "";
		$size = 0;
		$content_id = "";
		if($object){
			//var_dump(array_keys(get_object_vars($structure)));
			//var_dump($structure->headers);
			if(isset($structure->ctype_primary) && isset($structure->ctype_secondary)){
				$mime_type = strtolower(trim($structure->ctype_primary)."/".trim($structure->ctype_secondary));
			}
			if(isset($structure->ctype_parameters["name"]) && $name==""){
				$name = correct_filename($structure->ctype_parameters["name"]);
			}
			if(isset($structure->d_parameters["filename"]) && $name==""){
				$name = correct_filename($structure->d_parameters["filename"]);
			}
			if(isset($structure->ctype_parameters["charset"])){
				$charset = strtolower(trim($structure->ctype_parameters["charset"]));
			}
			if(isset($structure->content_id)){
				$content_id = $structure->content_id; // TODO: toto tu je z puvodniho listonose... nastane to nekdy? :)
			}elseif(isset($structure->headers["content-id"])){
				$content_id = $structure->headers["content-id"];
			}
			
			$_body = "";
			$body_included = false;

			if(isset($structure->body)){
				$size = strlen($structure->body);
				$id = $this->id_counter;
				if(!$this->first_readable_text && ($mime_type == "text/plain")){
					$this->first_readable_text = $this->id_counter;
					$first_readable_text_set = true;
				}
				if($size<10000){
					$body_included = true;
					$_body = &$structure->body;
				}else{
					//telo ma cenu ukladat do cache, jenom, kdyz neni included
					$this->bodies["$id"] = &$structure->body;
				}

				/*
				if($mime_type == "text/plain" && $charset!="" && $charset!=DEFAULT_CHARSET){
					//PREKLAD JAZYKA
					//bude nutne vyrobit novy soubor (original zachovat)
					$body = translate::cs2cs($body,$charset,DEFAULT_CHARSET);
					$new_body = false;
					if($new_body){
						$body = $new_body;
						$charset = $this->charset;
					}	
				}
				*/

				$this->id_counter++;
			}

			$cache_file = "";
			if($body_included && !is_null($this->email_id)){
				$cache_file = $this->_getCacheFilenameForPart($id);
			}

			$this->struct[] = array(
				"mime_type" => $mime_type,
				"charset" => $charset,
				"name" => $name,
				"content_id" => $content_id,
				"level" => $this->level_counter,
				"id" => $id,
				"body_included" => $body_included,
				"body" => $_body,
				"size" => $size,

				// TODO: na novem listonosovi se cache_file nastavuje zcela nove tady
				"cache_file" => $cache_file
			);

			//assert(!!$this->user_id);
			//assert(!!$this->email_id);
						
			if(isset($structure->parts)){
				$this->attachment = true;
				for($i=0;$i<sizeof($structure->parts);$i++){
					$this->fill_struct($structure->parts[$i]);
				}
			}
		}
		$this->level_counter--;
	}

	function get_part($id){
		for($i=0;$i<sizeof($this->struct);$i++){
			if($this->struct[$i]["id"] == $id){

				// TODO: na starem listonosovi se cache_file nastavovalo tady, moc tomu nerozumim...
				if(!$this->struct[$i]["body_included"] && !$this->struct[$i]["cache_file"]){
					$this->struct[$i]["cache_file"] = $this->_getCacheFilenameForPart($id);
					//assert(!!$this->user_id);
					//assert(!!$this->email_id);
				}
				return $this->struct[$i];
			}
		}
		return false;
	}

	function get_body($id){
		for($i=0;$i<sizeof($this->struct);$i++){
			if($this->struct[$i]["id"] == $id){
				if($this->struct[$i]["body_included"]){
					return $this->struct[$i]["body"];
				}else{
					$cache_file = $this->_getCacheFilenameForPart($id);
					$body = Files::GetFileContent($cache_file);
					return $body;
				}
			}
		}
		return "";
	}

	function _put_cache(){
		$out = false;
		if($this->user_id==0 || $this->email_id==0){
			return $out;
		}
		$user_id = $this->user_id;
		$email_id = $this->email_id;

		$cache_dir = $this->_getCacheDir();
		$this->_remove_cache_files($user_id,$email_id);

		if(!file_exists($cache_dir)){
			$error = false;
			$error_str = "";
			$stat = Files::Mkdir($cache_dir,$error,$error_str);
			if($error){
				//ERROR: nepodarilo se vytvorit cache adresar pro uzivatele
				return $out;
			}
		}
		
		myAssert(file_exists($cache_dir));
	
		foreach($this->bodies as $body_id => $_body){
			$body_cache_filename = $this->_getCacheFilenameForPart($body_id);
			$__error = false;
			$__error_str = "";
			Files::MkdirForFile($body_cache_filename,$_error,$__error_str);
			Files::WriteToFile($body_cache_filename,$this->bodies[$body_id],$__error,$__error_str);
		}

		$cache = serialize(array(
				"mail_parser_version" => $this->mail_parser_version,
				"headers" => $this->headers,
				"struct" => $this->struct,
				"attachment" => $this->attachment,
				"first_readable_text" => $this->first_readable_text,
				"size" => $this->size,
				"received_from_host" => $this->received_from_host
			)
		);
		$cache_file = $cache_dir."cache";
		/*
		$f = fopen($cache_file,"w");
		fwrite($f,$cache,strlen($cache));
		fclose($f);
		chmod($cache_file,0777);
		*/

		$__error = false;
		$__error_str = "";
		Files::WriteToFile($cache_file,$cache,$__error,$__error_str);
	}

	function _get_cache(){
		/*
		$out = array(
			"cache_valid" => false,
			"struct" => array(),
			"headers" => array(),
			"first_readable_text" => false,
			"size" => 0,
			"attachment" => false
		);
		*/
		$out = false;
		if($this->user_id==0 || $this->email_id==0){
			return $out;
		}

		$user_id = $this->user_id;
		$email_id = $this->email_id;
		$cache_file = $this->_getCacheFilename();
		//assert(!!$user_id);
		//assert(!!$email_id);
		if(!file_exists($cache_file)){
			return $out;
		}
		$f = fopen($cache_file,"r");
		$_out = unserialize(fread($f,filesize($cache_file)));
		fclose($f);
		if(!is_array($_out)){
			return $out;
		}
		if($_out["mail_parser_version"]!=$this->mail_parser_version){
			return $out;
		}
		
		//extracting cache
		$this->struct = $_out["struct"];
		$this->first_readable_text = $_out["first_readable_text"];
		$this->size = $_out["size"];
		$this->attachment = $_out["attachment"];
		foreach($_out["headers"] as $key => $value){
			$this->headers[$key] = $value;
		}
		$this->received_from_host = $_out["received_from_host"];
		return true;
	}

	function _remove_cache_files($user_id,$email_id){
		$cache_dir = $this->_getCacheDir($email_id);
		if(!file_exists($cache_dir)){
			return;
		}
		$this->_unlink_dir($cache_dir);
	}

	function _unlink_dir($dir){
		if($dir==""){
			return;
		}
		Files::RecursiveUnlinkDir($dir);
	}

	function get_headers(){
		$out = "";
		if($this->user_id!=0 && $this->email_id!=0){
			$email_filename = $this->_getEmailFilename($this->email_id);
			$f = fopen($email_filename,"r");
			while((!feof($f) && $f) || strlen($out)<81920){
				$line = fgets($f,4096);
				if(trim($line)==""){
					break;
				}
				$out .= $line;
			}
			fclose($f);
		}
		return $out;
	}

	function _getEmailObj($email_id = null){
		if(is_null($email_id)){
			$email_id = $this->email_id;
		}
		return Cache::Get("Email",$email_id);
	}


	function _getEmailFilename($email_id = null){
		$email = $this->_getEmailObj($email_id);
		return $email->getSourceFilename();
	}

	function _getCacheDir($email_id = null){
		$email = $this->_getEmailObj($email_id);
		return $email->getCacheDir();
	}

	function _getCacheFilename($email_id = null){
		$email = $this->_getEmailObj($email_id);
		return $email->getCacheFilename();
	}

	function _getCacheFilenameForPart($number,$email_id = null){
		$email = $this->_getEmailObj($email_id);
		return $email->getCacheFilenameForPart($number);
	}

	function getHeader($key,$options = []){
		$options += [
			"as_array" => false,
		];

		$key = strtolower($key);
		if(!isset($this->headers[$key])){ return $options["as_array"] ? [] : null; }
		$header = $this->headers[$key];
		if(!$options["as_array"] && is_array($header)){
			$header = join("\n",$header);
		}
		if($options["as_array"] && !is_array($header)){
			$header = [$header];
		}
		return $header;
	}

	function getParts(){
		$parts = [];
		foreach($this->struct as $struct){
			$parts[] = new ParsedEmailPart($this,$struct);
		}
		return $parts;
	}
}
