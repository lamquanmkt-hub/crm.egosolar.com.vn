(function () {
    'use strict';

    var root = document.getElementById('egoAiApp');
    if (!root) return;

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var list = document.getElementById('egoAiConversationList');
    var messages = document.getElementById('egoAiMessages');
    var form = document.getElementById('egoAiForm');
    var input = document.getElementById('egoAiInput');
    var sendButton = document.getElementById('egoAiSend');
    var provider = document.getElementById('egoAiProvider');
    var title = document.getElementById('egoAiConversationTitle');
    var welcome = document.getElementById('egoAiWelcome');
    var search = document.getElementById('egoAiConversationSearch');
    var historyToggle = document.getElementById('egoAiHistoryToggle');
    var liveContext = document.getElementById('egoAiLiveContext');
    var liveContextTitle = document.getElementById('egoAiLiveContextTitle');
    var liveContextMeta = document.getElementById('egoAiLiveContextMeta');
    var usageText = document.getElementById('egoAiUsageText');
    var currentConversationId = null;
    var loading = false;

    function endpoint(name) {
        return root.getAttribute('data-' + name + '-url') || '';
    }

    function headers() {
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf ? csrf.content : ''
        };
    }

    function request(url, options) {
        options = options || {};
        options.headers = Object.assign(headers(), options.headers || {});
        options.credentials = 'same-origin';
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok || data.ok === false) {
                    var error = new Error(data.message || 'Không thể xử lý yêu cầu.');
                    error.payload = data;
                    error.status = response.status;
                    throw error;
                }
                return data;
            });
        });
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function markdown(value) {
        var source = escapeHtml(value || '').replace(/\r\n/g, '\n');
        var lines = source.split('\n');
        var html = '';
        var inList = false;

        function inline(text) {
            return text
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/__(.+?)__/g, '<strong>$1</strong>')
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>');
        }

        lines.forEach(function (line) {
            var trimmed = line.trim();
            var listMatch = trimmed.match(/^[-•]\s+(.+)$/);
            var numberMatch = trimmed.match(/^\d+[.)]\s+(.+)$/);

            if (listMatch || numberMatch) {
                if (!inList) {
                    html += '<ul>';
                    inList = true;
                }
                html += '<li>' + inline((listMatch || numberMatch)[1]) + '</li>';
                return;
            }

            if (inList) {
                html += '</ul>';
                inList = false;
            }

            if (!trimmed) {
                html += '<div class="ego-ai-md-gap"></div>';
            } else if (/^###\s+/.test(trimmed)) {
                html += '<h4>' + inline(trimmed.replace(/^###\s+/, '')) + '</h4>';
            } else if (/^##\s+/.test(trimmed)) {
                html += '<h3>' + inline(trimmed.replace(/^##\s+/, '')) + '</h3>';
            } else if (/^#\s+/.test(trimmed)) {
                html += '<h2>' + inline(trimmed.replace(/^#\s+/, '')) + '</h2>';
            } else if (/^---+$/.test(trimmed)) {
                html += '<hr>';
            } else {
                html += '<p>' + inline(trimmed) + '</p>';
            }
        });

        if (inList) html += '</ul>';
        return html;
    }

    function removeWelcome() {
        if (welcome && welcome.parentNode) {
            welcome.parentNode.removeChild(welcome);
            welcome = null;
        }
    }

    function scrollBottom() {
        window.requestAnimationFrame(function () {
            messages.scrollTop = messages.scrollHeight;
        });
    }

    function contextCard(context) {
        if (!context || !context.title) return null;
        var card = document.createElement('section');
        card.className = 'ego-ai-data-card';

        var metrics = (context.metrics || []).map(function (item) {
            return '<div class="ego-ai-data-metric ego-ai-tone-' + escapeHtml(item.tone || 'info') + '">' +
                '<i class="bi ' + escapeHtml(item.icon || 'bi-bar-chart') + '"></i>' +
                '<span><small>' + escapeHtml(item.label || '') + '</small><strong>' + escapeHtml(item.value || '') + '</strong></span>' +
            '</div>';
        }).join('');

        var items = (context.items || []).slice(0, 10).map(function (item) {
            var content = '<i class="bi ' + escapeHtml(item.icon || 'bi-circle') + '"></i>' +
                '<span><strong>' + escapeHtml(item.title || '') + '</strong>' +
                '<small>' + escapeHtml(item.subtitle || '') + '</small>' +
                '<em>' + escapeHtml(item.meta || '') + '</em></span>' +
                '<b class="ego-ai-badge ego-ai-badge--' + escapeHtml(item.tone || 'info') + '">' + escapeHtml(item.badge || '') + '</b>';
            if (item.url) {
                return '<a href="' + escapeHtml(item.url) + '" class="ego-ai-data-item" target="_self">' + content + '</a>';
            }
            return '<div class="ego-ai-data-item">' + content + '</div>';
        }).join('');

        card.innerHTML = '<div class="ego-ai-data-card__head">' +
                '<div><span>CRM ĐÃ LỌC QUYỀN</span><strong>' + escapeHtml(context.title) + '</strong></div>' +
                '<div class="ego-ai-data-scope"><i class="bi bi-shield-check"></i>' + escapeHtml(context.scope || '') + '</div>' +
            '</div>' +
            '<p class="ego-ai-data-summary">' + escapeHtml(context.summary || '') + '</p>' +
            (metrics ? '<div class="ego-ai-data-metrics">' + metrics + '</div>' : '') +
            (items ? '<div class="ego-ai-data-items">' + items + '</div>' : '') +
            '<div class="ego-ai-data-foot"><span><i class="bi bi-calendar3"></i>' + escapeHtml(context.period || '') + '</span><span>' + escapeHtml(context.updated_at || '') + '</span></div>';

        return card;
    }

    function renderContext(context) {
        var card = contextCard(context);
        if (!card) return;
        removeWelcome();
        messages.appendChild(card);
        if (liveContext) {
            liveContext.hidden = false;
            liveContextTitle.textContent = context.title || '';
            liveContextMeta.textContent = [context.scope, context.period].filter(Boolean).join(' · ');
        }
        scrollBottom();
    }

    function renderMessage(message, options) {
        options = options || {};
        removeWelcome();
        var isUser = message.role === 'user';
        var wrapper = document.createElement('article');
        wrapper.className = 'ego-ai-message' + (isUser ? ' ego-ai-message--user' : '');
        var content = isUser ? escapeHtml(message.content || '').replace(/\n/g, '<br>') : markdown(message.content || '');
        var denied = message.metadata && message.metadata.permission_denied;
        if (denied) wrapper.classList.add('ego-ai-message--denied');
        wrapper.innerHTML =
            '<div class="ego-ai-message__avatar"><i class="bi ' + (isUser ? 'bi-person' : (denied ? 'bi-shield-x' : 'bi-stars')) + '"></i></div>' +
            '<div class="ego-ai-message__body">' +
                '<div class="ego-ai-message__bubble ego-ai-markdown">' + content + '</div>' +
                '<div class="ego-ai-message__meta">' +
                    '<span>' + escapeHtml(message.created_at || '') + '</span>' +
                    (!isUser && message.model ? '<span>· ' + escapeHtml(message.model) + '</span>' : '') +
                '</div>' +
            '</div>';
        messages.appendChild(wrapper);

        if (!isUser && options.renderMetadataContext !== false && message.metadata && message.metadata.crm_context) {
            renderContext(message.metadata.crm_context);
        }
        scrollBottom();
    }

    function showTyping() {
        var node = document.createElement('div');
        node.id = 'egoAiTyping';
        node.className = 'ego-ai-typing';
        node.innerHTML = '<i class="bi bi-stars"></i><span><b></b><b></b><b></b></span><em>Đang kiểm tra quyền và xử lý dữ liệu...</em>';
        messages.appendChild(node);
        scrollBottom();
    }

    function hideTyping() {
        var node = document.getElementById('egoAiTyping');
        if (node) node.remove();
    }

    function showError(message, settingsUrl) {
        hideTyping();
        removeWelcome();
        var node = document.createElement('div');
        node.className = 'ego-ai-message ego-ai-message--denied';
        node.innerHTML = '<div class="ego-ai-message__avatar"><i class="bi bi-exclamation-triangle"></i></div>' +
            '<div class="ego-ai-message__body"><div class="ego-ai-message__bubble ego-ai-markdown"><p>' + escapeHtml(message) + '</p>' +
            (settingsUrl ? '<p><a href="' + escapeHtml(settingsUrl) + '">Mở cấu hình API AI</a></p>' : '') + '</div></div>';
        messages.appendChild(node);
        scrollBottom();
    }

    function setLoading(value) {
        loading = value;
        input.disabled = value || !provider || !provider.value;
        sendButton.disabled = value || !provider || !provider.value;
        root.classList.toggle('is-loading', value);
    }

    function setActiveConversation(id) {
        currentConversationId = id ? String(id) : null;
        list.querySelectorAll('[data-conversation-id]').forEach(function (item) {
            item.classList.toggle('is-active', String(item.getAttribute('data-conversation-id')) === currentConversationId);
        });
    }

    function resetChat() {
        currentConversationId = null;
        setActiveConversation(null);
        title.textContent = 'Cuộc trò chuyện mới';
        messages.innerHTML = '<div class="ego-ai-welcome" id="egoAiWelcome"><div class="ego-ai-welcome__mark"><i class="bi bi-stars"></i></div><span>TRỢ LÝ AI THEO ROLE</span><h2>Bắt đầu cuộc trò chuyện mới</h2><p>AI chỉ truy cập dữ liệu đúng quyền tài khoản hiện tại.</p></div>';
        welcome = document.getElementById('egoAiWelcome');
        if (liveContext) liveContext.hidden = true;
        input.focus();
    }

    function addConversationItem(conversation) {
        var empty = document.getElementById('egoAiHistoryEmpty');
        if (empty) empty.remove();
        var existing = list.querySelector('[data-conversation-id="' + conversation.id + '"]');
        var button = existing || document.createElement('button');
        button.type = 'button';
        button.className = 'ego-ai-conversation-item is-active';
        button.setAttribute('data-conversation-id', conversation.id);
        button.setAttribute('data-show-url', conversation.show_url || '');
        button.setAttribute('data-delete-url', conversation.delete_url || '');
        button.setAttribute('data-title', conversation.title || 'Cuộc trò chuyện');
        button.innerHTML = '<span class="ego-ai-conversation-item__icon"><i class="bi bi-chat-square-text"></i></span>' +
            '<span class="ego-ai-conversation-item__copy"><strong>' + escapeHtml(conversation.title || 'Cuộc trò chuyện') + '</strong><small>' + escapeHtml(conversation.last_message_at || 'Vừa xong') + '</small></span>' +
            '<span class="ego-ai-conversation-item__delete" data-delete-conversation title="Xóa"><i class="bi bi-trash3"></i></span>';
        list.insertBefore(button, list.firstChild);
        setActiveConversation(conversation.id);
    }

    function loadConversation(button) {
        var url = button.getAttribute('data-show-url');
        if (!url || loading) return;
        setActiveConversation(button.getAttribute('data-conversation-id'));
        title.textContent = button.getAttribute('data-title') || 'Cuộc trò chuyện';
        messages.innerHTML = '<div class="ego-ai-typing"><i class="bi bi-arrow-repeat"></i><em>Đang tải hội thoại...</em></div>';
        if (liveContext) liveContext.hidden = true;

        request(url, { method: 'GET' })
            .then(function (data) {
                messages.innerHTML = '';
                welcome = null;
                title.textContent = data.conversation.title;
                (data.messages || []).forEach(function (message) {
                    renderMessage(message, { renderMetadataContext: true });
                });
                if (!data.messages || !data.messages.length) resetChat();
                root.classList.remove('is-history-open');
            })
            .catch(function (error) { showError(error.message); });
    }

    function deleteConversation(button, event) {
        event.preventDefault();
        event.stopPropagation();
        var url = button.getAttribute('data-delete-url');
        if (!url || !window.confirm('Xóa cuộc trò chuyện này?')) return;
        request(url, { method: 'DELETE' }).then(function () {
            var active = String(button.getAttribute('data-conversation-id')) === currentConversationId;
            button.remove();
            if (active) resetChat();
        }).catch(function (error) { window.alert(error.message); });
    }

    function updateUsage(usage) {
        if (!usageText || !usage) return;
        usageText.textContent = String(usage.used_today || 0) + '/' + String(usage.daily_limit || 0);
    }

    function sendMessage(text) {
        text = String(text || '').trim();
        if (!text || loading || !provider || !provider.value) return;

        renderMessage({ role: 'user', content: text, created_at: 'Vừa xong' }, { renderMetadataContext: false });
        input.value = '';
        input.style.height = 'auto';
        setLoading(true);
        showTyping();

        request(endpoint('send'), {
            method: 'POST',
            body: JSON.stringify({
                conversation_id: currentConversationId,
                provider_id: provider.value,
                message: text,
                path: root.getAttribute('data-current-path') || '/ai'
            })
        }).then(function (data) {
            hideTyping();
            currentConversationId = String(data.conversation.id);
            title.textContent = data.conversation.title;
            addConversationItem(data.conversation);
            if (data.crm_context) renderContext(data.crm_context);
            renderMessage(data.assistant_message, { renderMetadataContext: false });
            updateUsage(data.usage);
        }).catch(function (error) {
            showError(error.message, error.payload && error.payload.settings_url);
            if (error.payload && error.payload.conversation) addConversationItem(error.payload.conversation);
        }).finally(function () {
            setLoading(false);
            input.focus();
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage(input.value);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage(input.value);
        }
    });

    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 150) + 'px';
    });

    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-conversation-id]');
        if (!button) return;
        if (event.target.closest('[data-delete-conversation]')) {
            deleteConversation(button, event);
            return;
        }
        loadConversation(button);
    });

    document.querySelectorAll('[data-ai-prompt]').forEach(function (button) {
        button.addEventListener('click', function () {
            input.value = button.getAttribute('data-ai-prompt') || '';
            input.dispatchEvent(new Event('input'));
            input.focus();
        });
    });

    [document.getElementById('egoAiNew'), document.getElementById('egoAiClear')].forEach(function (button) {
        if (button) button.addEventListener('click', resetChat);
    });

    if (search) {
        search.addEventListener('input', function () {
            var term = this.value.trim().toLowerCase();
            list.querySelectorAll('[data-conversation-id]').forEach(function (item) {
                item.hidden = term && !String(item.getAttribute('data-title') || '').toLowerCase().includes(term);
            });
        });
    }

    if (historyToggle) {
        historyToggle.addEventListener('click', function () {
            root.classList.toggle('is-history-open');
        });
    }

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            input.focus();
        }
    });

    setLoading(false);
})();
