<?php

namespace Tests\Unit;

use App\Models\ChatMessage;
use App\Services\ChatBotService;
use Tests\TestCase;

class ChatBotFallbackTest extends TestCase
{
    private ChatBotService $bot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bot = new ChatBotService;
    }

    public function test_offline_fallback_answers_clinic_hours(): void
    {
        $reply = $this->bot->reply('What are your clinic hours?');

        $this->assertStringContainsString('Monday', $reply);
        $this->assertStringContainsString('9:00 AM', $reply);
    }

    public function test_offline_fallback_answers_services_question(): void
    {
        $reply = $this->bot->reply('what services do you offer');

        $this->assertStringContainsString('Teeth Cleaning', $reply);
    }

    public function test_offline_fallback_answers_booking_question(): void
    {
        $reply = $this->bot->reply('how do I book an appointment');

        $this->assertStringContainsString('Book Appointment', $reply);
    }

    public function test_offline_fallback_handles_greeting(): void
    {
        $reply = $this->bot->reply('hello');

        $this->assertStringContainsString('Welcome', $reply);
    }

    public function test_unknown_question_gets_default_answer(): void
    {
        $reply = $this->bot->reply('zzzz qqqq xyzzy');

        $this->assertStringContainsString('Sorry', $reply);
    }

    public function test_chat_message_senders_are_stored(): void
    {
        $this->assertSame('user', ChatMessage::SENDER_USER);
        $this->assertSame('bot', ChatMessage::SENDER_BOT);
    }
}
