<?php
namespace Yarri\EmailParser;

require_once(__DIR__ . "/../pear/Mail/mimeDecode.php");

class ParsedEmail {

	var $size = 0;
	var $headers = [];
	var $struct = [];
	var $input = "";

	var $id_counter = 1;
	var $level_counter = 0;

	var $mail_parser_version = \Yarri\EmailParser::VERSION;
	var $mail_cache_dir = MAIL_CACHE_DIR;

	protected $parser;

	function __construct(\Yarri\EmailParser $parser){
		$this->parser = $parser;
	}

	function setEmailSource(&$input){
		$this->_reset();
		$this->_setEmailSource($input);
	}

	function _setEmailSource(&$input){
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

		$first = true;
		foreach($structure->headers as $key => $value){
			$key = trim($key);
			$key = \Yarri\Utf8Cleaner::Clean($key);
			$key = mb_strtolower($key);
			$key = str_replace('-','_',$key); // "message-id" => "message_id"
			
			if(is_array($value)){
				$value = array_map(function($item){ return \Yarri\Utf8Cleaner::Clean($item); },$value);
				$this->headers[$key] = $value;
				continue;
			}

			$value = trim($value);
			$value = \Yarri\Utf8Cleaner::Clean($value);

			$this->headers[$key] = $value;
			continue;
		}
		
		$this->_fillStruct($structure);
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
			);

			if(isset($structure->parts)){
				for($i=0;$i<sizeof($structure->parts);$i++){
					$this->_fillStruct($structure->parts[$i]);
				}
			}
		}
		$this->level_counter--;
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
		$plain = null;
		$html = null;
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
			if($part->hasContent() && strlen((string)$part->getFilename())){
				return true;
			}
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
