<?php
class TcEmailParser extends TcBase {

	function test_basic_usage(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/text_plain.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$this->assertEquals('"Jaromir Tomek" <yarri@listonos.cz>',$email->getHeader("from"));
		$this->assertEquals(['"Jaromir Tomek" <yarri@listonos.cz>'],$email->getHeader("from",["as_array" => true]));
		$this->assertEquals('RE: Testovací zpráva (text/plain)',$email->getHeader("Subject"));
		$this->assertEquals('yarri@listonos.cz',$email->getHeader("TO"));
		$this->assertEquals('yarri@listonos.cz',$email->getHeader("Delivered-To"));
		$this->assertEquals(null,$email->getHeader("nonsence"));
		$this->assertEquals([],$email->getHeader("nonsence",["as_array" => true]));

		$parts = $email->getParts();

		$this->assertEquals(1,sizeof($parts));

		$this->assertEquals("text/plain",$parts[0]->getMimeType());
		$this->assertEquals(1,$parts[0]->getLevel());
		$this->assertEquals(1,$parts[0]->getId());
		$this->assertStringContains("Díky za zprávu.",$parts[0]->getBody());
	}

	function test_multipart_alternative(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/multipart_alternative.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$parts = $email->getParts();

		$this->assertEquals(3,sizeof($parts));

		$this->assertEquals("multipart/alternative",$parts[0]->getMimeType());
		$this->assertEquals(1,$parts[0]->getLevel());
		$this->assertEquals(null,$parts[0]->getId());
		$this->assertEquals(null,$parts[0]->getBody());

		$this->assertEquals("text/plain",$parts[1]->getMimeType());
		$this->assertStringContains("Zdravím sebe sama!",$parts[1]->getBody());
		$this->assertStringNotContains("<br>",$parts[1]->getBody());
		$this->assertEquals(2,$parts[1]->getLevel());
		$this->assertEquals(1,$parts[1]->getId());

		$this->assertEquals("text/html",$parts[2]->getMimeType());
		$this->assertStringContains("Zdravím sebe sama!<br><br>",$parts[2]->getBody());
		$this->assertEquals(2,$parts[2]->getLevel());
		$this->assertEquals(2,$parts[2]->getId());
	}

	function test_subject_in_base64(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/subject_in_base64.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$this->assertEquals("Re: Vánoční večírek 28.12.",$email->getHeader("Subject"));
	}

	function test_multiline_header(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/subject_in_base64.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$this->assertEquals('(qmail 54202 invoked by uid 89); 22 Dec 2013 22:02:39 -0000
from mail-la0-f43.google.com (209.85.215.43) by kajka.plovarna.cz with SMTP; 22 Dec 2013 22:02:39 -0000
by mail-la0-f43.google.com with SMTP id n7so2016594lam.30 for <yarri@listonos.cz>; Sun, 22 Dec 2013 14:02:38 -0800 (PST)
by 10.114.91.199 with HTTP; Sun, 22 Dec 2013 14:02:37 -0800 (PST)',$email->getHeader("Received"));

		$this->assertEquals([
			"(qmail 54202 invoked by uid 89); 22 Dec 2013 22:02:39 -0000",
			"from mail-la0-f43.google.com (209.85.215.43) by kajka.plovarna.cz with SMTP; 22 Dec 2013 22:02:39 -0000",
			"by mail-la0-f43.google.com with SMTP id n7so2016594lam.30 for <yarri@listonos.cz>; Sun, 22 Dec 2013 14:02:38 -0800 (PST)",
			"by 10.114.91.199 with HTTP; Sun, 22 Dec 2013 14:02:37 -0800 (PST)"
		],$email->getHeader("Received",["as_array" => true]));
	}

	function test_spam_with_invalid_subject(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/spam_with_invalid_subject.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$this->assertEquals("Nemate dostatek financnich prostredku? Nabizime Vam reseni � praci ve volnem case.",$email->getHeader("Subject"));
	}
}
