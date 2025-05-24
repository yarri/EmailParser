<?php
class TcCaching extends TcBase {

	function test(){
		$cache_dir = __DIR__ ."/tmp/cache/text_plain_".uniqid()."/";
		$this->assertFalse(file_exists($cache_dir));

		$parser = new Yarri\EmailParser();
		$email = $parser->parseFile(__DIR__ . "/sample_emails/text_plain.txt",$cache_dir);
		$this->assertTrue(file_exists($cache_dir));
		$this->assertTrue(file_exists($cache_dir."/cache.ser"));
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
