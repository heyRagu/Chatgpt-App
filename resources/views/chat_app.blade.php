<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Chat app</title>
    <link rel="stylesheet" href="{{ asset('/css/gpt.css') }}">
    <style>

    </style>
</head>

<body>
    <div class="d-flex mx-auto justify-content-between">

        <div id="sidebar">
            <div id="progressBarContainer">
                <div id="progressBar"></div>
            </div>
            <div>
                <button id="clearChatButton" class="btn btn-info">New Chat</button>
            </div>
            <h3>Conversation History</h3>
            <div id="conversationContainer">

            </div>
        </div>

        <div id="chat-container">
            <h3 class="text-center mb-4"><span><img src="/imgs/ChatGPT-Logo.svg.png" alt="ChatGPT"
                        style="width: 15px; height: 15px; vertical-align: middle; display: inline-block;">&nbsp;</span>Chat
                with ChatGPT</h3>

            <div id="messages"></div>

            <div id="input-container">
                <input type="text" id="user-message" placeholder="Message ChatGPT..." autocomplete="off" />
                <button id="send-message">Send</button>
            </div>

        </div>
    </div>

    <script>
        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $('#send-message').click(function() {
                const $userMessageInput = $('#user-message');
                const $messagesContainer = $('#messages');

                const message = $userMessageInput.val().trim();
                if (!message) {
                    alert('Please type a message!');
                    return;
                }

                // Disable the button to prevent multiple clicks
                $('#send-message').prop('disabled', true);

                // Append the user's message to the chat window
                const userDiv = $('<div>')
                    .addClass('message user-message')
                    .text(` ${message}`);
                $messagesContainer.append(userDiv);

                $userMessageInput.val('');

                // Send the message to the /chat route to save the conversation
                $.ajax({
                    url: '/chat',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        message: message
                    }),
                    success: function(data) {
                        // After the message is saved, start the stream for the response
                        const eventSource = new EventSource(
                            `/stream-chat?message=${encodeURIComponent(message)}`);

                        const botDiv = $('<div>')
                            .addClass('message bot-message')
                            .html(
                                '<img src="/imgs/ChatGPT-Logo.svg.png" alt="ChatGPT" style="width: 20px; height: 20px; vertical-align: middle; display: inline-block;"> <span class="bot-text" style="white-space: pre-line; vertical-align: middle;"></span>'
                            );
                        const botText = botDiv.find('.bot-text');
                        $messagesContainer.append(botDiv);

                        // Listen for streaming messages
                        eventSource.onmessage = function(event) {
                            const data = JSON.parse(event.data);

                            if (data.status === 'start') {
                                console.log('Stream started');
                            } else if (data.status === 'complete') {
                                console.log('Stream completed');
                                eventSource.close();

                                // Re-enable the send button
                                $('#send-message').prop('disabled', false);
                            } else if (data.content) {
                                // Append each chunk of the message to simulate typing
                                botText.append(data.content);
                                $messagesContainer.scrollTop($messagesContainer[0]
                                    .scrollHeight);
                            }
                        };

                        eventSource.onerror = function(xhr, status, error) {
                            console.error('Error during streaming');
                            alert('Something went wrong. Please try again.');
                            eventSource.close();

                            // Re-enable the button after error
                            $('#send-message').prop('disabled', false);
                        };
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status,
                            error); // Print status and error details
                        console.error('XHR Details:', xhr); // XHR (XMLHttpRequest) object

                        alert('Error saving the conversation. Please try again.');

                        // Re-enable the send button
                        $('#send-message').prop('disabled', false);
                    }
                });
            });



            const $conversationContainer = $('#conversationContainer');
            let lastConversationId = null;

            function fetchNewConversations() {
                $.ajax({
                    url: '/conversations',
                    method: 'GET',
                    success: function(data) {
                        if (data.conversations && data.conversations.length > 0) {
                            // Filter for new conversations that have an ID greater than lastConversationId
                            const newConversations = data.conversations.filter(conv => conv.id >
                                lastConversationId);

                            if (newConversations.length > 0) {
                                // Update lastConversationId to the most recent conversation's ID
                                lastConversationId = newConversations[newConversations.length - 1].id;

                                newConversations.reverse().forEach(function(conv) {
                                    const conversationHtml = `
                            <div class="conversation-item">
                                <p><strong>User:</strong> ${conv.user_message || 'No message'}</p>
                                <p><strong>ChatGPT:</strong> ${conv.chatgpt_response || 'No response'}</p>
                                <p><strong>Time:</strong> ${conv.created_at ? new Date(conv.created_at).toLocaleString() : 'Unknown'}</p>
                            </div>
                            <hr>
                        `;
                                    $conversationContainer.prepend(conversationHtml);
                                });
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching new conversations:', error);
                    }
                });
            }

            // Fetch new conversations every 2.5 seconds
            setInterval(fetchNewConversations, 5500);



            $('#toggleSidebar').click(function() {
                const $sidebar = $('#sidebar');
                if ($sidebar.is(':visible')) {
                    $sidebar.hide();
                    $(this).text('Show Conversation');
                } else {
                    $sidebar.show();
                    $(this).text('Hide Conversation');
                }
            });

            const $sidebar = $('#sidebar');
            const $progressBar = $('#progressBar');

            $sidebar.on('scroll', function() {
                const scrollTop = $sidebar.scrollTop();
                const scrollHeight = $sidebar.prop('scrollHeight');
                const clientHeight = $sidebar.innerHeight();

                const scrollPercent = (scrollTop / (scrollHeight - clientHeight)) * 100;
                $progressBar.css('height', `${scrollPercent}%`);
            });
        });
        $('#clearChatButton').on('click', function() {
            $.ajax({
                url: '/clear', // Ensure the correct API URL
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + localStorage.getItem(
                        'auth_token') // Use stored token or CSRF token
                },
                success: function(data) {
                    alert(data.message); // Display confirmation message
                    // Optionally, reset the UI here, for example, clear chat history in the frontend
                    $('#conversationContainer').html(
                    ''); // Assuming you have a div with id 'chatHistory' for displaying conversation
                    location.reload();
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error); // Handle errors
                }
            });
        });
    </script>

</body>

</html>
