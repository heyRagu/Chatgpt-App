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
    // {
    //     Log::info('Received message: ' . $request->input('message'));
    //     $message = $request->input('message');

    //     $response = new StreamedResponse(function () use ($message) {
    //         echo "data: {\"status\": \"start\"}\n\n";
    //         ob_flush();
    //         flush();
    //         Log::info('Streaming started');
    //         $this->chatGPT->streamMessage($message, function ($chunk) {
    //             if ($chunk) {
    //                 foreach (str_split($chunk) as $char) {
    //                     echo "data: " . json_encode(['content' => $char]) . "\n\n";
    //                     ob_flush();
    //                     flush();
    //                     usleep(100000); // Simulate typing delay (100ms per character)
    //                 }
    //             }
    //         });
    //         Log::info('Streaming complete');

    //         echo "data: {\"status\": \"complete\"}\n\n";
    //         ob_flush();
    //         flush();
    //     });

    //     $response->headers->set('Content-Type', 'text/event-stream');
    //     $response->headers->set('Cache-Control', 'no-cache');
    //     $response->headers->set('Connection', 'keep-alive');

    //     return $response;
    // }
    {
        $message = $request->query('message');  // Get the message parameter from query

        // Initialize the response as a streamed response
        $response = new StreamedResponse(function () use ($message) {
            echo "data: {\"status\": \"start\"}\n\n";
            ob_flush();
            flush();

            // Assuming $this->chatGPT->streamMessage() streams data progressively
            $this->chatGPT->streamMessage($message, function ($chunk) {
                if ($chunk) {
                    echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                    usleep(100000);  // Simulate typing delay
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



    public function handleChat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = $request->input('message');
        $botResponse = '';

        $this->chatGPT->streamMessage($userMessage, function ($chunk) use (&$botResponse) {
            $botResponse .= $chunk;
        });

        $conversation = Conversation::create([
            'user_id' => auth()->id(),
            'user_message' => $userMessage,
            'chatgpt_response' => $botResponse,
        ]);

        return response()->json([
            'user_message' => $conversation->user_message,
            'chatgpt_response' => $conversation->chatgpt_response,
            'timestamp' => $conversation->created_at->toDateTimeString(),
        ], 201);
    }
    public function getConversation()
    {
        $user = auth()->user();
        $conversations = Conversation::where('user_id',$user->id)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'conversations' => $conversations
        ]);
    }
}
