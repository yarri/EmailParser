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

	function getContent($options = []){
		$buffer = $this->getContentBuffer($options);
		return $buffer->toString();
	}

	/**
	 *
	 *	$buffer = $part->getContentBuffer();
	 *
	 *	header("Content-Type: ".$buffer->getMimeType());
	 *	$buffer->printOut();
	 *
	 * Options:
	 *	embed_inline_images: for a text/html part, rewrites "cid:xxx" references
	 *	(eg. <img src="cid:c1">) to "data:<mime-type>;base64,..." URIs resolved from
	 *	sibling parts sharing that Content-ID, so the HTML is self-contained and
	 *	renders inline images without a separate request for each one. Off by default.
	 *
	 * @return \StringBuffer
	 */
	function getContentBuffer($options = []){
		$options += [
			"embed_inline_images" => false,
		];

		$buffer = new \StringBuffer();
		if(!$this->struct["has_content"]){ return $buffer; }
		if(!$this->struct["body_included"]){
			$filename = $this->email->_getCacheFilenameForPart($this->getId());
			$buffer->addFile($filename);
		}else{
			$buffer->addString($this->struct["body"]);
		}

		if(!$options["embed_inline_images"] || $this->getMimeType()!=="text/html"){
			return $buffer;
		}

		$out = new \StringBuffer();
		$out->addString($this->_embedInlineImages($buffer->toString()));
		return $out;
	}

	function _embedInlineImages($html){
		$email = $this->email;
		return preg_replace_callback(
			'/(["\'(])cid:([^"\'()\s]+)(["\')])/',
			function($matches) use($email){
				list(,$prefix,$cid,$suffix) = $matches;
				$part = $email->getPartByContentId("<$cid>");
				if(!$part){ return $matches[0]; }
				return $prefix."data:".$part->getMimeType().";base64,".base64_encode($part->getContent()).$suffix;
			},
			$html
		);
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

	function getHeaders(){
		return $this->struct["headers"];
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

	function getChildParts(){
		$level = $this->getLevel();
		$got_me = false;
		
		$children = [];
		foreach($this->email->getParts() as $part){
			if($part->getId()==$this->getId()){ $got_me = true; continue; }
			if($got_me && $level<$part->getLevel()){
				$children[] = $part;
				continue;
			}
			if(!$got_me){ continue; }
			break;
		}

		return $children;
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

	function isAttachedEmail(){
		return $this->getMimeType()==="message/rfc822";
	}

	function getAttachedEmail(){
		if(!$this->isAttachedEmail()){
			return null;
		}
		$parts = $this->getChildParts();
		$email = $this->email;
		return new AttachedEmail($email->getParser(),$email->getCacheDir(),$parts);
	}

	/*
	function toArray(){
		return $this->struct;
	}
	*/

	function __toString(){ return (string)$this->getContent(); }
}
