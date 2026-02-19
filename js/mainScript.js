const messagesDiv = document.getElementById('messages');
const input = document.getElementById('messageInput');
const sendButton = document.getElementById('sendButton');
const newDM = document.getElementById('newDM');
const dmList = document.getElementById('DMList');
const globalEnable = document.getElementById('global-enable');
const currentUserId = window.currentUserId;
const currentUsername = window.currentUsername;
const currentProfilePictureUrl = window.currentProfilePictureUrl;

let activeChatType = "global";
let activeConvId = null;
let recipientId = "all";
let sending = false;
let ws = null;

console.log(`User Id: ${currentUserId} \nUsername: ${currentUsername} \nProfile Picture URL: ${currentProfilePictureUrl}`); //debug :3

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

    if (data.userId == currentUserId) {
        wrapper.style.backgroundColor = "#E9E9FF";
        wrapper.style.flexDirection = "row-reverse";
        wrapper.style.textAlign = "right";
        wrapper.style.marginLeft = "auto";
    }

    if (data.type == "direct" && (data.recipientId == currentUserId || data.userId === currentUserId) && data.message != null) {
        updatePreviewStr(data.message, data.convId);
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

function updatePreviewStr(str, convId) {
    parent = document.getElementById('conversation-' + convId);
    child = parent.querySelector(".conversation-prevStr");
    if (!child) {
        console.warn("Preview child not found in conv: ", convId);
        return;
    }
    child.textContent = str;
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

    function loadGlobalLog(){
        fetch('/samtalerpanett/Handler/GlobalChatHandler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({action: 'getLogs'})
        })
        .then(res => res.json())
        .then(data => {
            messagesDiv.innerHTML = '';
            console.log("Global message data:", data);
            data.globalLog.forEach(message => {
                const standardized = {
                    userId: message.sender_id,
                    username: message.sender_name,
                    profilePictureUrl: message.sender_pfp,
                    message: message.message
                };
                appendMessage(standardized);
            })
        })
    }

    function loadConversationDiv() {
        fetch('/samtalerpanett/Handler/DmHandler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'loadConversationDiv', user_id: currentUserId })
        })
        .then(res => res.json())
        .then(data => {
            console.log("loadConvData:", data);
            if (data.success === true && Array.isArray(data.conversations)) {
                data.conversations.forEach(conv => {
                    renderConversationList(conv);
                })
            }
        })
        .catch(err => {
            console.error('LoadConvDivErr', err);
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

        fetch('/samtalerpanett/Handler/DmHandler.php?action=getUserId&reciverUser=' + encodeURIComponent(reciverUser))
        .then(res => res.json())
        .then(data => {
            console.log("reciverUserData", data);
            if (data.success === false) {
                alert(data.response);
                return;
            }
            fetch('/samtalerpanett/Handler/DmHandler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({action: 'createConversation', user1_id: currentUserId, user2_id: data.reciverUserId })
            })
            .then(res => res.json())
            .then(data => {
                console.log("createConvData", data);
                if (data.success === false) {
                    alert(data.response);
                    return;
                }
                alert(data.response);
                loadConversationDiv();
            })
            .catch(err => {
                console.error('NewConvErr_createConv', err);
            })
        })
        .catch(err => {
            console.error('NewConvErr_getUserId', err);
        })
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
            activeConvId = conv.conversation_id;
            recipientId = conv.recipientId;
            loadConvLog(conv);
        });

        dmList.appendChild(wrapper);
    }

    function loadConvLog(conv) {
        messagesDiv.innerHTML = '';

        fetch('/samtalerpanett/Handler/DmHandler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({action: 'loadConversationLog', conversation_id: conv.conversation_id, user2_id: conv.recipientId, user1_id: currentUserId, user1_name: currentUsername, user2_name: conv.recipientUsername})
        })
        .then(res => res.json())
        .then(data => {
            console.log("loadConvMsgData:", data)
            if (data.success === false) {
                alert(data.response);
                return;
            }
            messagesDiv.innerHTML = '';
            data.messageData.forEach(message => {
                appendMessage(message, true);
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            })
        })
        .catch(err => {
                console.error('LoadConvLog', err);
        })
    }

    function sendMessage() {
        console.log("Sendt melding (sendMessage())");
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

        // console.log(messageData);

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
