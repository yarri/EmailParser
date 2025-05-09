<?php
namespace Yarri;

class EmailParser {

	const VERSION = 0.1;

	protected $charset;

	function __construct(array $options = []){
		$options += [
			"charset" => "UTF-8",
		];

		$this->charset = $options["charset"];
	}

	function parse($email_content){
		$email = new ParsedEmail($this);
		$email->set_input($email_content);
		return $email;
	}
}
