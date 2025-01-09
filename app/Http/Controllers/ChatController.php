<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Services\ChatGPTService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    protected $chatGPT;

    public function __construct(ChatGPTService $chatGPT)
    {
        $this->chatGPT = $chatGPT;
    }

    public function streamChat(Request $request)
    {
        // Get the message parameter from the request
        $message = $request->query('message');
        $user = auth()->user();

        // Retrieve the previous conversation history for context (user-message and bot-response)
        $conversationHistory = Conversation::where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->pluck('user_message', 'chatgpt_response')
            ->toArray();

        // Initialize the response as a streamed response
        $response = new StreamedResponse(function () use ($message, $conversationHistory) {
            echo "data: {\"status\": \"start\"}\n\n";
            ob_flush();
            flush();

            // Prepare the conversation context (previous user-bot exchanges)
            $contextMessage = $this->generateConversationContext($conversationHistory, $message);

            // Stream the message to ChatGPT, appending the conversation history
            $this->chatGPT->streamMessage($contextMessage, function ($chunk) {
                if ($chunk) {
                    echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                    usleep(100000); // Simulate typing delay (100ms per character)
                }
            });

            echo "data: {\"status\": \"complete\"}\n\n";
            ob_flush();
            flush();
        });

        // Set headers for SSE (Server-Sent Events)
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    // Helper method to generate conversation context (history)
    private function generateConversationContext($conversationHistory, $newMessage)
    {
        $contextMessage = '';

        // Add each prior user-bot message pair to the context
        foreach ($conversationHistory as $userMessage => $chatGptResponse) {
            $contextMessage .= "User: $userMessage\nBot: $chatGptResponse\n";
        }

        // Append the new user message to the context
        $contextMessage .= "User: $newMessage\nBot: ";

        return $contextMessage;
    }

    public function handleChat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = $request->input('message');
        $botResponse = '';
        $user = auth()->user();

        // Retrieve previous conversation history for context
        $conversationHistory = Conversation::where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->pluck('chatgpt_response', 'user_message')
            ->toArray();

        // Add the previous conversation to the message for continuity
        $contextMessage = $this->generateConversationContext($conversationHistory, $userMessage);

        // Send the context message to ChatGPT and collect the response
        $this->chatGPT->streamMessage($contextMessage, function ($chunk) use (&$botResponse) {
            $botResponse .= $chunk;
        });

        // Save the conversation to the database
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'user_message' => $userMessage,
            'chatgpt_response' => $botResponse,
        ]);

        // Return the conversation data
        return response()->json([
            'user_message' => $conversation->user_message,
            'chatgpt_response' => $conversation->chatgpt_response,
            'timestamp' => $conversation->created_at->toDateTimeString(),
        ], 201);
    }

    public function getConversation()
    {
        $user = auth()->user();
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'conversations' => $conversations
        ]);
    }
    public function clearConversation(Request $request)
    {
        $user = auth()->user();

        // Clear all the conversation history for the logged-in user
        Conversation::where('user_id', $user->id)->delete();

        // Return a response confirming the conversation history is cleared
        return response()->json([
            'message' => 'Conversation history cleared successfully.',
        ]);
    }
}
