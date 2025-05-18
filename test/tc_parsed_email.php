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
}
