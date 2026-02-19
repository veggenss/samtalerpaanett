function setupWebSocket() {
    ws = new WebSocket(
        "ws://localhost:8080/chat?userId=" + encodeURIComponent(currentUserId),
    );

    ws.onopen = () => {
        console.log("Tilkobling til websocket åpnet :)");
    };

    ws.onclose = () => {
        console.log("Tilkobling til websocket lukket :(");
        appendSystemMessage("Tilkoblingen ble lukket. :(");
    };

    ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        console.log(data);
        if (activeChatType === "global" && data.convId == null) {
           appendMessage(data);
        }
        else if (activeChatType == data.type && activeConv === data.convId) {
            appendMessage(data);
        }
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    };
}
