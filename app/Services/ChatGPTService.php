<?php
namespace App\Services;

use OpenAI;

class ChatGPTService
{
    protected $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.key'));
    }

    public function streamMessage(string $message, callable $onData): void
    {
        $response = $this->client->chat()->createStreamed([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $message]],
            'max_tokens' => 2048,
            'stream' => true,
        ]);

        foreach ($response as $chunk) {
            $onData($chunk->choices[0]->delta->content ?? '');
        }
    }

}
