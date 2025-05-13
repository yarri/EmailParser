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
		$this->assertEquals(true,$parts[0]->hasContent());
		$this->assertStringContains("Díky za zprávu.",$parts[0]->getContent());

		// ParsedEmailPart::getPartById()

		$part = $email->getPartById(1);
		$this->assertEquals("text/plain",$part->getMimeType());

		$part = $email->getPartById(999);
		$this->assertTrue(is_null($part));
	}

	function test_multipart_alternative(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/multipart_alternative.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$parts = $email->getParts();

		$this->assertEquals(3,sizeof($parts));

		$this->assertEquals("multipart/alternative",$parts[0]->getMimeType());
		$this->assertEquals(1,$parts[0]->getLevel());
		$this->assertEquals(1,$parts[0]->getId());
		$this->assertEquals(false,$parts[0]->hasContent());
		$this->assertTrue(is_null($parts[0]->getContent()));

		$this->assertEquals("text/plain",$parts[1]->getMimeType());
		$this->assertEquals(true,$parts[1]->hasContent());
		$this->assertStringNotContains("<br>",$parts[1]->getContent());
		$this->assertEquals(2,$parts[1]->getLevel());
		$this->assertEquals(2,$parts[1]->getId());
		$this->assertStringContains("Zdravím sebe sama!",$parts[1]->getContent());

		$this->assertEquals("text/html",$parts[2]->getMimeType());
		$this->assertEquals(true,$parts[1]->hasContent());
		$this->assertEquals(2,$parts[2]->getLevel());
		$this->assertEquals(3,$parts[2]->getId());
		$this->assertStringContains("Zdravím sebe sama!<br><br>",$parts[2]->getContent());
	}

	function test_multipart_related(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/multipart_related.txt");

		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$parts = $email->getParts();

		$this->assertEquals(6,sizeof($parts));

		$this->assertEquals("multipart/related",$parts[0]->getMimeType());
		$this->assertEquals(false,$parts[0]->hasContent());
		$this->assertEquals(1,$parts[0]->getId());
		$this->assertEquals(1,$parts[0]->getLevel());
		$this->assertEquals(null,$parts[0]->getContentId());
		$this->assertEquals(null,$parts[0]->getFilename());

		$this->assertEquals("multipart/alternative",$parts[1]->getMimeType());
		$this->assertEquals(false,$parts[1]->hasContent());
		$this->assertEquals(2,$parts[1]->getId());
		$this->assertEquals(2,$parts[1]->getLevel());
		$this->assertEquals(null,$parts[1]->getContentId());
		$this->assertEquals(null,$parts[1]->getFilename());

		$this->assertEquals("text/plain",$parts[2]->getMimeType());
		$this->assertEquals(true,$parts[2]->hasContent());
		$this->assertEquals(3,$parts[2]->getId());
		$this->assertEquals(3,$parts[2]->getLevel());
		$this->assertEquals(null,$parts[2]->getContentId());
		$this->assertEquals(null,$parts[2]->getFilename());

		$this->assertEquals("text/html",$parts[3]->getMimeType());
		$this->assertEquals(true,$parts[3]->hasContent());
		$this->assertEquals(4,$parts[3]->getId());
		$this->assertEquals(3,$parts[3]->getLevel());
		$this->assertEquals(null,$parts[3]->getContentId());
		$this->assertEquals(null,$parts[3]->getFilename());

		$this->assertEquals("image/png",$parts[4]->getMimeType());
		$this->assertEquals(true,$parts[4]->hasContent());
		$this->assertEquals(5,$parts[4]->getId());
		$this->assertEquals(2,$parts[4]->getLevel());
		$this->assertEquals("<c1>",$parts[4]->getContentId());
		$this->assertEquals("dungeon-master.png",$parts[4]->getFilename());
		$this->assertEquals("c4f99bdb6a4feb3b41b1bcd56a4d7aa3",md5($parts[4]->getContent()));

		$this->assertEquals("image/jpeg",$parts[5]->getMimeType());
		$this->assertEquals(true,$parts[5]->hasContent());
		$this->assertEquals(6,$parts[5]->getId());
		$this->assertEquals(2,$parts[5]->getLevel());
		$this->assertEquals("<c2>",$parts[5]->getContentId());
		$this->assertEquals("holub.jpg",$parts[5]->getFilename());
		$this->assertEquals("144875a232cb1d2d5abfbf75f4e52d61",md5($parts[5]->getContent()));

		// ParsedEmailPart::getPartByContentId()

		$part = $email->getPartByContentId("<c1>");
		$this->assertEquals("dungeon-master.png",$part->getFilename());

		$part = $email->getPartByContentId("<c2>");
		$this->assertEquals("holub.jpg",$part->getFilename());

		$part = $email->getPartByContentId("<nonsence>");
		$this->assertTrue(is_null($part));
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

	function test_hard_to_parse_1(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/hard_to_parse_1.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$this->assertEquals("Donâ��t Risk It: Drive Confidently After Dark",$email->getHeader("Subject"));
	
		$parts = $email->getParts();
		$this->assertEquals(1,sizeof($parts));

		$this->assertEquals("text/html",$parts[0]->getMimeType());
		$this->assertStringContains(">Glare, reflections, and poor visibility at night can make driving stressful",$parts[0]->getContent());
	}
}
