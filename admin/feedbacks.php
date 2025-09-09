<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Flash message
$success = flash('success');
$error = flash('error');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Chat - Admin</title>
    <!-- Preload critical assets -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" as="script">
    
    <!-- Load stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/Admin_feedbacks.css">
</head>
<body class="loading">
    <!-- Chat Container -->
    <div class="chat-container">
        <!-- Sidebar -->
        <div class="chat-sidebar" id="chatSidebar">
            <!-- Header -->
            <div class="sidebar-header">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <button class="mobile-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="index.php" class="btn-back" style="
                        background: rgba(255, 255, 255, 0.2);
                        color: white;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                        border-radius: 20px;
                        padding: 0.5rem 1rem;
                        text-decoration: none;
                        font-size: 0.85rem;
                        font-weight: 600;
                        transition: all 0.3s ease;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        backdrop-filter: blur(10px);
                    " onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'; this.style.transform='translateY(-2px)'" 
                       onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'; this.style.transform='translateY(0)'">
                        <i class="fas fa-arrow-left"></i>
                        Quay lại Admin
                    </a>
                </div>
                <h4 class="sidebar-title">
                    <i class="fas fa-comments me-2"></i>
                    Chat Admin
                </h4>
                <p class="sidebar-subtitle">Hỗ trợ khách hàng trực tiếp</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card active" id="statActive">
                    <div class="stat-number" id="activeCount">0</div>
                    <div class="stat-label">Đang hoạt động</div>
                </div>
                <div class="stat-card unassigned" id="statUnassigned">
                    <div class="stat-number" id="unassignedCount">0</div>
                    <div class="stat-label">Chưa nhận</div>
                </div>
                <div class="stat-card unread" id="statUnread">
                    <div class="stat-number" id="unreadCount">0</div>
                    <div class="stat-label">Chưa đọc</div>
                </div>
                <div class="stat-card" id="statTotal">
                    <div class="stat-number" id="totalCount">0</div>
                    <div class="stat-label">Tổng phòng</div>
                </div>
            </div>

            <!-- Search -->
            <div class="room-search">
                <input type="text" id="roomSearch" placeholder="Tìm kiếm khách hàng..." 
                       onkeyup="debounce(searchRooms, 300)()">
            </div>

            <!-- Room List -->
            <div class="room-list" id="roomList">
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h5>Chưa có cuộc trò chuyện nào</h5>
                    <p>Các cuộc trò chuyện sẽ xuất hiện ở đây</p>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area" id="chatArea">
            <div class="empty-state">
                <i class="fas fa-comment-dots"></i>
                <h3>Chọn một cuộc trò chuyện</h3>
                <p>Chọn một cuộc trò chuyện từ danh sách bên trái để bắt đầu nhắn tin</p>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentRoomId = null;
        let lastMessageId = 0;
        let chatInterval = null;
        let rooms = [];
        let currentUser = <?= $_SESSION['user_id'] ?>;
        let isFirstLoad = true; // Track first message load

        // Page initialization
        document.addEventListener('DOMContentLoaded', function() {
            document.documentElement.classList.add('loading');
            
            setTimeout(() => {
                console.log('Initializing admin chat system...');
                document.body.classList.remove('loading');
                document.documentElement.classList.remove('loading');
                
                // Initialize chat system
                initializeChat();
                console.log('Loading chat rooms...');
                loadChatRooms();
                console.log('Loading stats...');
                loadStats();
                
                // Set up auto-refresh
                setInterval(loadChatRooms, 5000); // Refresh rooms every 5 seconds
                setInterval(loadStats, 10000); // Refresh stats every 10 seconds
                console.log('Admin chat system initialized successfully');
            }, 100);
        });

        // Initialize chat system
        function initializeChat() {
            // Auto-resize message input
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                });

                // Send message on Enter (Shift+Enter for new line)
                messageInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }
        }

        // Load chat rooms
        async function loadChatRooms() {
            console.log('Loading chat rooms...');
            try {
                const response = await fetch('api/chat_admin_simple.php?action=get_chat_rooms&status=active');
                console.log('Response status:', response.status);
                
                const text = await response.text();
                console.log('Raw response:', text);
                
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.success) {
                    rooms = data.rooms;
                    console.log('Found rooms:', rooms.length);
                    renderRoomList(rooms);
                } else {
                    console.error('API error:', data.message);
                }
            } catch (error) {
                console.error('Error loading chat rooms:', error);
            }
        }

        // Load statistics
        async function loadStats() {
            console.log('Loading stats...');
            try {
                const response = await fetch('api/chat_admin_simple.php?action=get_chat_stats');
                console.log('Stats response status:', response.status);
                
                const text = await response.text();
                console.log('Stats raw response:', text);
                
                const data = JSON.parse(text);
                console.log('Stats parsed data:', data);
                
                if (data.success) {
                    console.log('Updating stats elements...');
                    document.getElementById('totalCount').textContent = data.stats.total_rooms;
                    document.getElementById('activeCount').textContent = data.stats.active_rooms;
                    document.getElementById('unassignedCount').textContent = data.stats.unassigned_rooms;
                    document.getElementById('unreadCount').textContent = data.stats.unread_messages;
                    console.log('Stats updated successfully');
                } else {
                    console.error('Stats API error:', data.message);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Render room list
        function renderRoomList(roomsToRender) {
            console.log('Rendering room list with', roomsToRender.length, 'rooms');
            const roomList = document.getElementById('roomList');
            
            if (!roomList) {
                console.error('Room list element not found!');
                return;
            }
            
            if (roomsToRender.length === 0) {
                console.log('No rooms to display');
                roomList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h5>Chưa có cuộc trò chuyện nào</h5>
                        <p>Các cuộc trò chuyện sẽ xuất hiện ở đây</p>
                    </div>
                `;
                return;
            }

            console.log('Generating HTML for rooms...');
            roomList.innerHTML = roomsToRender.map(room => {
                const isActive = currentRoomId === room.room_id;
                const unreadBadge = room.unread_count > 0 ? 
                    `<div class="unread-badge">${room.unread_count}</div>` : '';
                const statusDot = room.admin_id ? 
                    '<div class="status-dot online"></div>' : 
                    '<div class="status-dot unassigned"></div>';
                
                const lastMessage = room.last_message ? 
                    (room.last_message.length > 30 ? 
                        room.last_message.substring(0, 30) + '...' : 
                        room.last_message) : 'Chưa có tin nhắn';
                
                const lastTime = room.last_message_time ? 
                    formatTime(room.last_message_time) : 
                    formatTime(room.created_at);

                return `
                    <div class="room-item ${isActive ? 'active' : ''}" 
                         onclick="selectRoom(${room.room_id}, '${room.user_name}', '${room.email}')">
                        <div class="room-avatar">
                            ${room.user_name.charAt(0).toUpperCase()}
                        </div>
                        <div class="room-info">
                            <div class="room-name">${room.user_name}</div>
                            <div class="room-last-message">${lastMessage}</div>
                        </div>
                        <div class="room-meta">
                            <div class="room-time">${lastTime}</div>
                            ${unreadBadge}
                            ${statusDot}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Select room
        async function selectRoom(roomId, userName, userEmail) {
            currentRoomId = roomId;
            lastMessageId = 0;
            isFirstLoad = true; // Reset first load flag for new room
            
            // Update UI
            document.querySelectorAll('.room-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.closest('.room-item').classList.add('active');
            
            // Load chat interface
            loadChatInterface(roomId, userName, userEmail);
            
            // Load messages
            await loadMessages();
            
            // Start real-time updates
            if (chatInterval) {
                clearInterval(chatInterval);
            }
            chatInterval = setInterval(loadMessages, 2000);
        }

        // Load chat interface
        function loadChatInterface(roomId, userName, userEmail) {
            const chatArea = document.getElementById('chatArea');
            chatArea.innerHTML = `
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-user-info">
                        <div class="chat-avatar">
                            ${userName.charAt(0).toUpperCase()}
                        </div>
                        <div class="chat-user-details">
                            <h5>${userName}</h5>
                            <div class="status">${userEmail}</div>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <div class="dropdown">
                            <button class="btn-action dropdown-toggle" type="button" data-bs-toggle="dropdown" style="
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                color: white;
                                border: none;
                                border-radius: 20px;
                                padding: 0.6rem 1.2rem;
                                font-size: 0.85rem;
                                font-weight: 600;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                            ">
                                <i class="fas fa-cog"></i> Hành động
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" style="
                                border: none;
                                border-radius: 15px;
                                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                                padding: 0.5rem 0;
                                min-width: 200px;
                            ">
                                <li>
                                    <a class="dropdown-item" href="#" onclick="assignToAdmin(${roomId})" style="
                                        padding: 0.75rem 1.5rem;
                                        color: #495057;
                                        display: flex;
                                        align-items: center;
                                        gap: 0.75rem;
                                        transition: all 0.3s ease;
                                    ">
                                        <i class="fas fa-user-plus text-success"></i>
                                        Gán cho admin khác
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="markAsResolved(${roomId})" style="
                                        padding: 0.75rem 1.5rem;
                                        color: #495057;
                                        display: flex;
                                        align-items: center;
                                        gap: 0.75rem;
                                        transition: all 0.3s ease;
                                    ">
                                        <i class="fas fa-check-circle text-info"></i>
                                        Đánh dấu đã giải quyết
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider" style="margin: 0.5rem 0;"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" onclick="closeRoom(${roomId})" style="
                                        padding: 0.75rem 1.5rem;
                                        color: #dc3545;
                                        display: flex;
                                        align-items: center;
                                        gap: 0.75rem;
                                        transition: all 0.3s ease;
                                    ">
                                        <i class="fas fa-times-circle"></i>
                                        Đóng cuộc trò chuyện
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="messages-container" id="messagesContainer">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Đang tải tin nhắn...</p>
                    </div>
                </div>

                <!-- Message Input -->
                <div class="message-input-container">
                    <div class="message-input-form">
                        <textarea id="messageInput" class="message-input" 
                                placeholder="Nhập tin nhắn..." rows="1"></textarea>
                        <button class="send-button" onclick="sendMessage()" id="sendButton">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            `;
            
            // Reinitialize input
            initializeChat();
        }

        // Load messages
        async function loadMessages() {
            console.log('Loading messages for room:', currentRoomId);
            if (!currentRoomId) return;
            
            try {
                const response = await fetch(
                    `api/chat_admin_simple.php?action=get_room_messages&room_id=${currentRoomId}`
                );
                console.log('Messages response status:', response.status);
                
                const text = await response.text();
                console.log('Messages raw response:', text);
                
                const data = JSON.parse(text);
                console.log('Messages parsed data:', data);
                
                if (data.success) {
                    console.log('Found', data.messages.length, 'messages');
                    renderMessages(data.messages);
                    
                    // Update last message ID for real-time updates
                    if (data.messages.length > 0) {
                        lastMessageId = Math.max(...data.messages.map(m => m.message_id));
                    }
                } else {
                    console.error('Load messages API error:', data.message);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        // Render messages
        function renderMessages(messages) {
            const container = document.getElementById('messagesContainer');
            if (!container) return;
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comment-dots"></i>
                        <h5>Chưa có tin nhắn nào</h5>
                        <p>Hãy bắt đầu cuộc trò chuyện</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = messages.map(message => {
                const isAdmin = message.sender_type === 'admin';
                const messageClass = isAdmin ? 'sent' : 'received';
                const senderName = isAdmin ? 'Admin' : message.full_name;
                
                return `
                    <div class="message ${messageClass}">
                        <div class="message-content">
                            ${!isAdmin ? `<div class="message-sender">${senderName}</div>` : ''}
                            <div class="message-text">${message.message.replace(/\n/g, '<br>')}</div>
                            <div class="message-time">${formatTime(message.created_at)}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Always scroll to bottom on first load or when user is at bottom
            const isAtBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 10;
            if (isFirstLoad || isAtBottom) {
                container.scrollTop = container.scrollHeight;
                isFirstLoad = false; // Mark as loaded
            }
        }

        // Send message
        async function sendMessage() {
            console.log('Admin sending message...');
            if (!currentRoomId) {
                console.error('No room selected');
                return;
            }
            
            const input = document.getElementById('messageInput');
            const sendButton = document.getElementById('sendButton');
            const message = input.value.trim();
            
            if (!message) {
                console.error('Message is empty');
                return;
            }
            
            console.log('Sending message to room:', currentRoomId, 'Message:', message);
            
            // Disable input
            input.disabled = true;
            sendButton.disabled = true;
            
            try {
                const response = await fetch('api/chat_admin_simple.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_message&room_id=${currentRoomId}&message=${encodeURIComponent(message)}`
                });
                
                console.log('Response status:', response.status);
                const text = await response.text();
                console.log('Raw response:', text);
                
                const data = JSON.parse(text);
                console.log('Parsed response:', data);
                
                if (data.success) {
                    console.log('Message sent successfully');
                    input.value = '';
                    input.style.height = 'auto';
                    await loadMessages();
                    // Force scroll to bottom after sending message
                    setTimeout(() => {
                        const container = document.getElementById('messagesContainer');
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }
                    }, 100);
                    await loadChatRooms(); // Refresh room list
                } else {
                    console.error('API error:', data.message);
                    showToast(data.message || 'Có lỗi xảy ra', 'error');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showToast('Có lỗi xảy ra khi gửi tin nhắn', 'error');
            } finally {
                input.disabled = false;
                sendButton.disabled = false;
                input.focus();
            }
        }

        // Close room
        async function closeRoom(roomId) {
            // Show confirmation dialog with better styling
            const result = await showConfirmDialog(
                'Đóng cuộc trò chuyện', 
                'Bạn có chắc muốn đóng cuộc trò chuyện này? Hành động này không thể hoàn tác.',
                'Đóng chat',
                'Hủy'
            );
            
            if (!result) return;
            
            try {
                const response = await fetch('api/chat_admin_simple.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=close_room&room_id=${roomId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success animation
                    showSuccessAnimation();
                    showToast('Cuộc trò chuyện đã được đóng thành công', 'success');
                    
                    // Clear current chat
                    currentRoomId = null;
                    isFirstLoad = true;
                    if (chatInterval) {
                        clearInterval(chatInterval);
                    }
                    
                    // Show empty state with animation
                    const chatArea = document.getElementById('chatArea');
                    chatArea.style.opacity = '0';
                    setTimeout(() => {
                        chatArea.innerHTML = `
                            <div class="empty-state" style="opacity: 0; transform: translateY(20px);">
                                <i class="fas fa-comment-dots"></i>
                                <h3>Chọn một cuộc trò chuyện</h3>
                                <p>Chọn một cuộc trò chuyện từ danh sách bên trái để bắt đầu nhắn tin</p>
                            </div>
                        `;
                        chatArea.style.opacity = '1';
                        const emptyState = chatArea.querySelector('.empty-state');
                        setTimeout(() => {
                            emptyState.style.opacity = '1';
                            emptyState.style.transform = 'translateY(0)';
                        }, 100);
                    }, 300);
                    
                    // Refresh room list and stats
                    await loadChatRooms();
                    await loadStats();
                } else {
                    showToast(data.message || 'Có lỗi xảy ra', 'error');
                }
            } catch (error) {
                console.error('Error closing room:', error);
                showToast('Có lỗi xảy ra', 'error');
            }
        }

        // Search rooms
        function searchRooms() {
            const searchTerm = document.getElementById('roomSearch').value.toLowerCase();
            const filteredRooms = rooms.filter(room => 
                room.user_name.toLowerCase().includes(searchTerm) ||
                room.email.toLowerCase().includes(searchTerm)
            );
            renderRoomList(filteredRooms);
        }

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Format time
        function formatTime(timestamp) {
            const now = new Date();
            const date = new Date(timestamp);
            const diffInHours = (now - date) / (1000 * 60 * 60);
            
            if (diffInHours < 1) {
                const diffInMinutes = Math.floor((now - date) / (1000 * 60));
                return diffInMinutes === 0 ? 'Vừa xong' : `${diffInMinutes} phút`;
            } else if (diffInHours < 24) {
                return `${Math.floor(diffInHours)} giờ`;
            } else {
                return date.toLocaleDateString('vi-VN');
            }
        }

        // Toggle sidebar (mobile)
        function toggleSidebar() {
            const sidebar = document.getElementById('chatSidebar');
            sidebar.classList.toggle('show');
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle'
            };
            
            const colors = {
                success: 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
                error: 'linear-gradient(135deg, #ff6b6b 0%, #feca57 100%)',
                info: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                warning: 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)'
            };
            
            toast.innerHTML = `
                <div style="
                    background: ${colors[type]};
                    color: white;
                    padding: 1rem 1.5rem;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    margin-bottom: 1rem;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    min-width: 300px;
                    font-weight: 500;
                ">
                    <i class="fas ${icons[type]}"></i>
                    <span>${message}</span>
                    <button onclick="closeToast(this)" style="
                        background: none;
                        border: none;
                        color: white;
                        margin-left: auto;
                        cursor: pointer;
                        padding: 5px;
                        border-radius: 50%;
                    ">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                closeToast(toast.querySelector('button'));
            }, 5000);
        }

        function closeToast(btn) {
            const toast = btn.closest('div').parentElement;
            toast.remove();
        }

        // Custom confirm dialog
        function showConfirmDialog(title, message, confirmText = 'OK', cancelText = 'Hủy') {
            return new Promise((resolve) => {
                // Create backdrop
                const backdrop = document.createElement('div');
                backdrop.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    backdrop-filter: blur(5px);
                `;

                // Create dialog
                const dialog = document.createElement('div');
                dialog.style.cssText = `
                    background: white;
                    border-radius: 15px;
                    padding: 2rem;
                    max-width: 400px;
                    width: 90%;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    transform: scale(0.8);
                    transition: transform 0.2s ease;
                `;

                dialog.innerHTML = `
                    <div style="text-align: center;">
                        <div style="
                            width: 60px;
                            height: 60px;
                            margin: 0 auto 1.5rem;
                            background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: white;
                            font-size: 1.5rem;
                        ">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 style="margin: 0 0 1rem; color: #2c3e50; font-size: 1.3rem; font-weight: 600;">${title}</h4>
                        <p style="margin: 0 0 2rem; color: #7f8c8d; line-height: 1.5;">${message}</p>
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <button id="cancelBtn" style="
                                padding: 0.75rem 1.5rem;
                                border: 2px solid #e9ecef;
                                background: white;
                                color: #6c757d;
                                border-radius: 25px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                min-width: 100px;
                            ">${cancelText}</button>
                            <button id="confirmBtn" style="
                                padding: 0.75rem 1.5rem;
                                border: none;
                                background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
                                color: white;
                                border-radius: 25px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                min-width: 100px;
                            ">${confirmText}</button>
                        </div>
                    </div>
                `;

                backdrop.appendChild(dialog);
                document.body.appendChild(backdrop);

                // Animate in
                setTimeout(() => {
                    dialog.style.transform = 'scale(1)';
                }, 10);

                // Event handlers
                const confirmBtn = dialog.querySelector('#confirmBtn');
                const cancelBtn = dialog.querySelector('#cancelBtn');

                // Hover effects
                confirmBtn.addEventListener('mouseenter', () => {
                    confirmBtn.style.transform = 'translateY(-2px)';
                    confirmBtn.style.boxShadow = '0 10px 25px rgba(255, 107, 107, 0.4)';
                });
                confirmBtn.addEventListener('mouseleave', () => {
                    confirmBtn.style.transform = 'translateY(0)';
                    confirmBtn.style.boxShadow = 'none';
                });

                cancelBtn.addEventListener('mouseenter', () => {
                    cancelBtn.style.borderColor = '#6c757d';
                    cancelBtn.style.color = '#495057';
                });
                cancelBtn.addEventListener('mouseleave', () => {
                    cancelBtn.style.borderColor = '#e9ecef';
                    cancelBtn.style.color = '#6c757d';
                });

                confirmBtn.addEventListener('click', () => {
                    document.body.removeChild(backdrop);
                    resolve(true);
                });

                cancelBtn.addEventListener('click', () => {
                    document.body.removeChild(backdrop);
                    resolve(false);
                });

                backdrop.addEventListener('click', (e) => {
                    if (e.target === backdrop) {
                        document.body.removeChild(backdrop);
                        resolve(false);
                    }
                });
            });
        }

        // Assign to admin
        async function assignToAdmin(roomId) {
            showToast('Tính năng đang phát triển', 'info');
        }

        // Mark as resolved
        async function markAsResolved(roomId) {
            const result = await showConfirmDialog(
                'Đánh dấu đã giải quyết',
                'Cuộc trò chuyện này sẽ được đánh dấu là đã giải quyết nhưng vẫn mở.',
                'Xác nhận',
                'Hủy'
            );
            
            if (result) {
                showToast('Đã đánh dấu cuộc trò chuyện là đã giải quyết', 'success');
            }
        }

        // Success animation
        function showSuccessAnimation() {
            const animation = document.createElement('div');
            animation.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                color: white;
                border-radius: 50%;
                width: 80px;
                height: 80px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2rem;
                z-index: 10001;
                opacity: 0;
                transition: all 0.3s ease;
            `;
            
            animation.innerHTML = '<i class="fas fa-check"></i>';
            document.body.appendChild(animation);
            
            setTimeout(() => {
                animation.style.opacity = '1';
                animation.style.transform = 'translate(-50%, -50%) scale(1.2)';
            }, 10);
            
            setTimeout(() => {
                animation.style.opacity = '0';
                animation.style.transform = 'translate(-50%, -50%) scale(0.8)';
                setTimeout(() => {
                    if (animation.parentNode) {
                        document.body.removeChild(animation);
                    }
                }, 300);
            }, 1500);
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (chatInterval) {
                clearInterval(chatInterval);
            }
        });
    </script>
</body>
</html>