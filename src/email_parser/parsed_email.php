<?php
namespace Yarri\EmailParser;

require_once(__DIR__ . "/../pear/Mail/mimeDecode.php");

class ParsedEmail {

	protected $parser;
	protected $cache_dir = ""; // path to a cache directory for the given email

	protected $size = null;
	protected $headers = [];
	protected $struct = [];
	protected $parts = [];

	protected $id_counter = 1;
	protected $level_counter = 0;

	protected $declared_charset = "";

	function __construct(\Yarri\EmailParser $parser, string $cache_dir = ""){
		$this->parser = $parser;
		$this->cache_dir = $cache_dir;
	}

	function setEmailSource(&$input){
		if($this->_readCache()){
			return;
		}
		$this->_setEmailSource($input);
	}

	function setEmailSourceByFile(string $filename){
		if($this->_readCache()){
			return;
		}
		$email_source = \Files::GetFileContent($filename,$err,$err_msg);
		if(preg_match('/\.gz/i',$filename)){
			$email_source = gzdecode($email_source);
		}
		return $this->setEmailSource($email_source);
	}

	function _setEmailSource(&$input){
		$this->_reset();

		$this->size = strlen($input);

		$params = [
			"input"          => &$input,
			"crlf"           => "\n",
			"include_bodies" => TRUE,
			"decode_headers" => TRUE,
			"decode_bodies"  => TRUE
		];
		
		$Mail_mimeDecode = new \__Mail_mimeDecode($input,"\n");

		$structure = $Mail_mimeDecode->decode($params);

		if(!$structure || !$structure->headers || (sizeof($structure->headers)===1 && isset($structure->headers[""]))){
			throw new InvalidEmailSourceException();
		}

		//print_r($structure);
		//exit;

		$this->_fillStruct($structure);

		$this->headers = $this->_parseHeaders($structure);

		$this->_writeCache();
	}

	function _reset(){
		$this->size = null;
		$this->headers = [];
		$this->struct = [];
		$this->parts = [];
		$this->id_counter = 1;
		$this->level_counter = 0;
	}

	function _fillStruct(&$structure){
		$this->level_counter++;
		$object = true;// :-) - TO JE VZDYCKU

		$declared_mime_type = null;
		$charset = null;
		$declared_charset = null;
		$name = null; // nazev soubodu
		$level = $this->level_counter;
		$id = false;
		$has_content = false;
		$body = null;
		$size = 0;
		$content_id = null;
		$headers = [];
		if($object){
			if(isset($structure->ctype_primary) && isset($structure->ctype_secondary)){
				$declared_mime_type = strtolower(trim($structure->ctype_primary)."/".trim($structure->ctype_secondary));
			}
			if(isset($structure->ctype_parameters["name"]) && $name==""){
				$name = self::_CorrectFilename($structure->ctype_parameters["name"],$this->declared_charset);
			}
			if(isset($structure->d_parameters["filename"]) && $name==""){
				$name = self::_CorrectFilename($structure->d_parameters["filename"],$this->declared_charset);
			}
			if(isset($structure->ctype_parameters["charset"])){
				$charset = strtolower(trim($structure->ctype_parameters["charset"]));
				$declared_charset = $charset;
			}
			if(isset($structure->content_id)){
				$content_id = $structure->content_id; // TODO: toto tu je z puvodniho listonose... nastane to nekdy? :)
			}elseif(isset($structure->headers["content-id"])){
				$content_id = $structure->headers["content-id"];
			}

			if(isset($structure->headers)){
				$headers = $this->_parseHeaders($structure);
			}
			
			$_body = null;
			$body_included = false;

			$id = $this->id_counter;

			if(isset($structure->body)){
				$has_content = true;
				$size = strlen($structure->body);

				$body_included = true;
				$_body = &$structure->body;

				if(preg_match('/^text\//',$declared_mime_type) && !$name){
					if($charset){
						$_body = \Translate::Trans($_body,$charset,"UTF-8");
					}
					$_body = \Yarri\Utf8Cleaner::Clean($_body);
					$charset = "UTF-8";
				}
			}

			$this->id_counter++;

			$mime_type = $declared_mime_type;
			if($declared_mime_type && $has_content && !(strlen($name)===0 && strlen($_body)===0) && !(in_array($declared_mime_type,["text/plain","text/html"]) && !strlen($name))){
				$_file = \Files::WriteToTemp($_body,$err,$err_msg);
				$mime_type = \Files::DetermineFileType($_file,["original_filename" => $name]);
				\Files::Unlink($_file);
				$mime_type = $mime_type ? $mime_type : $declared_mime_type;
			}

			if(in_array($mime_type,["text/plain","text/html"]) && !$this->declared_charset && $declared_charset && !$name){
				$this->declared_charset = $declared_charset;
			}

			$this->struct[] = [
				"mime_type" => $mime_type,
				"declared_mime_type" => $declared_mime_type,
				"charset" => $charset,
				"declared_charset" => $declared_charset,
				"name" => $name,
				"content_id" => $content_id,
				"level" => $this->level_counter,
				"id" => $id,
				"has_content" => $has_content,
				"body_included" => $body_included,
				"body" => $_body,
				"size" => $size,
				"headers" => $headers,
			];

			if(isset($structure->parts)){
				for($i=0;$i<sizeof($structure->parts);$i++){
					$this->_fillStruct($structure->parts[$i]);
				}
			}
		}
		$this->level_counter--;
	}

	function _writeCache(){
		$cache_dir = $this->_getCacheDir();
		if(!$cache_dir){ return null; }

		$uniqid = uniqid();

		if(!file_exists($cache_dir)){
			$stat = \Files::Mkdir($cache_dir,$err,$err_msg);
			if($err){ return false; }
		}
		
		foreach($this->struct as &$struct){
			if($struct["has_content"] && $struct["size"]>10000){
				$id = $struct["id"];
				$body_cache_filename = $this->_getCacheFilenameForPart($id);
				\Files::MkdirForFile($body_cache_filename,$err,$err_msg);
				\Files::WriteToFile("$body_cache_filename.$uniqid",$struct["body"],$err,$err_msg);
				\Files::MoveFile("$body_cache_filename.$uniqid",$body_cache_filename,$struct["body"],$err,$err_msg);
				if($err){ return false;}

				$struct["body_included"] = false;
				$struct["body"] = null;
			}
		}

		$cache = serialize([
			"email_parser_version" => \Yarri\EmailParser::VERSION,
			"size" => $this->size,
			"headers" => $this->headers,
			"struct" => $this->struct,
		]);

		$cache_file = $cache_dir."/cache.ser";
		\Files::WriteToFile("$cache_file.$uniqid",$cache,$err,$err_msg);
		\Files::MoveFile("$cache_file.$uniqid",$cache_file,$cache,$err,$err_msg);
		if($err){ return false; }

		return true;
	}

	function _readCache(){
		$cache_dir = $this->_getCacheDir();
		if(!$cache_dir){ return false; }

		if(!file_exists("$cache_dir/cache.ser")){ return false; }
		$cache_ser = \Files::GetFileContent("$cache_dir/cache.ser");
		$cache = unserialize($cache_ser);

		if(!is_array($cache)){ return false; }
		if(!isset($cache["email_parser_version"])){ return false; }
		if($cache["email_parser_version"]!==\Yarri\EmailParser::VERSION){ return false; }

		foreach($cache as $key => $value){
			if($key==="email_parser_version"){ continue; }
			$this->$key = $value; // $this->size, $this->headers...
		}
		
		return true;
	}

	function _getCacheDir(){
		if(!$this->cache_dir){ return null; }

		return $this->cache_dir;
	}
	
	function _getCacheFilenameForPart($id){
		return $this->_getCacheDir()."/parts/".$id.".cache";
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

	function getSubject(){ return $this->getHeader("subject"); }
	function getFrom(){ return $this->getHeader("from"); }
	function getTo(){ return $this->getHeader("to"); }
	function getDate(){
		$date = $this->getHeader("date");
		if(!$date){ return null; }
		$out = date("Y-m-d H:i:s",strtotime($date));
		if($out){ return $out; }
	}
	function getSize(){ return $this->size; }

	function getParts(){
		if($this->parts){ return $this->parts; }
		foreach($this->struct as $struct){
			$this->parts[] = new ParsedEmailPart($this,$struct);
		}
		return $this->parts;
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

	function getFirstReadablePart($options = []){
		$options += [
			"prefer_html" => false,
		];

		$plain_parts = $html_parts = [];
		foreach($this->getParts() as $part){
			if(!in_array($part->getMimeType(),["text/plain","text/html"]) || strlen((string)$part->getFilename())){ continue; }
			if($part->getMimeType()=="text/plain"){ $plain_parts[] = $part; continue; }
			if($part->getMimeType()=="text/html"){ $html_parts[] = $part; continue; }
		}

		$plain = null;
		foreach($plain_parts as $part){
			$plain = $part;
			if(strlen(trim($plain->getContent()))){ break; }
		}
		$html = null;
		foreach($html_parts as $part){
			$html = $part;
			if(strlen(trim($html->getContent()))){ break; }
		}

		return (!$plain || ($options["prefer_html"] && $html)) ? $html : $plain; 
	}

	function hasAttachment(){
		foreach($this->getParts() as $part){
      if($part->isAttachment()){ return true; }
		}
		return false;
	}

	/**
	 * Returns an array of all SMTP server IP addresses (relay hops) from email headers
	 *
	 * @return string[]
	 */
	function getSmtpRelayIps(){
		$headers = $this->getHeader("received",["as_array" => true]);

		$ipv4 = '(?:\d{1,3}\.){3}\d{1,3}';
		$ipv6 = '(?:[a-f0-9]{1,4}:){2,7}[a-f0-9]{1,4}';
		$seprator = '[\s\[\]\(\),;]';

		$out = [];
		foreach($headers as $header){
			$header = strtolower($header);
			if(preg_match_all("/$seprator(?P<ip>$ipv4|$ipv6)$seprator/i", " $header ", $ips)){
				foreach($ips["ip"] as $ip){
					if(preg_match('/^\d+:\d+:\d+$/',$ip)){ continue; } // time, e.g. 20:29:18
					if(self::_IsPrivateIp($ip)){ continue; }
					$out[] = $ip;
				}
			}
		}

		$out = array_unique($out);
		$out = array_reverse($out);

		return $out;
	}

	static function _IsPrivateIp($ip){
		// TODO: detect private IPv6 address
		return
			preg_match('/^10\./', $ip) ||
			preg_match('/^192\.168\./', $ip) ||
			preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) ||
			preg_match('/^127\./', $ip);
	}

	static function _CorrectFilename($filename,$declared_charset = ""){
		if(is_null($filename)){ return null; }
		$filename = trim((string)$filename);

		if(!\Translate::CheckEncoding($filename,"UTF-8") && $declared_charset){
			$_filename = \Translate::Trans($filename,$declared_charset,"UTF-8");
			if(!\Translate::CheckEncoding($_filename,"UTF-8")){
				$filename = $_filename;
			}
		}

		$filename = \Yarri\Utf8Cleaner::Clean($filename,"_");
		$filename = preg_replace("/[\\/\\\\]/",'_',$filename);
		$filename = preg_replace('/[\x00-\x1F\x7F]/','_',$filename);
		if($filename===""){ $filename = "_"; }
		if($filename==="."){ $filename = "_"; }
		if($filename===".."){ $filename = "__"; }
		if(mb_strlen($filename)>100){
			$filename = mb_substr($filename,-100);
		}
		return $filename;
	}

	function _getContentCharset(&$structure){
		$mime_type = null;
		$filename = null;
		$charset = null;
		if(isset($structure->ctype_primary) && isset($structure->ctype_secondary)){
			$mime_type = strtolower(trim($structure->ctype_primary)."/".trim($structure->ctype_secondary));
		}
		if(isset($structure->ctype_parameters["name"])){
			$filename = $structure->ctype_parameters["name"];
		}
		if(isset($structure->d_parameters["filename"])){
			$filename = $structure->d_parameters["filename"];
		}
		if(isset($structure->ctype_parameters["charset"])){
			$charset = strtolower(trim($structure->ctype_parameters["charset"]));
		}

		if(in_array($mime_type,["text/html","text/plain"]) && !$filename && $charset){
			return $charset;
		}
		
		if(isset($structure->parts)){
			foreach($structure->parts as &$part){
				$charset = $this->_getContentCharset($part);
				if($charset){ return $charset; }
			}
		}
	}

	function _parseHeaders($structure){
		if(!isset($structure->headers)){ return []; }

		$content_charset = $this->_getContentCharset($structure);

		$fix_encoding = function($value) use($content_charset){
			if(!\Translate::CheckEncoding($value,"UTF-8") && $content_charset){
				$_value = \Translate::Trans($value,$content_charset,"UTF-8");
				if(\Translate::CheckEncoding($_value,"UTF-8")){
					return $_value;
				}
			}
			$value = \Yarri\Utf8Cleaner::Clean($value);
			return $value;
		};

		$headers = [];
		foreach($structure->headers as $key => $value){
			$key = trim($key);
			$key = $fix_encoding($key);
			$key = mb_strtolower($key);
			
			if(is_array($value)){
				$value = array_map(function($item) use($fix_encoding){ return $fix_encoding($item); },$value);
				$headers[$key] = $value;
				continue;
			}

			$value = trim($value);
			$value = $fix_encoding($value);

			$headers[$key] = $value;
			continue;
		}

		return $headers;
	}

	function getParser(){
		return $this->parser;
	}

	function getCacheDir(){
		return $this->cache_dir;
	}
}
