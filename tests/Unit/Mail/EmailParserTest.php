<?php

namespace JeffersonGoncalves\HelpDesk\Tests\Unit\Mail;

use JeffersonGoncalves\HelpDesk\Mail\EmailParser;
use PHPUnit\Framework\TestCase;

class EmailParserTest extends TestCase
{
    private EmailParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new EmailParser;
    }

    public function test_parse_extracts_basic_fields(): void
    {
        $data = [
            'message_id' => '<test@example.com>',
            'from' => 'John Doe <john@example.com>',
            'to' => 'support@example.com',
            'subject' => 'Test Subject',
            'text_body' => 'Hello, I need help.',
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('<test@example.com>', $result['message_id']);
        $this->assertEquals('john@example.com', $result['from_address']);
        $this->assertEquals('John Doe', $result['from_name']);
        $this->assertEquals(['support@example.com'], $result['to_addresses']);
        $this->assertEquals('Test Subject', $result['subject']);
        $this->assertEquals('Hello, I need help.', $result['text_body']);
    }

    public function test_parse_handles_plain_email_address(): void
    {
        $data = [
            'from' => 'john@example.com',
            'to' => 'support@example.com',
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('john@example.com', $result['from_address']);
        $this->assertNull($result['from_name']);
    }

    public function test_parse_handles_multiple_to_addresses(): void
    {
        $data = [
            'from' => 'john@example.com',
            'to' => 'support@example.com, admin@example.com',
        ];

        $result = $this->parser->parse($data);

        $this->assertCount(2, $result['to_addresses']);
        $this->assertEquals('support@example.com', $result['to_addresses'][0]);
        $this->assertEquals('admin@example.com', $result['to_addresses'][1]);
    }

    public function test_clean_text_body_removes_quoted_reply(): void
    {
        $body = "Thanks for the help.\n\nOn Mon, Jan 1, 2024 John Doe wrote:\n> Previous message";

        $result = $this->parser->cleanTextBody($body);

        $this->assertEquals('Thanks for the help.', $result);
    }

    public function test_parse_generates_message_id_when_missing(): void
    {
        $data = [
            'from' => 'john@example.com',
            'to' => 'support@example.com',
        ];

        $result = $this->parser->parse($data);

        $this->assertStringStartsWith('<helpdesk-', $result['message_id']);
        $this->assertStringEndsWith('@generated>', $result['message_id']);
    }

    public function test_parse_handles_in_reply_to_and_references(): void
    {
        $data = [
            'from' => 'john@example.com',
            'to' => 'support@example.com',
            'in_reply_to' => '<original@example.com>',
            'references' => '<ref1@example.com> <ref2@example.com>',
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('<original@example.com>', $result['in_reply_to']);
        $this->assertEquals('<ref1@example.com> <ref2@example.com>', $result['references']);
    }
}
