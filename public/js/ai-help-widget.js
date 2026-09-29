/* Trợ lý hướng dẫn sử dụng — chatbox nổi. Hội thoại chỉ lưu trong sessionStorage của trình duyệt. */
(function () {
    'use strict';

    var root = document.getElementById('egoHelp');
    if (!root || root.dataset.ready) { return; }
    root.dataset.ready = '1';

    var endpoint = root.dataset.endpoint;
    var routeName = root.dataset.route || '';
    var storeKey = 'egoHelp:v1:' + (root.dataset.user || '');
    var MAX_HISTORY = 8;

    var fab = root.querySelector('.ego-help__fab');
    var panel = root.querySelector('.ego-help__panel');
    var log = root.querySelector('[data-help-log]');
    var welcome = root.querySelector('[data-help-welcome]');
    var form = root.querySelector('[data-help-form]');
    var input = root.querySelector('[data-help-input]');
    var sendBtn = root.querySelector('.ego-help__send');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var busy = false;

    function load() {
        try { return JSON.parse(sessionStorage.getItem(storeKey) || '[]') || []; } catch (e) { return []; }
    }
    function save(items) {
        try { sessionStorage.setItem(storeKey, JSON.stringify(items.slice(-30))); } catch (e) { /* bỏ qua */ }
    }
    var history = load();

    // ---------- Markdown an toàn: escape trước, chỉ cho phép vài định dạng + link nội bộ "/..."
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function inline(s) {
        s = esc(s);
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\[([^\]]+)\]\((\/(?!\/)[^)\s]*)\)/g, function (_, text, href) {
            return '<a href="' + href + '">' + text + '</a>';
        });
        return s;
    }
    function markdown(text) {
        var lines = String(text).replace(/\r\n/g, '\n').split('\n');
        var html = '';
        var list = null;
        var para = [];
        function flushPara() { if (para.length) { html += '<p>' + para.map(inline).join('<br>') + '</p>'; para = []; } }
        function closeList() { if (list) { html += '</' + list + '>'; list = null; } }
        lines.forEach(function (line) {
            var t = line.trim();
            var m;
            if (!t) { flushPara(); closeList(); return; }
            if ((m = t.match(/^#{1,6}\s+(.*)$/))) { flushPara(); closeList(); html += '<h4>' + inline(m[1]) + '</h4>'; return; }
            if ((m = t.match(/^\d+[.)]\s+(.*)$/))) {
                flushPara(); if (list !== 'ol') { closeList(); html += '<ol>'; list = 'ol'; }
                html += '<li>' + inline(m[1]) + '</li>'; return;
            }
            if ((m = t.match(/^[-*•]\s+(.*)$/))) {
                flushPara(); if (list !== 'ul') { closeList(); html += '<ul>'; list = 'ul'; }
                html += '<li>' + inline(m[1]) + '</li>'; return;
            }
            closeList();
            para.push(t);
        });
        flushPara(); closeList();
        return html;
    }

    // ---------- Giao diện
    function scrollDown() { log.scrollTop = log.scrollHeight; }

    function addMessage(role, content, sources) {
        if (welcome) { welcome.hidden = true; }
        var div = document.createElement('div');
        if (role === 'user') {
            div.className = 'ego-help__msg ego-help__msg--user';
            div.textContent = content;
        } else if (role === 'error') {
            div.className = 'ego-help__msg ego-help__msg--error';
            div.innerHTML = inline(content);
        } else {
            div.className = 'ego-help__msg ego-help__msg--bot';
            div.innerHTML = markdown(content);
            var links = (sources || []).filter(function (s) { return s.url && /^\/(?!\/)/.test(s.url); });
            if (links.length) {
                var src = document.createElement('div');
                src.className = 'ego-help__sources';
                src.innerHTML = 'Xem thêm: ' + links.map(function (s) {
                    return '<a href="' + esc(s.url) + '">' + esc(s.title) + '</a>';
                }).join('');
                div.appendChild(src);
            }
        }
        log.appendChild(div);
        scrollDown();
    }

    function render() {
        Array.prototype.slice.call(log.querySelectorAll('.ego-help__msg')).forEach(function (n) { n.remove(); });
        if (welcome) { welcome.hidden = history.length > 0; }
        history.forEach(function (m) { addMessage(m.role, m.content, m.sources); });
    }

    // Vòng sáng "thở" trên nút chỉ để gây chú ý lần đầu; đã mở chat một lần thì tắt hẳn.
    var SEEN_KEY = 'egoHelp:seen';
    try { if (localStorage.getItem(SEEN_KEY) === '1') { root.classList.add('is-seen'); } } catch (e) { /* bỏ qua */ }

    function setOpen(open) {
        if (open) {
            root.classList.add('is-seen');
            try { localStorage.setItem(SEEN_KEY, '1'); } catch (e) { /* bỏ qua */ }
        }
        panel.hidden = !open;
        root.classList.toggle('is-open', open);
        fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { setTimeout(function () { input.focus(); scrollDown(); }, 30); } else { fab.focus(); }
    }

    function typing(show) {
        var el = log.querySelector('.ego-help__typing');
        if (show && !el) {
            el = document.createElement('div');
            el.className = 'ego-help__typing';
            el.innerHTML = '<span></span><span></span><span></span>';
            log.appendChild(el);
            scrollDown();
        } else if (!show && el) {
            el.remove();
        }
    }

    function ask(question) {
        question = String(question || '').trim();
        if (!question || busy) { return; }
        busy = true;
        sendBtn.disabled = true;

        // Chỉ gửi các cặp hỏi–đáp đã có câu trả lời (câu hỏi bị lỗi không gửi lại),
        // để hội thoại luôn xen kẽ user/assistant như nhà cung cấp AI yêu cầu.
        var pairs = [];
        for (var i = 0; i < history.length - 1; i++) {
            if (history[i].role === 'user' && history[i + 1].role === 'assistant') {
                pairs.push({ role: 'user', content: history[i].content });
                pairs.push({ role: 'assistant', content: history[i + 1].content });
                i++;
            }
        }
        var sentHistory = pairs.slice(-MAX_HISTORY);

        history.push({ role: 'user', content: question });
        save(history);
        addMessage('user', question);
        input.value = '';
        autosize();
        typing(true);

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                message: question,
                history: sentHistory,
                route_name: routeName,
                path: location.pathname,
                page_title: document.title
            })
        })
            .then(function (res) { return res.json().catch(function () { return { ok: false }; }).then(function (data) { return { status: res.status, data: data }; }); })
            .then(function (r) {
                typing(false);
                if (r.data && r.data.ok) {
                    history.push({ role: 'assistant', content: r.data.answer, sources: r.data.sources || [] });
                    save(history);
                    addMessage('assistant', r.data.answer, r.data.sources);
                    return;
                }
                var msg = (r.data && (r.data.message || (r.data.errors && Object.values(r.data.errors)[0][0])))
                    || (r.status === 419 ? 'Phiên đăng nhập đã hết hạn, vui lòng tải lại trang.' : 'Trợ lý chưa trả lời được, vui lòng thử lại.');
                if (r.data && r.data.settings_url) { msg += ' [Mở cài đặt AI](' + r.data.settings_url.replace(/^https?:\/\/[^/]+/, '') + ')'; }
                dropUnanswered();
                addMessage('error', msg);
            })
            .catch(function () {
                typing(false);
                dropUnanswered();
                addMessage('error', 'Không kết nối được máy chủ. Kiểm tra mạng và thử lại.');
            })
            .finally(function () {
                busy = false;
                sendBtn.disabled = false;
                input.focus();
            });
    }

    // Câu hỏi không nhận được trả lời: bỏ khỏi lịch sử đã lưu (vẫn hiện trên màn hình đến khi tải lại trang).
    function dropUnanswered() {
        if (history.length && history[history.length - 1].role === 'user') {
            history.pop();
            save(history);
        }
    }

    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }

    // ---------- Sự kiện
    fab.addEventListener('click', function () { setOpen(true); });
    root.querySelector('[data-help-close]').addEventListener('click', function () { setOpen(false); });
    root.querySelector('[data-help-reset]').addEventListener('click', function () {
        history = [];
        save(history);
        render();
        input.focus();
    });
    Array.prototype.forEach.call(root.querySelectorAll('[data-help-suggest]'), function (b) {
        b.addEventListener('click', function () { ask(b.textContent); });
    });
    form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
    input.addEventListener('input', autosize);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); ask(input.value); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { setOpen(false); }
    });

    render();
})();
