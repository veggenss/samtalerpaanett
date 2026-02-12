const messagesDiv = document.getElementById('messages');
const input = document.getElementById('messageInput');
const sendButton = document.getElementById('sendButton');
const newDM = document.getElementById('newDM');
const dmList = document.getElementById('DMList');
const globalEnable = document.getElementById('global-enable');
const currentUserId = window.currentUserId;
const currentUsername = window.currentUsername;
const currentProfilePictureUrl = window.currentProfilePictureUrl;

console.log('User Id: ${currentUserId} \nUsername: ${currentUsername} \nProfile Picture URL: ${currentProfilePictureUrl}'); //debug :3
let recipientId = "all";
let activeChatType = "global";
let sending = false;
let ws = null;

function appendMessage(data) {
    const wrapper = document.createElement('div');
    wrapper.classList.add('message');

    const avatar = document.createElement('img');
    avatar.classList.add('avatar');
    avatar.src = data.profilePictureUrl || 'default.png';

    const content = document.createElement('div');
    content.classList.add('message-content');

    const username = document.createElement('span');
    username.classList.add('username');
    username.textContent = data.username || 'Ukjent';

    const text = document.createElement('div');
    text.classList.add('text');
    text.textContent = data.message;

    if (data.username === "[System]") {
        text.style.color = "#8B193C";
        username.style.color = "#8B193C";
        wrapper.style.backgroundColor = "#FFF1F2";
    }

    if (data.username === currentUsername) {
        wrapper.style.backgroundColor = "#E9E9FF";
        wrapper.style.flexDirection = "row-reverse";
        wrapper.style.textAlign = "right";
        wrapper.style.marginLeft = "auto";
    }

    content.appendChild(username);
    content.appendChild(text);
    wrapper.appendChild(avatar);
    wrapper.appendChild(content);

    messagesDiv.prepend(wrapper);
}

function appendSystemMessage(message) {
    appendMessage({
        username: "[System]",
        message,
        profilePictureUrl: "assets/icons/default.png"
    });
}

document.addEventListener('DOMContentLoaded', () => {
    function init() {
        setupWebSocket();
        loadGlobalLog();
        loadConversationDiv();
        setupEventListeners();
    }

    function setupEventListeners() {
        sendButton.onclick = sendMessage;

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });

        newDM.addEventListener('click', () => {
            newConversation();
        });

        globalEnable.addEventListener('click', () => {
            activeChatType = "global";
            recipientId = "all";
            loadGlobalLog();
        });
    }

    function loadGlobalLog() {
        fetch('/samtalerpanett/global_chat/get_global_logs.php')
            .then(res => res.json())
            .then(data => {
                messagesDiv.innerHTML = '';
                data.forEach(message => appendMessage(message, true));
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            })
            .catch(console.error);
    }

    function loadConversationDiv() {
        fetch('/samtalerpanett/direct_messages/dm_functions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'loadConversationDiv', user_id: currentUserId })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success === true && Array.isArray(data.conversations)) {
                    data.conversations.forEach(conv => {
                        renderConversationList(conv);
                    })
                }
            })
            .catch(err => {
                console.error('Fetch Error', err);
            })
    }

    function newConversation() {
        const reciverUser = prompt("Skriv in brukernavn til bruker du vil ha samtale med");
        if (!reciverUser) {
            return;
        }
        if (currentUsername === reciverUser) {
            alert("Du kan ikke starte samtale med degselv");
            return;
        }

        fetch('/samtalerpanett/direct_messages/dm_functions.php?action=get_user_id&reciverUser=' + encodeURIComponent(reciverUser))
            .then(res => res.json())
            .then(data => {
                console.log(data.reciverUserId);
                if (data.success === false) {
                    alert(data.response);
                    return;
                }
                fetch('/samtalerpanett/direct_messages/dm_functions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({action: 'createConversation', user1_id: currentUserId, user2_id: data.reciverUserId })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success === false) {
                            alert(data.response);
                            return;
                        }
                        alert(data.response);
                        loadConversationDiv();
                    });
            })
            .catch(err => {
                console.error('Fetch error', err);
            });
    }


    function renderConversationList(conv) {
        if (document.getElementById('conversation-' + conv.conversation_id)) return;

        const wrapper = document.createElement('div');
        wrapper.classList.add('conversation');
        wrapper.id = 'conversation-' + conv.conversation_id;

        const recipientWrapper = document.createElement('div');
        recipientWrapper.classList.add('conversation-user');

        const recipientTextWrapper = document.createElement('div');
        recipientTextWrapper.classList.add('conversation-userText');

        const recipientUsername = document.createElement('span');
        recipientUsername.classList.add('conversation-name');
        recipientUsername.textContent = conv.recipientUsername;

        const recipientPrevStr = document.createElement('span');
        recipientPrevStr.classList.add('conversation-prevStr');
        recipientPrevStr.textContent = conv.prevStr;

        const recipientAvatar = document.createElement('img');
        recipientAvatar.classList.add('conversation-avatar');
        recipientAvatar.src = conv.recipient_profile_icon;


        recipientWrapper.appendChild(recipientAvatar);
        recipientTextWrapper.appendChild(recipientUsername);
        recipientTextWrapper.appendChild(recipientPrevStr);
        recipientWrapper.appendChild(recipientTextWrapper);
        wrapper.appendChild(recipientWrapper);

        wrapper.addEventListener('click', () => {
            activeChatType = "direct";
            recipientId = conv.recipientId;
            loadConvLog(conv);
        });

        dmList.appendChild(wrapper);
    }

    function loadConvLog(conv) {
        messagesDiv.innerHTML = '';

        fetch('/samtalerpanett/direct_messages/dm_functions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({action: 'loadConversationLog', conversation_id: conv.conversation_id, user2_id: conv.recipientId, user1_id: currentUserId, user1_name: currentUsername, user2_name: conv.recipientUsername})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success === false) {
                alert(data.response);
                return;
            }
            messagesDiv.innerHTML = '';
            data.forEach(message => {
                appendMessage(message, true);
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            });
        });
    }

    function sendMessage() {
        if (sending) return;
        sending = true;

        const text = input.value.trim();
        if (text === '') {
            sending = false;
            return;
        }

        if (text.length > 400) {
            sending = false;
            appendSystemMessage("Meldingen er for lang. Maks 400 tegn.");
            return;
        }

        const messageData = {
            recipientId: recipientId,
            type: activeChatType,
            username: currentUsername,
            userId: currentUserId,
            message: text,
            profilePictureUrl: currentProfilePictureUrl,
        };

        console.log(messageData);

        if (ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify(messageData));
        }
        else {
            appendSystemMessage("WebSocket er frakoblet.");
        }

        input.value = '';
        setTimeout(() => { sending = false; }, 100);
    }

    init();
});