<?php
class TcEmailParser extends TcBase {

	function test_basic_usage(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/text_plain.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$this->assertEquals('RE: Testovací zpráva (text/plain)',$email->getSubject());
		$this->assertEquals('"Jaromir Tomek" <yarri@listonos.cz>',$email->getFrom());
		$this->assertEquals('yarri@listonos.cz',$email->getTo());
		$this->assertEquals('2013-10-07 20:00:03',$email->getDate()); // UTC, see test/initialize.php
		$this->assertEquals(931,$email->getSize());

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
		$this->assertEquals("text/plain",$parts[0]->getDeclaredMimeType());
		$this->assertEquals(1,$parts[0]->getLevel());
		$this->assertEquals(1,$parts[0]->getId());
		$this->assertEquals(true,$parts[0]->hasContent());
		$this->assertStringContains("Díky za zprávu.",$parts[0]->getContent());
		$this->assertEquals("UTF-8",$parts[0]->getCharset());
		$this->assertEquals(198,$parts[0]->getSize());
		$this->assertEquals(false,$parts[0]->isAttachment());

		// ParsedEmailPart::getPartById()

		$part = $email->getPartById(1);
		$this->assertEquals("text/plain",$part->getMimeType());

		$part = $email->getPartById(999);
		$this->assertTrue(is_null($part));

		// ParsedEmailPart::getParentPart()

		$parent = $parts[0]->getParentPart();
		$this->assertTrue(is_null($parent));

		// EmailParser::parseFile()

		$parser = new Yarri\EmailParser();

		$email = $parser->parseFile(__DIR__ . "/sample_emails/multipart_alternative.txt");
		$this->assertEquals("Testovací zpráva (multipart/alternative)",$email->getHeader("subject"));

		// a gziped file
		$email = $parser->parseFile(__DIR__ . "/sample_emails/html_document_in_latin_2_encoding.txt.gz");
		$this->assertEquals("HTML document in Latin 2 encoding",$email->getHeader("subject"));
		$this->assertEquals(4592,$email->getSize()); // size of the uncompressed source
	}

	function test_multipart_alternative(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/multipart_alternative.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$parts = $email->getParts();

		$this->assertEquals(3,sizeof($parts));

		$this->assertEquals("multipart/alternative",$parts[0]->getMimeType());
		$this->assertEquals("multipart/alternative",$parts[0]->getDeclaredMimeType());
		$this->assertEquals(1,$parts[0]->getLevel());
		$this->assertEquals(1,$parts[0]->getId());
		$this->assertEquals(false,$parts[0]->hasContent());
		$this->assertTrue(""===$parts[0]->getContent());
		$this->assertEquals(false,$parts[0]->isAttachment());
		$this->assertTrue(is_null($parts[0]->getCharset()));
		$parent = $parts[0]->getParentPart();
		$this->assertTrue(is_null($parent));

		$this->assertEquals("text/plain",$parts[1]->getMimeType());
		$this->assertEquals("text/plain",$parts[1]->getDeclaredMimeType());
		$this->assertEquals(true,$parts[1]->hasContent());
		$this->assertStringNotContains("<br>",$parts[1]->getContent());
		$this->assertEquals(2,$parts[1]->getLevel());
		$this->assertEquals(2,$parts[1]->getId());
		$this->assertStringContains("Zdravím sebe sama!",$parts[1]->getContent());
		$this->assertEquals(false,$parts[1]->isAttachment());
		$this->assertEquals("UTF-8",$parts[1]->getCharset());
		$parent = $parts[1]->getParentPart();
		$this->assertEquals(1,$parent->getId());

		$this->assertEquals("text/html",$parts[2]->getMimeType());
		$this->assertEquals("text/html",$parts[2]->getDeclaredMimeType());
		$this->assertEquals(true,$parts[1]->hasContent());
		$this->assertEquals(2,$parts[2]->getLevel());
		$this->assertEquals(3,$parts[2]->getId());
		$this->assertStringContains("Zdravím sebe sama!<br><br>",$parts[2]->getContent());
		$this->assertEquals(false,$parts[1]->isAttachment());
		$this->assertEquals("UTF-8",$parts[2]->getCharset());
		$parent = $parts[2]->getParentPart();
		$this->assertEquals(1,$parent->getId());
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
		$this->assertEquals(false,$parts[0]->isAttachment());
		$parent = $parts[0]->getParentPart();
		$this->assertTrue(is_null($parent));

		$this->assertEquals("multipart/alternative",$parts[1]->getMimeType());
		$this->assertEquals(false,$parts[1]->hasContent());
		$this->assertEquals(2,$parts[1]->getId());
		$this->assertEquals(2,$parts[1]->getLevel());
		$this->assertEquals(null,$parts[1]->getContentId());
		$this->assertEquals(null,$parts[1]->getFilename());
		$this->assertEquals(false,$parts[1]->isAttachment());
		$parent = $parts[1]->getParentPart();
		$this->assertEquals(1,$parent->getId());

		$this->assertEquals("text/plain",$parts[2]->getMimeType());
		$this->assertEquals(true,$parts[2]->hasContent());
		$this->assertEquals(3,$parts[2]->getId());
		$this->assertEquals(3,$parts[2]->getLevel());
		$this->assertEquals(null,$parts[2]->getContentId());
		$this->assertEquals(null,$parts[2]->getFilename());
		$this->assertEquals(false,$parts[2]->isAttachment());
		$parent = $parts[2]->getParentPart();
		$this->assertEquals(2,$parent->getId());

		$this->assertEquals("text/html",$parts[3]->getMimeType());
		$this->assertEquals(true,$parts[3]->hasContent());
		$this->assertEquals(4,$parts[3]->getId());
		$this->assertEquals(3,$parts[3]->getLevel());
		$this->assertEquals(null,$parts[3]->getContentId());
		$this->assertEquals(null,$parts[3]->getFilename());
		$this->assertEquals(false,$parts[3]->isAttachment());
		$parent = $parts[3]->getParentPart();
		$this->assertEquals(2,$parent->getId());

		$this->assertEquals("image/png",$parts[4]->getMimeType());
		$this->assertEquals("application/octed-stream",$parts[4]->getDeclaredMimeType());
		$this->assertEquals(true,$parts[4]->hasContent());
		$this->assertEquals(5,$parts[4]->getId());
		$this->assertEquals(2,$parts[4]->getLevel());
		$this->assertEquals("<c1>",$parts[4]->getContentId());
		$this->assertEquals("dungeon-master.png",$parts[4]->getFilename());
		$this->assertEquals(false,$parts[4]->isAttachment());
		$this->assertEquals(11462,$parts[4]->getSize());
		$this->assertEquals("c4f99bdb6a4feb3b41b1bcd56a4d7aa3",md5($parts[4]->getContent()));
		$parent = $parts[4]->getParentPart();
		$this->assertEquals(1,$parent->getId());

		$this->assertEquals("image/jpeg",$parts[5]->getMimeType());
		$this->assertEquals("application/octed-stream",$parts[5]->getDeclaredMimeType());
		$this->assertEquals(true,$parts[5]->hasContent());
		$this->assertEquals(6,$parts[5]->getId());
		$this->assertEquals(2,$parts[5]->getLevel());
		$this->assertEquals("<c2>",$parts[5]->getContentId());
		$this->assertEquals("holub.jpg",$parts[5]->getFilename());
		$this->assertEquals(false,$parts[5]->isAttachment());
		$this->assertEquals(9123,$parts[5]->getSize());
		$this->assertEquals("144875a232cb1d2d5abfbf75f4e52d61",md5($parts[5]->getContent()));
		$parent = $parts[5]->getParentPart();
		$this->assertEquals(1,$parent->getId());

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

	function test_attachment_with_special_chars(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/attachment_with_special_chars.txt");
		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$parts = $email->getParts();

		$this->assertEquals(3,sizeof($parts));

		$this->assertEquals("image/webp",$parts[2]->getMimeType());
		$this->assertEquals("image/jpeg",$parts[2]->getDeclaredMimeType());
		$this->assertEquals("Malé roztomilé lištičky.jpeg",$parts[2]->getFilename());
		$this->assertEquals(true,$parts[2]->isAttachment());
	}

	function test_text_document_with_latin_2_encoding(){
		$email_content = Files::GetFileContent(__DIR__ . "/sample_emails/text_document_with_latin_2_encoding.txt");

		$parser = new Yarri\EmailParser();
		$email = $parser->parse($email_content);

		$parts = $email->getParts();

		$this->assertEquals(3,sizeof($parts));

		$this->assertEquals("text/plain",$parts[2]->getMimeType());
		$this->assertEquals("iso-8859-2",$parts[2]->getCharset());
		$this->assertEquals("text_document_latin2.txt",$parts[2]->getFilename());
		$this->assertEquals(true,$parts[2]->isAttachment());
		$this->assertEquals("Příliš žluťoučký kůň úpěl ďábelské ódy v kódování Latin 2.",trim(Translate::Trans($parts[2]->getContent(),"iso-8859-2","UTF-8")));
		$this->assertEquals(60,$parts[2]->getSize());
	}

	function test_html_document_in_latin_2_encoding(){
		$parser = new Yarri\EmailParser();
		$email = $parser->parseFile(__DIR__ . "/sample_emails/html_document_in_latin_2_encoding.txt.gz");

		$parts = $email->getParts();

		$this->assertEquals(3,sizeof($parts));

		$this->assertEquals("text/html",$parts[2]->getMimeType());
		$this->assertEquals("iso-8859-2",$parts[2]->getCharset());
		$this->assertEquals("html_document_latin2.html",$parts[2]->getFilename());
		$this->assertEquals(true,$parts[2]->isAttachment());
		$this->assertStringContains("<p>Příliš žluťoučký kůň úpěl ďábelské ódy.</p>",trim(Translate::Trans($parts[2]->getContent(),"iso-8859-2","UTF-8")));
		$this->assertEquals(146,$parts[2]->getSize());
	}

	function test_latin_2(){
		$parser = new Yarri\EmailParser();
		$email = $parser->parseFile(__DIR__ . "/sample_emails/latin_2.eml");

		$this->assertEquals("Re: Odesílání e-mailu: HLÁŠENÍ  O  POŽÁRU__1.doc",$email->getSubject());
		$this->assertEquals('"Martin Petřík" <martin.petrik@firehlaseni.cz>',$email->getFrom());

		$parts = $email->getParts();

		$this->assertEquals("UTF-8",$parts[0]->getCharset());
		$this->assertEquals("iso-8859-2",$parts[0]->getDeclaredCharset());
		$this->assertStringContains("V kolonce výše škody odhadněte požárem způsobenou škodu na Vašem majetku",$parts[0]->getContent());
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

	function test_invalid_email(){
		$parser = new Yarri\EmailParser();

		$exception_thrown = false;
		$exception = null;
		try {
			$email = $parser->parse("nonsence");
		}catch(Exception $e){
			$exception_thrown = true;
			$exception = $e;
		}

		$this->assertEquals(true,$exception_thrown);
		$this->assertEquals("Yarri\EmailParser\InvalidEmailSourceException",get_class($e));
	}

	function test_minimal_email(){
		$parser = new Yarri\EmailParser();
		$email = $parser->parseFile(__DIR__ . "/sample_emails/minimal_email.txt");

		$this->assertEquals("Minimal email",$email->getSubject());
		$this->assertNull($email->getFrom());
		$this->assertNull($email->getTo());
		$this->assertNull($email->getDate());

		$parts = $email->getParts();

		$this->assertEquals(1,sizeof($parts));
		$this->assertEquals("text/plain",$parts[0]->getMimeType());
		$this->assertEquals("text/plain",$parts[0]->getDeclaredMimeType());
		$this->assertEquals("Test\n",$parts[0]->getContent());
	}

	function test_text_plain_forwarded_as_attachment(){
		$parser = new Yarri\EmailParser();
		$email = $parser->parseFile(__DIR__ . "/sample_emails/text_plain_forwarded_as_attachment.txt");

		$parts = $email->getParts();

		$this->assertEquals(4,sizeof($parts));

		$this->assertEquals("multipart/mixed",$parts[0]->getMimeType());
		$this->assertEquals("text/plain",$parts[1]->getMimeType());
		$this->assertEquals("message/rfc822",$parts[2]->getMimeType());
		$this->assertEquals("text/plain",$parts[3]->getMimeType());

		$part = $email->getFirstReadablePart();
		$this->assertEquals(4,$part->getId());
		$this->assertStringContains("Děkuji",$part->getContent());
	}

	function test_getFirstReadablePart(){
		$parser = new Yarri\EmailParser();

		$email = $parser->parseFile(__DIR__ . "/sample_emails/text_plain.txt");
		//
		$part = $email->getFirstReadablePart();
		$this->assertEquals("text/plain",$part->getMimeType());
		$this->assertStringContains("Zdravím sebe sama!",(string)$part);
		//
		$part = $email->getFirstReadablePart(["prefer_html" => true]);
		$this->assertEquals("text/plain",$part->getMimeType());
		$this->assertStringContains("Zdravím sebe sama!",(string)$part);

		$email = $parser->parseFile(__DIR__ . "/sample_emails/multipart_alternative.txt");
		//
		$part = $email->getFirstReadablePart();
		$this->assertEquals("text/plain",$part->getMimeType());
		$this->assertStringContains("Zdravím sebe sama!",(string)$part);
		//
		$part = $email->getFirstReadablePart(["prefer_html" => true]);
		$this->assertEquals("text/html",$part->getMimeType());
		$this->assertStringContains(">Zdravím sebe sama!<",(string)$part);
	}

	function test_hasAttachment(){
		$parser = new Yarri\EmailParser();

		foreach([
			"text_plain.txt" => false,
			"multipart_alternative.txt" => false,

			"text_document_with_latin_2_encoding.txt" => true,
			"html_document_in_latin_2_encoding.txt.gz" => true,
			"multipart_related.txt" => false,
		] as $file => $has_attachment_exp){
			$email = $parser->parseFile(__DIR__ . "/sample_emails/$file");
			$this->assertEquals($has_attachment_exp,$email->hasAttachment(),"$file expected to ".($has_attachment_exp ? "have attachment" : "haven't attachment"));
		}
	}

	function test_getDate(){
		$parser = new Yarri\EmailParser();
		$email = $parser->parseFile(__DIR__ . "/sample_emails/hard_to_parse_1.txt");

		date_default_timezone_set("America/Los_Angeles");
		$this->assertEquals("2025-05-01 11:22:13",$email->getDate());
		$this->assertEquals("Thu, 01 May 2025 14:22:13 -0400",$email->getHeader("date"));

		date_default_timezone_set("Europe/Prague");
		$this->assertEquals("2025-05-01 20:22:13",$email->getDate());
		$this->assertEquals("Thu, 01 May 2025 14:22:13 -0400",$email->getHeader("date"));

		date_default_timezone_set("UTC");
		$this->assertEquals("2025-05-01 18:22:13",$email->getDate());
		$this->assertEquals("Thu, 01 May 2025 14:22:13 -0400",$email->getHeader("date"));
	}

	function test_getSmtpRelayIps(){
		$parser = new Yarri\EmailParser();

		$email = $parser->parseFile(__DIR__ . "/sample_emails/hard_to_parse_1.txt");
		$ips = $email->getSmtpRelayIps();
		$this->assertEquals(["37.123.98.46"],$ips);

		$email = $parser->parseFile(__DIR__ . "/sample_emails/html_document_in_latin_2_encoding.txt.gz");
		$ips = $email->getSmtpRelayIps();
		$this->assertEquals([
			"78.45.42.91",
			"209.85.128.53"
		],$ips);

		$email = $parser->parseFile(__DIR__ . "/sample_emails/received_from_ipv6.txt");
		$ips = $email->getSmtpRelayIps();
		$this->assertEquals([
			"89.187.145.110",
			"2002:a05:6358:d5aa:b0:1eb:a36e:6865"
		],$ips);
	}
}
