<?php
namespace Yarri;

class EmailParser {

	const VERSION = 0.1;

	function __construct(){
	}

	/**
	 * Parses the email message by its source
	 *
	 *	$email = $parser->parseFile("/path/to/email.eml");
	 *	$email = $parser->parseFile("/path/to/email.eml.gz");
	 */
	function parse(string $email_source){
		$email = new EmailParser\ParsedEmail($this);
		$email->setEmailSource($email_source);
		return $email;
	}

	/**
	 * Parses the email message by the given filename
	 *
	 *	$email = $parser->parseFile("/path/to/email.eml");
	 *	$email = $parser->parseFile("/path/to/email.eml.gz");
	 */
	function parseFile(string $filename){
		$email_source = \Files::GetFileContent($filename,$err,$err_msg);
		if(preg_match('/\.gz/i',$filename)){
			$email_source = gzdecode($email_source);
		}
		return $this->parse($email_source);
	}
}
