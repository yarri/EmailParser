<?php
namespace Yarri\EmailParser;

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
		return $this->struct["body"];
	}

	function getSize(){
		return strlen($this->getContent());
	}

	function getCharset(){
		return $this->struct["charset"];
	}

	function getParentPart(){
		$parents = [];
		foreach($this->email->getParts() as $part){
			$l = $part->getLevel();
			$parents[$l] = $part;;
			if($part->getId()==$this->getId()){
				return isset($parents[$l-1]) ? $parents[$l-1] : null;
			}
		}
	}

	function toArray(){
		return $this->struct;
	}
}
