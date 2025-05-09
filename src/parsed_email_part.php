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

	function getLevel(){
		return $this->struct["level"];
	}

	function getId(){
		return $this->struct["id"];
	}

	function getBody(){
		if(preg_match('/text\//',$this->getMimeType()) && $this->struct["charset"]){
			$body = $this->struct["body"];
			$body = \Translate::Trans($body,$this->struct["charset"],"UTF-8");
			$body = \Yarri\Utf8Cleaner::Clean($body);
			return $body;
		}
		return $this->struct["body"];
	}

	function toArray(){
		return $this->struct;
	}
}
