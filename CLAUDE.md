# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`yarri/email-parser` is a PHP library that parses raw MIME email messages (from strings or files) into structured PHP objects. It automatically converts headers and text content to UTF-8, detects MIME types, sanitizes attachment filenames, and supports attached emails (message/rfc822).

## Commands

### Install dependencies
```bash
composer update
```

### Run all tests
```bash
cd test && ../vendor/bin/run_unit_tests
```

### Run a single test file
```bash
cd test && ../vendor/bin/run_unit_tests tc_email_parser.php
```

No linting tools are configured in this project.

## Architecture

The library has four main source files under `src/`:

- **`email_parser.php`** — Entry point. `EmailParser::parse($string)` and `EmailParser::parseFile($path, $cache_dir)` return a `ParsedEmail` instance. Caching stores serialized results in `$cache_dir`.

- **`email_parser/parsed_email.php`** — The primary result object. Exposes headers (`getSubject`, `getFrom`, `getTo`, `getCc`, `getBcc`, `getDate`, `getHeader`), navigation (`getParts`, `getPartById`, `getPartByContentId`, `getFirstReadablePart`), and metadata (`hasAttachment`, `getSmtpRelayIps`, `getFromEmail`, `getFromName`). `getDate()` always returns ISO format `YYYY-mm-dd H:i:s`.

- **`email_parser/parsed_email_part.php`** — Represents one MIME part. Key distinction: `getMimeType()` returns the detected type while `getDeclaredMimeType()` returns the original header value. `getCharset()` always returns `"UTF-8"`; `getDeclaredCharset()` returns the original. Use `getContentBuffer()` (returns a `StringBuffer`) instead of `getContent()` for large attachments to avoid memory issues.

- **`email_parser/attached_email.php`** — Wraps an embedded `message/rfc822` part. Returned by `ParsedEmailPart::getAttachedEmail()`.

The underlying MIME decoding is done by the bundled PEAR `Mail_mimeDecode` class in `src/pear/`. This is vendored directly rather than pulled from Composer.

## Testing

Tests live in `test/` and use the `atk14/tester` framework (a PHPUnit wrapper). Test classes follow the `Tc*` naming convention. Sample `.eml`-style fixture files are in `test/sample_emails/`. When adding new parser behaviour, add a corresponding fixture file and test case in `tc_email_parser.php`.

The CI matrix covers PHP 7.0–8.5 (see `.github/workflows/tests.yml`), so avoid syntax or functions unavailable in PHP 7.0.
