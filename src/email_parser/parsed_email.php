<?php
namespace Yarri\EmailParser;

require_once(__DIR__ . "/../pear/Mail/mimeDecode.php");

class ParsedEmail {

	protected $parser;
	protected $cache_dir = ""; // path to a cache directory for the given email

	protected $size = 0;
	protected $headers = [];
	protected $struct = [];

	protected $id_counter = 1;
	protected $level_counter = 0;


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

		$this->_fillStruct($structure);

		$content_charset = null;
		foreach($this->getParts() as $part){
			if(in_array($part->getMimeType(),["text/html","text/plain"]) && !$part->getFilename() && $part->getDeclaredCharset()){
				$content_charset = $part->getDeclaredCharset();
				break;
			}
		}

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

		foreach($structure->headers as $key => $value){
			$key = trim($key);
			$key = $fix_encoding($key);
			$key = mb_strtolower($key);
			$key = str_replace("-","_",$key); // "message-id" => "message_id"
			
			if(is_array($value)){
				$value = array_map(function($item) use($fix_encoding){ return $fix_encoding($item); },$value);
				$this->headers[$key] = $value;
				continue;
			}

			$value = trim($value);
			$value = $fix_encoding($value);

			$this->headers[$key] = $value;
			continue;
		}

		$this->_writeCache();
	}

	function _reset(){
		$this->size = 0;
		$this->headers = [];
		$this->struct = [];
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
		if($object){
			if(isset($structure->ctype_primary) && isset($structure->ctype_secondary)){
				$declared_mime_type = strtolower(trim($structure->ctype_primary)."/".trim($structure->ctype_secondary));
			}
			if(isset($structure->ctype_parameters["name"]) && $name==""){
				$name = self::_CorrectFilename($structure->ctype_parameters["name"]);
			}
			if(isset($structure->d_parameters["filename"]) && $name==""){
				$name = self::_CorrectFilename($structure->d_parameters["filename"]);
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
			if($declared_mime_type && !(strlen($name)===0 && strlen($_body)===0) && !(in_array($declared_mime_type,["text/plain","text/html"]) && !strlen($name))){
				$_file = \Files::WriteToTemp($_body,$err,$err_msg);
				$mime_type = \Files::DetermineFileType($_file,["original_filename" => $name]);
				\Files::Unlink($_file);
				$mime_type = $mime_type ? $mime_type : $declared_mime_type;
			}

			$this->struct[] = array(
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
			);

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
		$key = str_replace('-','_',$key); // "message-id" => "message_id"
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

	function getFirstReadablePart($options = []){
		$options += [
			"prefer_html" => false,
		];
		$plain = $html = null;
		foreach($this->getParts() as $part){
			if($plain && $html){ break; }
			if(!in_array($part->getMimeType(),["text/plain","text/html"]) || strlen((string)$part->getFilename())){ continue; }
			if($part->getMimeType()=="text/plain" && !$plain){ $plain = $part; continue; }
			if($part->getMimeType()=="text/html" && !$html){ $html = $part; continue; }
		}
		return (!$plain || ($options["prefer_html"] && $html)) ? $html : $plain; 
	}

	function hasAttachment(){
		foreach($this->getParts() as $part){
      if($part->isAttachment()){ return true; }
		}
		return false;
	}

	static function _CorrectFilename($filename){
		if(is_null($filename)){ return null; }
		$filename = trim((string)$filename);
		$filename = \Yarri\Utf8Cleaner::Clean($filename,"_");
		$filename = preg_replace("/[\\/\\\\]/",'_',$filename);
		$filename = preg_replace('/[\x00-\x1F\x7F]/','_',$filename);
		if(mb_strlen($filename)>100){
			$filename = mb_substr($filename,-100);
		}
		if($filename===""){ $filename = "_"; }
		if($filename==="."){ $filename = "_"; }
		if($filename===".."){ $filename = "__"; }
		return $filename;
	}
}
