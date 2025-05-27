EmailParser
===========

[![Build Status](https://app.travis-ci.com/yarri/EmailParser.svg?token=Kc7UxgK5oqFG8sZAhCzg&branch=master)](https://app.travis-ci.com/yarri/EmailParser)

Parses emails, parses them well :)

EmailParser tries to simplify some of the pains in the email parsing process:

* All headers, text/plain and text/html parts (which are not attachments) and filenames of attachments are converted into UTF-8 encoding.
* In these components, illegal UTF-8 characters are replaced.
* Attachment filenames are properly sanitized.
* Email can be parsed by its source or by its filename.
* The email source file can be gzipped.
* EmailParser itself determines mime types of attachments.
* In EmailParser, a caching mechanism is built-in.
* Contents of attachments can be accessd via [StringBuffer](https://packagist.org/packages/atk14/string-buffer) which has a positive impact on memory consumption.

Usage
-----

    $parser = new \Yarri\EmailParser();

    // Parsing email
    $email = $parser->parse($email_content);
    // or
    $email = $parser->parseFile("/path/to/email.eml");
    // or
    $email = $parser->parseFile("/path/to/email.eml.gz");
    
    // Getting headers
    $email->getSubject();
    $email->getFrom();
    $email->getTo();
    $email->getDate(); // returns date in the ISO format (YYYY-mm-dd H:i:s) in the current timezone (set via date_default_timezone_set()); e.g. "2025-05-25 12:40:22"
    $email->getHeader("Date"); // e.g. "Sun, 25 May 2025 06:37:33 +0200 (CEST)"
    $email->getHeader("Return-Path");
    $email->getHeader("Subject"); // same as $email->getSubject()
    $email->getHeader("Received"); // returns string
    $email->getHeader("Received",["as_array" => true]); // returns array of strings
    $email->hasAttachment(); // true of false

    // Displaying the message
    $part = $email->getFirstReadablePart();
    // or
    $part = $email->getFirstReadablePart(["prefer_html" => true]);
    //
    header(sprintf(
      "Content-Type: %s; charset=%s",
      $part->getMimeType(), // "text/plain" or "text/html"
      $part->getCharset() // always "UTF-8"
    ));
    echo $part->getContent();

    // Traversing email structure
    $parts = $email->getParts();
    foreach($parts as $part){
      $id = $part->getId(); // 1,2,3...
      $level = $part->getLevel(); // 1,2,3..
      $padding = str_repeat(" ",$level); // " ","  ","   "...
      $mime_type = $part->getMimeType();
      if($part->hasContent()){
        $content_info = $part->getSize()." bytes";
        if($part->getFilename()){
          $content_info .= ", ".$part->getFilename();
        }
      }else{
        $content_info = "no content";
      }
      echo "$id.$padding$mime_type ($content_info)\n";
    }

    // Something like this can be printed:
    /*
    1. multipart/related (no content)
    2.  multipart/alternative (no content)
    3.   text/plain (55 bytes)
    4.   text/html (107 bytes)
    5.  image/png (11462 bytes, dungeon-master.png)
    6.  image/jpeg (9123 bytes, pigeon.jpg)
    // */

    // Getting parts
    $part = $email->getPartById(5);
    $part->isAttachment(); // true
    $part->getMimeType(); // "image/png"
    $part->getContent(); // binary content

    // Caching mechanism
    // (you are responsible for providing specific cache path for every email you want to parse)
    $email = $parser->parse($email_1_content,"/path/to/cache/for_email_1/");
    // or
    $email = $parser->parseFile("/path/to/email_2.eml","/path/to/cache/for_email_2/");
    // or
    $email = $parser->parseFile("/path/to/email_3.eml.gz","/path/to/cache/for_email_3/");

    // Displaying attachment via StringBuffer which is memory more efficient
    // (only takes effect when caching is active)
    header(sprintf('Content-Type: %s',$part->getMimeType());
    header(sprintf('Content-Disposition: attachment; filename="%s"',$part->getFilename()));
    $buffer = $part->getContentBuffer();
    $buffer->printOut();

Installation
------------

Just use the Composer:

    composer require yarri/email-parser

Testing
-------

The EmailParser is tested automatically using Travis CI in PHP 7.0 to PHP 8.4.

For the tests execution, the package [atk14/tester](https://packagist.org/packages/atk14/tester) is used. It is just a wrapping script for [phpunit/phpunit](https://packagist.org/packages/phpunit/phpunit).

Install required dependencies for development:

    composer update --dev

Run tests:

    cd test
    ../vendor/bin/run_unit_tests

License
-------

EmailParser is free software distributed [under the terms of the MIT license](http://www.opensource.org/licenses/mit-license)

[//]: # ( vim: set ts=2 et: )
