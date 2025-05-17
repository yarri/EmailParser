<?php
namespace Yarri\EmailParser;

require_once(__DIR__ . "/../pear/Mail/mimeDecode.php");

/*
function set_input(&$input)
function _set_input_by_id($user_id,$email_id)
function get_email($user_id,$email_id)

function get_part($id) //vraci cely zaznam ze struct vcetne tela (! oprava!!! telo jenom nekdy)
function get_body($id) //vraci telo
*/
class ParsedEmail {

	/* DEKLARACE HODNOT NUTNYCH PRO EMAIL: ZACATEK */
	var $attachment = false;
	var $first_readable_text = false;
	var $size = 0;

	var $headers = [];
	
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

	var $mail_parser_version = \Yarri\EmailParser::VERSION;
	var $mail_cache_dir = MAIL_CACHE_DIR;

	protected $parser;

	function __construct(\Yarri\EmailParser $parser){
		$this->parser = $parser;
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
		
		$Mail_mimeDecode = new \__Mail_mimeDecode($input,"\n");

		$structure = $Mail_mimeDecode->decode($params);

		if(!$structure || !$structure->headers || (sizeof($structure->headers)===1 && isset($structure->headers[""]))){
			throw new InvalidEmailSourceException();
		}

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


		$this->headers = [];

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

		$declared_mime_type = "";
		$charset = "";
		$name = "";
		$level = $this->level_counter;
		$id = false;
		$has_content = false;
		$body = null;
		$size = 0;
		$content_id = null;
		if($object){
			//var_dump(array_keys(get_object_vars($structure)));
			//var_dump($structure->headers);
			if(isset($structure->ctype_primary) && isset($structure->ctype_secondary)){
				$declared_mime_type = strtolower(trim($structure->ctype_primary)."/".trim($structure->ctype_secondary));
			}
			if(isset($structure->ctype_parameters["name"]) && $name==""){
				$name = $this->_correctFilename($structure->ctype_parameters["name"]);
			}
			if(isset($structure->d_parameters["filename"]) && $name==""){
				$name = $this->_correctFilename($structure->d_parameters["filename"]);
			}
			if(isset($structure->ctype_parameters["charset"])){
				$charset = strtolower(trim($structure->ctype_parameters["charset"]));
			}
			if(isset($structure->content_id)){
				$content_id = $structure->content_id; // TODO: toto tu je z puvodniho listonose... nastane to nekdy? :)
			}elseif(isset($structure->headers["content-id"])){
				$content_id = $structure->headers["content-id"];
			}
			
			$_body = null;
			$body_included = false;

			$id = $this->id_counter;

			if(isset($structure->body)){
				$has_content = true;
				$size = strlen($structure->body);
				if(!$this->first_readable_text && ($declared_mime_type == "text/plain")){
					$this->first_readable_text = $this->id_counter;
					$first_readable_text_set = true;
				}

				// TODO: ???
				//if($size<10000){
					$body_included = true;
					$_body = &$structure->body;
				//}else{
				//	//telo ma cenu ukladat do cache, jenom, kdyz neni included
				//	$this->bodies["$id"] = &$structure->body;
				//}
			}

			$this->id_counter++;

			$cache_file = "";
			if($body_included && !is_null($this->email_id)){
				$cache_file = $this->_getCacheFilenameForPart($id);
			}

			$mime_type = $declared_mime_type;
			if($declared_mime_type && !(strlen($name)===0 && strlen($_body)===0) && !(in_array($declared_mime_type,["text/plain","text/html"]) && !strlen($name))){
				$_file = \Files::WriteToTemp($_body);
				$mime_type = \Files::DetermineFileType($_file,["original_filename" => $name]);
				\Files::Unlink($_file);
				$mime_type = $mime_type ? $mime_type : $declared_mime_type;
			}

			$this->struct[] = array(
				"mime_type" => $mime_type,
				"declared_mime_type" => $declared_mime_type,
				"charset" => $charset,
				"name" => $name,
				"content_id" => $content_id,
				"level" => $this->level_counter,
				"id" => $id,
				"has_content" => $has_content,
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

	function getPartById(int $id){
		foreach($this->getParts() as $part){
			if($part->getId()===$id){ return $part; }
		}
	}

	function getPartByContentId(string $content_id){
		foreach($this->getParts() as $part){
			if($part->getContentId()===$content_id){ return $part; }
		}
	}

	function _correctFilename($filename){
		$filename = trim($filename);
		$filename = \Yarri\Utf8Cleaner::Clean($filename,"_");
		$filename = preg_replace("/[\\/\\\\]/",'_',$filename);
		$filename = preg_replace('/[\x00-\x1F\x7F]/','_',$filename);
		if(strlen($filename)>100){
			$filename = substr($filename,0,100);
		}
		if($filename===""){ $filename = "_"; }
		if($filename==="."){ $filename = "_"; }
		if($filename===".."){ $filename = "__"; }
		return $filename;
	}
}
