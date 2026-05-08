// Prevent multiple definitions
if (typeof window.ChatbotAI !== 'undefined') {
    // Chatbot already defined, skip redefinition.
} else {

class ChatbotAI {
    constructor() {
        // Prevent multiple instances
        if (window.chatbotAIInstance) {
            return window.chatbotAIInstance;
        }
        
        this.isOpen = false;
        this.isTyping = false;
        this.init();
        
        // Store instance
        window.chatbotAIInstance = this;
    }

    init() {
        this.createWidget();
        this.bindEvents();
        this.showWelcomeMessage();
        this.adjustPosition();
    }

    adjustPosition() {
        // Thêm delay nhỏ để đảm bảo DOM đã load xong
        setTimeout(() => {
            const adminChatWidget = document.getElementById('chatWidget');
            const chatbotWidget = document.querySelector('.chatbot-widget');
            
            if (chatbotWidget) {
                if (adminChatWidget) {
                    // Nếu có admin chat, đặt chatbot AI ở bên trái
                    chatbotWidget.classList.add('left-positioned');
                    chatbotWidget.style.left = '20px';
                    chatbotWidget.style.right = 'auto';
                    chatbotWidget.style.bottom = '20px';
                    chatbotWidget.style.display = 'block';
                    
                    // Thêm label AI
                    if (!chatbotWidget.querySelector('.ai-label')) {
                        const label = document.createElement('div');
                        label.className = 'ai-label';
                        label.style.cssText = `
                            position: absolute;
                            top: -25px;
                            left: 50%;
                            transform: translateX(-50%);
                            background: #007bff;
                            color: white;
                            padding: 2px 8px;
                            border-radius: 10px;
                            font-size: 10px;
                            font-weight: bold;
                            z-index: 1001;
                        `;
                        label.textContent = 'AI';
                        chatbotWidget.appendChild(label);
                    }
                } else {
                    // Nếu không có admin chat, đặt chatbot ở bên phải
                    chatbotWidget.classList.remove('left-positioned');
                    chatbotWidget.style.right = '20px';
                    chatbotWidget.style.left = 'auto';
                    chatbotWidget.style.bottom = '20px';
                    chatbotWidget.style.display = 'block';
                    
                }
            }
        }, 100);
    }

    createWidget() {
        const widget = document.createElement('div');
        widget.className = 'chatbot-widget';
        widget.innerHTML = `
            <div class="chatbot-container" id="chatbotContainer">
                <div class="chatbot-header">
                    <h4>🤖 Trợ lý AI tìm sản phẩm</h4>
                    <button class="chatbot-close" id="chatbotClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="chatbot-messages" id="chatbotMessages" role="log" aria-live="polite" aria-relevant="additions">
                    <!-- Messages will be added here -->
                </div>
                <div class="chatbot-input">
                    <input type="text" id="chatbotInput" placeholder="Nhập câu hỏi của bạn..." aria-label="Nhap cau hoi" autocomplete="off">
                    <button class="chatbot-send" id="chatbotSend" type="button" aria-label="Gui tin nhan">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
            <button class="chatbot-trigger" id="chatbotTrigger" type="button" aria-label="Mo tro ly AI">
                <i class="fas fa-robot"></i>
            </button>
        `;
        
        document.body.appendChild(widget);
        
        // Lưu reference để sử dụng sau
        this.widget = widget;
    }

    bindEvents() {
        const trigger = document.getElementById('chatbotTrigger');
        const container = document.getElementById('chatbotContainer');
        const closeBtn = document.getElementById('chatbotClose');
        const input = document.getElementById('chatbotInput');
        const sendBtn = document.getElementById('chatbotSend');

        trigger.addEventListener('click', () => this.toggle());
        closeBtn.addEventListener('click', () => this.close());
        
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        sendBtn.addEventListener('click', () => this.sendMessage());

        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.widget.contains(e.target) && this.isOpen) {
                this.close();
            }
        });
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        const container = document.getElementById('chatbotContainer');
        const trigger = document.getElementById('chatbotTrigger');
        
        container.classList.add('active');
        trigger.classList.add('active');
        this.isOpen = true;
        
        // Focus input
        setTimeout(() => {
            document.getElementById('chatbotInput').focus();
        }, 300);
    }

    close() {
        const container = document.getElementById('chatbotContainer');
        const trigger = document.getElementById('chatbotTrigger');
        
        container.classList.remove('active');
        trigger.classList.remove('active');
        this.isOpen = false;
    }

    showWelcomeMessage() {
        setTimeout(() => {
            this.addBotMessage(
                'Xin chào! Tôi là trợ lý AI của cửa hàng. Tôi có thể giúp bạn tìm kiếm sản phẩm. Hãy cho tôi biết bạn đang tìm gì nhé!',
                [
                    'Tìm điện thoại',
                    'Tìm laptop',
                    'Sản phẩm giá rẻ',
                    'Sản phẩm mới nhất'
                ]
            );
        }, 1000);
    }

    async sendMessage() {
        const input = document.getElementById('chatbotInput');
        const message = input.value.trim();
        
        if (!message || this.isTyping) return;
        
        // Add user message
        this.addUserMessage(message);
        input.value = '';
        
        // Show typing indicator
        this.showTypingIndicator();
        
        try {
            // Sử dụng đường dẫn tuyệt đối
            const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/');
            const apiUrl = baseUrl + 'api/chatbot_ai.php';
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message: message }),
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.hideTypingIndicator();
                this.addBotMessage(data.response, data.suggestions, data.products);
            } else {
                this.hideTypingIndicator();
                this.addBotMessage('Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.');
            }
        } catch (error) {
            console.error('Chatbot error:', error);
            this.hideTypingIndicator();
            this.addBotMessage('Xin lỗi, không thể kết nối đến máy chủ. Vui lòng thử lại sau.');
        }
    }

    addUserMessage(message) {
        const messagesContainer = document.getElementById('chatbotMessages');
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message user';
        messageDiv.innerHTML = `
            <div class="message-avatar">U</div>
            <div class="message-content">${this.escapeHtml(message)}</div>
        `;
        
        messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }

    addBotMessage(message, suggestions = [], products = []) {
        const messagesContainer = document.getElementById('chatbotMessages');
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message bot';
        
        let productsHtml = '';
        if (products && products.length > 0) {
            productsHtml = `
                <div class="products-grid">
                    ${products.map(product => `
                        <a href="product.php?id=${product.product_id}" class="product-card" target="_blank">
                            <img src="${product.product_image || 'assets/img/no-image.png'}" 
                                 alt="${this.escapeHtml(product.name)}" 
                                 class="product-image"
                                   loading="lazy"
                                   decoding="async"
                                 onerror="this.src='assets/img/no-image.png'">
                            <div class="product-name">${this.escapeHtml(product.name)}</div>
                            <div class="product-price">${this.formatPrice(product.price)}đ</div>
                        </a>
                    `).join('')}
                </div>
            `;
        }
        
        let suggestionsHtml = '';
        if (suggestions && suggestions.length > 0) {
            suggestionsHtml = `
                <div class="suggestions">
                    ${suggestions.map((suggestion, index) => `
                        <button class="suggestion-btn" type="button" data-suggestion="${this.escapeHtml(suggestion)}" aria-label="Goi y: ${this.escapeHtml(suggestion)}" onclick="chatbot.handleSuggestionClick(this)">${this.escapeHtml(suggestion)}</button>
                    `).join('')}
                </div>
            `;
        }
        
        messageDiv.innerHTML = `
            <div class="message-avatar">AI</div>
            <div class="message-content">
                ${this.escapeHtml(message)}
                ${productsHtml}
                ${suggestionsHtml}
            </div>
        `;
        
        messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }

    handleSuggestion(suggestion) {
        const input = document.getElementById('chatbotInput');
        input.value = suggestion;
        this.sendMessage();
    }

    handleSuggestionClick(button) {
        const suggestion = button.getAttribute('data-suggestion');
        const input = document.getElementById('chatbotInput');
        input.value = suggestion;
        this.sendMessage();
    }

    showTypingIndicator() {
        if (this.isTyping) return;
        
        this.isTyping = true;
        const messagesContainer = document.getElementById('chatbotMessages');
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot typing-indicator';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = `
            <div class="message-avatar">AI</div>
            <div class="message-content">
                <div class="typing-indicator">
                    Đang trả lời
                    <div class="typing-dots">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            </div>
        `;
        
        messagesContainer.appendChild(typingDiv);
        this.scrollToBottom();
    }

    hideTypingIndicator() {
        this.isTyping = false;
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }

    scrollToBottom() {
        const messagesContainer = document.getElementById('chatbotMessages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatPrice(price) {
        return new Intl.NumberFormat('vi-VN').format(price);
    }
}

// Initialize chatbot when page loads
document.addEventListener('DOMContentLoaded', function() {
    const initChatbot = () => {
        if (!window.chatbot) {
            window.chatbot = new ChatbotAI();
        }
    };

    if ('requestIdleCallback' in window) {
        requestIdleCallback(initChatbot, { timeout: 2000 });
    } else {
        setTimeout(initChatbot, 500);
    }
});

// Add CSS dynamically if not already added
if (!document.querySelector('link[href*="chatbot.css"]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/chatbot.css';
    document.head.appendChild(link);
}

// Close the if-else block and store ChatbotAI in window
window.ChatbotAI = ChatbotAI;
}
