<?php
class TcParsedEmail extends TcBase {

	function test__CorrectFilename(){
		$invalid_utf8_char = chr(200);
		foreach([
			"image.jpg" => "image.jpg",
			"Pěkný obrázek.JPG" => "Pěkný obrázek.JPG",
			"   image.jpg   " => "image.jpg",
			"?\n\t\r?" => "?___?",
			"" => "_",
			"." => "_",
			".." => "__",
			"..." => "...",
			"{$invalid_utf8_char}píseň{$invalid_utf8_char}" => "_píseň_",
		] as $filename => $corrected_filename_exp){
			$this->assertEquals($corrected_filename_exp,\Yarri\EmailParser\ParsedEmail::_CorrectFilename($filename));
		}

		$this->assertTrue(is_null(\Yarri\EmailParser\ParsedEmail::_CorrectFilename(null)));

		$filename = str_repeat("á",120);
		$filename .= ".png";
		$this->assertEquals(124,mb_strlen($filename));
		$this->assertEquals(244,strlen($filename));
		$filename_corrected = \Yarri\EmailParser\ParsedEmail::_CorrectFilename($filename);
		$this->assertEquals(100,mb_strlen($filename_corrected));
		$this->assertEquals(196,strlen($filename_corrected));
		$this->assertTrue(!!preg_match('/\.png/',$filename_corrected));
	}

	function test__IsPrivateIp(){
		foreach([
			"127.0.0.1",
			"10.10.0.1",
			"192.168.1.1",
			"fe80::42:74ff:fe05:a648",
			"fd40:923a:0a2e:bca0::1",
			"fd00::1"
		] as $private_ip){
			$this->assertTrue(\Yarri\EmailParser\ParsedEmail::_IsPrivateIp($private_ip));
		}

		foreach([
			"8.8.8.8",
			"2606:4700:20::ac43:4628",
			"2001:0db8:85a3:0000:0000:8a2e:0370:7334",
		] as $public_ip){
			$this->assertFalse(\Yarri\EmailParser\ParsedEmail::_IsPrivateIp($public_ip));
		}
	}
}
