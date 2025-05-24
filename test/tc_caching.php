<?php
class TcCaching extends TcBase {

	function test(){
		$cache_dir = __DIR__ ."/tmp/cache/text_plain_".uniqid()."/";
		$this->assertFalse(file_exists($cache_dir));

		$parser = new Yarri\EmailParser();
		$email = $parser->parseFile(__DIR__ . "/sample_emails/text_plain.txt",$cache_dir);
		$this->assertTrue(file_exists($cache_dir));
		$this->assertTrue(file_exists($cache_dir."/cache.ser"));

		$email = $parser->parseFile(__DIR__ . "/sample_emails/text_plain.txt",$cache_dir);
		$this->assertEquals("RE: Testovací zpráva (text/plain)",$email->getSubject());
		$part = $email->getFirstReadablePart();
		$this->assertStringContains("Zdravím sebe sama!",$part->getContent());

		// 

		$cache_dir = __DIR__ . "/tmp/cache/multipart_related_".uniqid()."/";
		$this->assertFalse(file_exists($cache_dir));

		$email = $parser->parseFile(__DIR__ . "/sample_emails/multipart_related.txt",$cache_dir);
		$this->assertTrue(file_exists($cache_dir));
		$this->assertTrue(file_exists($cache_dir."/cache.ser"));
		$this->assertTrue(file_exists($cache_dir."/parts/5.cache"));

		$email = $parser->parseFile(__DIR__ . "/sample_emails/multipart_related.txt",$cache_dir);
		$this->assertEquals("Testovaci zprava (multipart/related)",$email->getSubject());

		$part = $email->getPartById(5);
		$this->assertEquals("image/png",$part->getMimeType());
		$this->assertEquals("application/octed-stream",$part->getDeclaredMimeType());
		$this->assertEquals("dungeon-master.png",$part->getFilename());
		$this->assertEquals("c4f99bdb6a4feb3b41b1bcd56a4d7aa3",md5($part->getContent()));
	}

	function test_performance(){
		$parser = new Yarri\EmailParser();

		// uncached
		$start = microtime(true);
		for($i=0;$i<100;$i++){
			$email = $parser->parseFile(__DIR__ . "/sample_emails/multipart_related.txt");
		}
		$stop = microtime(true);
		$uncached_time = $stop - $start;

		// creating cache
		$cache_dir = __DIR__ . "/tmp/cache/multipart_related_".uniqid()."/";
		$email = $parser->parseFile(__DIR__ . "/sample_emails/multipart_related.txt",$cache_dir);

		// cached
		$start = microtime(true);
		for($i=0;$i<100;$i++){
			$email = $parser->parseFile(__DIR__ . "/sample_emails/multipart_related.txt",$cache_dir);
		}
		$stop = microtime(true);
		$cached_time = $stop - $start;

		$this->assertTrue(($uncached_time / $cached_time) > 10);
	}
}
