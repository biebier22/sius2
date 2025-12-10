<div class="chat-container">
    <style>
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 500px;
            background: #f9fbff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 26px rgba(15,23,42,0.08);
        }
        .chat-header {
            background: linear-gradient(120deg, #00bcd4, #2979ff);
            color: #fff;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .chat-back-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.2s;
        }
        .chat-back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .chat-header-title {
            flex: 1;
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #f9fbff;
        }
        .chat-message {
            display: flex;
            gap: 8px;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .chat-message.user {
            justify-content: flex-end;
        }
        .chat-message.ai {
            justify-content: flex-start;
        }
        .message-bubble {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
            position: relative;
        }
        .chat-message.user .message-bubble {
            background: linear-gradient(120deg, #2979ff, #00bcd4);
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .chat-message.ai .message-bubble {
            background: #fff;
            color: #0f172a;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(15,23,42,0.08);
        }
        .message-time {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
            text-align: right;
        }
        .chat-message.ai .message-time {
            text-align: left;
        }
        .chat-message.user .message-time {
            color: rgba(255, 255, 255, 0.8);
        }
        .chat-input-area {
            background: #fff;
            padding: 12px 18px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            align-items: flex-end;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
        }
        .chat-input-wrapper {
            flex: 1;
            position: relative;
        }
        .chat-input {
            width: 100%;
            min-height: 44px;
            max-height: 120px;
            padding: 10px 14px;
            border: 1px solid #cbd5f5;
            border-radius: 22px;
            font-size: 14px;
            font-family: 'Segoe UI', Arial, sans-serif;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
        }
        .chat-input:focus {
            border-color: #2979ff;
            box-shadow: 0 0 0 2px rgba(41,121,255,0.1);
        }
        .chat-send-btn {
            background: linear-gradient(120deg, #2979ff, #00bcd4);
            color: #fff;
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            transition: transform 0.2s, box-shadow 0.2s;
            flex-shrink: 0;
        }
        .chat-send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(41,121,255,0.3);
        }
        .chat-send-btn:active {
            transform: scale(0.95);
        }
        .chat-send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .chat-empty {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
            font-size: 14px;
        }
        .chat-empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 12px 16px;
            background: #fff;
            border-radius: 18px;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(15,23,42,0.08);
        }
        .typing-dot {
            width: 8px;
            height: 8px;
            background: #94a3b8;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.7;
            }
            30% {
                transform: translateY(-8px);
                opacity: 1;
            }
        }
    </style>

    <div class="chat-header">
        <button class="chat-back-btn" onclick="location.reload();" aria-label="Kembali">←</button>
        <h2 class="chat-header-title">Tanya AI</h2>
    </div>

    <div class="chat-messages" id="chatMessages">
        <div class="chat-empty">
            <div class="chat-empty-icon">🤖</div>
            <div>Mulai percakapan dengan AI</div>
            <div style="font-size: 12px; margin-top: 8px; opacity: 0.7;">Tanyakan apapun tentang sistem ujian</div>
        </div>
    </div>

    <div class="chat-input-area">
        <div class="chat-input-wrapper">
            <textarea 
                id="chatInput" 
                class="chat-input" 
                placeholder="Ketik pesan..."
                rows="1"
                onkeydown="handleChatKeyDown(event)"
                oninput="autoResizeTextarea(this)"
            ></textarea>
        </div>
        <button class="chat-send-btn" id="chatSendBtn" onclick="sendMessage()" aria-label="Kirim pesan">
            ➤
        </button>
    </div>
</div>

<script>
let chatMessages = document.getElementById('chatMessages');
let chatInput = document.getElementById('chatInput');
let chatSendBtn = document.getElementById('chatSendBtn');
let isEmpty = true;

function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
}

function handleChatKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function sendMessage() {
    const message = chatInput.value.trim();
    if (!message) return;

    // Remove empty state
    if (isEmpty) {
        chatMessages.innerHTML = '';
        isEmpty = false;
    }

    // Add user message
    addMessage(message, 'user');
    
    // Clear input
    chatInput.value = '';
    chatInput.style.height = 'auto';
    
    // Disable send button
    chatSendBtn.disabled = true;
    
    // Show typing indicator
    showTypingIndicator();
    
    // Simulate AI response (replace with actual API call later)
    setTimeout(() => {
        hideTypingIndicator();
        const aiResponse = generateAIResponse(message);
        addMessage(aiResponse, 'ai');
        chatSendBtn.disabled = false;
        chatInput.focus();
    }, 1500);
}

function addMessage(text, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${type}`;
    
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    bubble.textContent = text;
    
    const time = document.createElement('div');
    time.className = 'message-time';
    const now = new Date();
    time.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    
    bubble.appendChild(time);
    messageDiv.appendChild(bubble);
    
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

function showTypingIndicator() {
    const typingDiv = document.createElement('div');
    typingDiv.className = 'chat-message ai';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `
        <div class="typing-indicator">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        </div>
    `;
    chatMessages.appendChild(typingDiv);
    scrollToBottom();
}

function hideTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.remove();
    }
}

function scrollToBottom() {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function generateAIResponse(userMessage) {
    // Placeholder AI responses - replace with actual API integration later
    const lowerMessage = userMessage.toLowerCase();
    
    if (lowerMessage.includes('ujian') || lowerMessage.includes('jadwal')) {
        return 'Untuk informasi jadwal ujian, Anda dapat melihatnya di menu rekap ujian. Apakah ada yang ingin Anda tanyakan lebih lanjut tentang jadwal ujian?';
    } else if (lowerMessage.includes('wasling') || lowerMessage.includes('pengawas')) {
        return 'Anda dapat menambahkan wasling atau pengawas ruang melalui menu yang tersedia. Apakah Anda membutuhkan bantuan untuk menambahkan data pengawas?';
    } else if (lowerMessage.includes('laporan') || lowerMessage.includes('rekap')) {
        return 'Laporan dan rekap ujian dapat diakses melalui menu Laporan Ujian. Data akan menampilkan ringkasan ujian berdasarkan lokasi Anda.';
    } else if (lowerMessage.includes('catatan') || lowerMessage.includes('temuan')) {
        return 'Catatan temuan dapat dicatat melalui menu Catatan Temuan. Fitur ini membantu Anda mencatat hal-hal penting selama ujian berlangsung.';
    } else if (lowerMessage.includes('halo') || lowerMessage.includes('hai') || lowerMessage.includes('hello')) {
        return 'Halo! Saya di sini untuk membantu Anda. Ada yang bisa saya bantu terkait sistem ujian?';
    } else {
        return 'Terima kasih atas pertanyaan Anda. Saya dapat membantu Anda dengan informasi tentang jadwal ujian, pengawas, laporan, dan catatan temuan. Ada yang ingin Anda tanyakan?';
    }
}

// Enable send button when input has text
chatInput.addEventListener('input', function() {
    chatSendBtn.disabled = !this.value.trim();
});

// Focus input on load
setTimeout(() => {
    chatInput.focus();
}, 100);
</script>

