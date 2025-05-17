<?php
namespace Yarri;

class EmailParser {

	const VERSION = 0.1;

	function __construct(){
	}

	function parse($email_content){
		$email = new EmailParser\ParsedEmail($this);
		$email->set_input($email_content);
		return $email;
	}
}
