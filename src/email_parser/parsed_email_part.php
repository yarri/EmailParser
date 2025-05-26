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
		$buffer = $this->getContentBuffer();
		return $buffer->toString();
	}

	/**
	 *
	 *	$buffer = $part->getContentBuffer();
	 *
	 *	header("Content-Type: ".$buffer->getMimeType());
	 *	$buffer->printOut();
	 *
	 * @return \StringBuffer
	 */
	function getContentBuffer(){
		$buffer = new \StringBuffer();
		if(!$this->struct["has_content"]){ return $buffer; }
		if(!$this->struct["body_included"]){
			$filename = $this->email->_getCacheFilenameForPart($this->getId());
			$buffer->addFile($filename);
			return $buffer;
		}
		$buffer->addString($this->struct["body"]);
		return $buffer;
	}

	function getSize(){
		return strlen($this->getContent());
	}

	function getCharset(){
		return $this->struct["charset"];
	}

	function getDeclaredCharset(){
		return $this->struct["declared_charset"];
	}

	function getParentPart(){
		$parents = [];
		foreach($this->email->getParts() as $part){
			$l = $part->getLevel();
			if($part->getId()==$this->getId()){
				return isset($parents[$l-1]) ? $parents[$l-1] : null;
			}
			$parents[$l] = $part;
		}
	}

  function isAttachment(){
		if(preg_match('/^image\//',$this->getMimeType()) && $this->getContentId()){
			$parent = $this->getParentPart();
			if($parent && $parent->getMimeType()=="multipart/related"){
        return false;
			}
		}
		if($this->hasContent() && strlen((string)$this->getFilename())){
			return true;
		}
    return false;
  }

	function toArray(){
		return $this->struct;
	}

	function __toString(){ return (string)$this->getContent(); }
}
