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
	function parse(string $email_source, string $cache_dir = ""){
		$email = new EmailParser\ParsedEmail($this,$cache_dir);
		$email->setEmailSource($email_source);
		return $email;
	}

	/**
	 * Parses the email message by the given filename
	 *
	 *	$email = $parser->parseFile("/path/to/email.eml");
	 *	$email = $parser->parseFile("/path/to/email.eml.gz");
	 */
	function parseFile(string $filename, string $cache_dir = ""){
		$email = new EmailParser\ParsedEmail($this,$cache_dir);
		$email->setEmailSourceByFile($filename);
		return $email;
	}
}
