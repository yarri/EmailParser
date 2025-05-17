<?php
namespace Yarri;

class ParsedEmailPart {

	protected $email;
	protected $struct;

	function __construct(ParsedEmail $email, array $struct){
		$this->email = $email;
		$this->struct = $struct;
	}

	function getMimeType(){
		return $this->struct["mime_type"];
	}

	function getDeclaredMimeType(){
		return $this->struct["declared_mime_type"];
	}

	function getLevel(){
		return $this->struct["level"];
	}

	function getId(){
		return $this->struct["id"];
	}

	function hasContent(){
		return $this->struct["has_content"];
	}

	function getContentId(){
		return $this->struct["content_id"];
	}

	function getFilename(){
		return $this->struct["name"];
	}

	function getContent(){
		$body = $this->struct["body"];
		if(preg_match('/text\//',$this->getMimeType()) && $this->struct["charset"]){
			$body = \Translate::Trans($body,$this->struct["charset"],"UTF-8");
			$body = \Yarri\Utf8Cleaner::Clean($body);
			return $body;
		}
		return $body;
	}

	function toArray(){
		return $this->struct;
	}
}
