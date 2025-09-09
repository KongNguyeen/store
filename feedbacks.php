<?php
require_once 'config/config.php';
require_once 'config/functions.php';
require_once 'middleware/auth.php';

$pdo = getPDO();

// Flash messages
$success = flash('success');
$error = flash('error');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat với hỗ trợ - <?= SITE_NAME ?></title>
    
    <!-- Load stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/feedbacks.css">

   
</head>

<body>
    <div class="chat-container">
        <div class="w-100">
            <!-- Chat Header -->
            <div class="chat-header">
                <h4 class="chat-title">
                    <i class="fas fa-comments"></i>
                    Chat với hỗ trợ
                </h4>
                <div class="d-flex align-items-center gap-3">
                    <div class="chat-status">
                        <div class="status-indicator"></div>
                        <span>Trực tuyến</span>
                    </div>
                    <a href="index.php" class="back-button">
                        <i class="fas fa-arrow-left"></i>
                        Trang chủ
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success mx-3 mt-3">
                    <i class="fas fa-check-circle me-2"></i><?= $success ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger mx-3 mt-3">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Chat Messages -->
            <div class="chat-messages" id="chatMessages">
                <div class="welcome-message">
                    <div class="welcome-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h5>Chào mừng đến với trung tâm hỗ trợ!</h5>
                    <p>Hãy gửi tin nhắn cho chúng tôi. Đội ngũ hỗ trợ sẽ phản hồi sớm nhất có thể.</p>
                </div>
            </div>

            <!-- Chat Input -->
            <div class="chat-input">
                <div class="input-group">
                    <textarea 
                        class="form-control message-input" 
                        id="messageInput"
                        placeholder="Nhập tin nhắn của bạn..."
                        rows="1"
                    ></textarea>
                    <button class="send-button" id="sendButton" type="button">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class ChatSystem {
            constructor() {
                this.userId = <?= $_SESSION['user_id'] ?>;
                this.currentRoomId = null;
                this.messageContainer = document.getElementById('chatMessages');
                this.messageInput = document.getElementById('messageInput');
                this.sendButton = document.getElementById('sendButton');
                this.isFirstLoad = true; // Track first load
                
                this.init();
            }

            init() {
                // Get or create chat room
                this.getOrCreateRoom();
                
                // Event listeners
                this.sendButton.addEventListener('click', () => this.sendMessage());
                this.messageInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });

                // Auto-resize textarea
                this.messageInput.addEventListener('input', () => {
                    this.messageInput.style.height = 'auto';
                    this.messageInput.style.height = Math.min(this.messageInput.scrollHeight, 120) + 'px';
                });

                // Poll for new messages every 3 seconds
                setInterval(() => this.loadMessages(), 3000);
            }

            async getOrCreateRoom() {
                console.log('Starting getOrCreateRoom...');
                console.log('User ID:', this.userId);
                
                try {
                    const response = await fetch('api/chat_simple.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_or_create_room&user_id=${this.userId}`
                    });

                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers.get('content-type'));

                    const text = await response.text();
                    console.log('Raw response:', text);

                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed data:', data);
                        
                        if (data.success) {
                            this.currentRoomId = data.room_id;
                            console.log('Room ID set to:', this.currentRoomId);
                            this.loadMessages();
                        } else {
                            console.error('API error:', data.message);
                            this.showError('Không thể kết nối đến hệ thống chat: ' + data.message);
                        }
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        this.showError('Lỗi phản hồi từ server');
                    }
                } catch (error) {
                    console.error('Network error:', error);
                    this.showError('Lỗi kết nối mạng');
                }
            }

            async loadMessages() {
                if (!this.currentRoomId) return;

                try {
                    const response = await fetch('api/chat_simple.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_messages&room_id=${this.currentRoomId}`
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        this.displayMessages(data.messages);
                    }
                } catch (error) {
                    console.error('Error loading messages:', error);
                }
            }

            displayMessages(messages) {
                // Clear welcome message if exists
                const welcomeMsg = this.messageContainer.querySelector('.welcome-message');
                if (welcomeMsg && messages.length > 0) {
                    welcomeMsg.remove();
                }

                // Clear existing messages
                this.messageContainer.innerHTML = '';

                messages.forEach(message => {
                    const messageEl = this.createMessageElement(message);
                    this.messageContainer.appendChild(messageEl);
                });

                // Always scroll to bottom on first load or when user is at bottom
                const isAtBottom = this.messageContainer.scrollTop + this.messageContainer.clientHeight >= this.messageContainer.scrollHeight - 10;
                if (this.isFirstLoad || isAtBottom) {
                    this.scrollToBottom();
                    this.isFirstLoad = false; // Mark as loaded
                }
            }

            createMessageElement(message) {
                const messageDiv = document.createElement('div');
                const isUser = message.sender_type === 'user';
                
                messageDiv.className = `message ${isUser ? 'message-user' : 'message-admin'}`;
                
                messageDiv.innerHTML = `
                    <div class="message-content">
                        ${this.escapeHtml(message.message)}
                        <div class="message-time">
                            ${this.formatTime(message.created_at)}
                        </div>
                    </div>
                `;

                return messageDiv;
            }

            async sendMessage() {
                const message = this.messageInput.value.trim();
                
                if (!message || !this.currentRoomId) return;

                // Disable send button
                this.sendButton.disabled = true;
                this.sendButton.innerHTML = '<div class="loading"><div class="loading-dot"></div><div class="loading-dot"></div><div class="loading-dot"></div></div>';

                try {
                    const response = await fetch('api/chat_simple.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=send_message&room_id=${this.currentRoomId}&message=${encodeURIComponent(message)}`
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        this.messageInput.value = '';
                        this.messageInput.style.height = 'auto';
                        // Force scroll to bottom after sending message
                        this.loadMessages();
                        setTimeout(() => this.scrollToBottom(), 100);
                    } else {
                        this.showError(data.message || 'Không thể gửi tin nhắn');
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    this.showError('Lỗi kết nối mạng');
                } finally {
                    // Re-enable send button
                    this.sendButton.disabled = false;
                    this.sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
                }
            }

            scrollToBottom() {
                this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
            }

            formatTime(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diff = now - date;
                
                if (diff < 60000) { // Less than 1 minute
                    return 'Vừa xong';
                } else if (diff < 3600000) { // Less than 1 hour
                    return Math.floor(diff / 60000) + ' phút trước';
                } else if (diff < 86400000) { // Less than 1 day
                    return Math.floor(diff / 3600000) + ' giờ trước';
                } else {
                    return date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'});
                }
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML.replace(/\n/g, '<br>');
            }

            showError(message) {
                // Create temporary error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mx-3 mt-3';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${message}`;
                
                const container = document.querySelector('.chat-container .w-100');
                const chatMessages = document.getElementById('chatMessages');
                container.insertBefore(errorDiv, chatMessages);
                
                // Remove after 5 seconds
                setTimeout(() => errorDiv.remove(), 5000);
            }
        }

        // Initialize chat when page loads
        document.addEventListener('DOMContentLoaded', () => {
            new ChatSystem();
        });
    </script>
</body>
</html>
