<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Chat app</title>
    <link rel="stylesheet" href="{{asset('/css/gpt.css')}}">
    <style>

    </style>
</head>

<body>
    <div class="d-flex mx-auto justify-content-between">

        <div id="sidebar">
            <div id="progressBarContainer">
                <div id="progressBar"></div>
            </div>
            <h3>Conversation History</h3>
            <div id="conversationContainer">

            </div>
        </div>

        <div id="chat-container">
            <h3 class="text-center mb-4"><span><img src="/imgs/ChatGPT-Logo.svg.png" alt="ChatGPT" style="width: 15px; height: 15px; vertical-align: middle; display: inline-block;">&nbsp;</span>Chat with ChatGPT</h3>

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

                const userDiv = $('<div>')
                    .addClass('message user-message')
                    .text(` ${message}`);
                $messagesContainer.append(userDiv);

                $userMessageInput.val('');

                $.ajax({
                    url: '/chat',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        message
                    }),
                    success: function(data) {
                        const botDiv = $('<div>')
                            .addClass('message bot-message')
                            .html(
                                '<img src="/imgs/ChatGPT-Logo.svg.png" alt="ChatGPT" style="width: 20px; height: 20px; vertical-align: middle; display: inline-block;"> <span style="white-space: pre-line; vertical-align: middle;">' +
                                data.chatgpt_response + '</span>');


                        $messagesContainer.append(botDiv);

                        $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
                    },
                    error: function() {
                        alert('Something went wrong. Please try again.');
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
                            const newConversations = data.conversations.filter(conv => conv.id > (
                                lastConversationId || 0));

                            if (newConversations.length > 0) {
                                lastConversationId = data.conversations[0].id;

                                newConversations.reverse().forEach(function(
                                    conv) {
                                    const conversationHtml = `
                                <div class="conversation-item">
                                    <p><strong>User:</strong> ${conv.user_message || 'No message'}</p>
                                    <p><strong>ChatGPT:</strong> ${conv.chatgpt_response || 'No response'}</p>
                                    <p><strong>Time:</strong> ${conv.created_at ? new Date(conv.created_at).toLocaleString() : 'Unknown'}</p>
                                </div>
                                <hr>
                            `;
                                    $conversationContainer.prepend(
                                        conversationHtml);
                                });
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching new conversations:', error);
                    }
                });
            }

            setInterval(fetchNewConversations, 2500);


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
    </script>

</body>

</html>
