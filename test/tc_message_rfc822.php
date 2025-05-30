<?php
class TcMessageRfc822 extends TcBase {

	function test(){
		$parser = new Yarri\EmailParser();
		$email = $parser->parseFile(__DIR__ . "/sample_emails/text_plain_forwarded_as_attachment.txt");

		$this->assertEquals("Fwd: Re: Friends",$email->getSubject());
		$this->assertEquals("Jaromir Tomek <jaromir.tomek@gmail.com>",$email->getFrom());
		$this->assertEquals("Jaromir Tomek <yarri@listonos.cz>",$email->getTo());
		$this->assertEquals("2025-05-27 08:28:42",$email->getDate());

		$parts = $email->getParts();
		$this->assertEquals(4,sizeof($parts));

		$this->assertEquals("multipart/mixed",$parts[0]->getMimeType());
		$this->assertEquals("text/plain",$parts[1]->getMimeType());
		$this->assertEquals("message/rfc822",$parts[2]->getMimeType());
		$this->assertEquals("text/plain",$parts[3]->getMimeType());

		// Attached Email

		$this->assertEquals(false,$parts[0]->isAttachedEmail());
		$this->assertEquals(null,$parts[0]->getAttachedEmail());

		$this->assertEquals(true,$parts[2]->isAttachedEmail());
		$attached_email = $parts[2]->getAttachedEmail();

		$this->assertEquals("RE: Friends",$attached_email->getSubject());
		$this->assertEquals('"Jaromir \"aka\" Mr. Hlina Tomek" <yarri@phyllostomus.com>',$attached_email->getFrom());
		$this->assertEquals("Jaromir Tomek <jaromir.tomek@gmail.com>",$attached_email->getTo());
		$this->assertEquals("2025-05-26 12:20:35",$attached_email->getDate());

		$parts = $attached_email->getParts();
		$this->assertEquals(1,sizeof($parts));

		$this->assertEquals("text/plain",$parts[0]->getMimeType());
		$this->assertEquals(4,$parts[0]->getId());
	}
}
