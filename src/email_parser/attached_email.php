<?php
namespace Yarri\EmailParser;

class AttachedEmail extends ParsedEmail {

	function __construct(\Yarri\EmailParser $parser, string $cache_dir = "", $parts = []){
		$this->parser = $parser;
		$this->cache_dir = $cache_dir;
		$this->parts = $parts;
		$this->headers = $parts[0]->getHeaders();
	}
}
