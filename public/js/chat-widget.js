(function () {
  if (window.__EGO_CHAT_WIDGET_LOADED__) return;
  window.__EGO_CHAT_WIDGET_LOADED__ = true;

  const state = {
    baseUrl: '',
    csrf: '',
    myId: 0,
    users: [],
    conversations: [],
    activeConversationId: 0,
    activeUserId: 0,
    lastId: 0,
    tab: 'conversations',
    search: '',
    root: null,
    mode: 'widget'
  };

  function qs(sel, root = document) {
    return root.querySelector(sel);
  }

  function qsa(sel, root = document) {
    return Array.from(root.querySelectorAll(sel));
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function initials(text) {
    const s = String(text || 'U').trim();
    return s ? s[0].toUpperCase() : 'U';
  }

  async function api(path, options = {}) {
    const headers = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers || {})
    };

    if (state.csrf) {
      headers['X-CSRF-TOKEN'] = state.csrf;
    }

    const res = await fetch(state.baseUrl + path, {
      credentials: 'same-origin',
      ...options,
      headers
    });

    if (!res.ok) {
      let text = '';
      try { text = await res.text(); } catch (e) {}
      throw new Error(text || ('HTTP ' + res.status));
    }

    return await res.json();
  }

  function renderMessage(m, container) {
    const me = parseInt(m.user_id) === parseInt(state.myId);
    const row = document.createElement('div');
    row.className = 'ego-msg-row ' + (me ? 'me' : 'them');

    row.innerHTML = `
      <div class="ego-msg-bubble">
        <div class="ego-msg-name">${escapeHtml(m.sender_name || 'Không rõ')}</div>
        <div class="ego-msg-text">${escapeHtml(m.body || '')}</div>
        <div class="ego-msg-time">${escapeHtml(m.created_at || '')}</div>
      </div>
    `;

    container.appendChild(row);
  }

  function renderMessages(messages, body) {
    body.innerHTML = '';
    if (!messages || !messages.length) {
      body.innerHTML = `
        <div class="ego-chat-empty ego-messenger-empty">
          <div>
            <div style="font-size:42px;margin-bottom:8px;">👋</div>
            <h3 style="font-weight:950;color:#0f172a;">Chưa có tin nhắn</h3>
            <p>Bắt đầu cuộc trò chuyện bằng tin nhắn đầu tiên.</p>
          </div>
        </div>
      `;
      state.lastId = 0;
      return;
    }

    messages.forEach(m => {
      renderMessage(m, body);
      state.lastId = Math.max(state.lastId, parseInt(m.id || 0));
    });

    body.scrollTop = body.scrollHeight;
  }

  function filterList(list, keys) {
    const term = state.search.trim().toLowerCase();
    if (!term) return list;
    return list.filter(item => keys.some(k => String(item[k] || '').toLowerCase().includes(term)));
  }

  function renderList(ctx) {
    const listEl = ctx.list;
    const statusEl = ctx.status;
    if (!listEl) return;

    let html = '';

    if (state.tab === 'conversations') {
      const conversations = filterList(state.conversations, ['title', 'subtitle', 'last_message']);

      html += `<div class="${ctx.sectionClass}">Gần đây</div>`;

      if (!conversations.length) {
        html += `<div style="padding:14px;color:#64748b;font-weight:750;">Chưa có cuộc trò chuyện.</div>`;
      }

      conversations.forEach(c => {
        html += `
          <button class="${ctx.itemClass} ${parseInt(c.id) === parseInt(state.activeConversationId) ? 'active' : ''}"
                  type="button"
                  data-conversation-id="${c.id}"
                  data-title="${escapeHtml(c.title || 'Không rõ')}"
                  data-subtitle="${escapeHtml(c.subtitle || '')}"
                  data-initial="${escapeHtml(c.initial || initials(c.title))}">
            <div class="${ctx.avatarClass}">${escapeHtml(c.initial || initials(c.title))}</div>
            <div class="${ctx.metaClass}">
              <div class="${ctx.nameClass}">${escapeHtml(c.title || 'Không rõ')}</div>
              <div class="${ctx.lastClass}">${escapeHtml(c.last_message || '')}</div>
            </div>
            <div class="${ctx.timeClass}">${escapeHtml(c.last_time || '')}</div>
          </button>
        `;
      });
    } else {
      const users = filterList(state.users, ['name', 'email']);

      html += `<div class="${ctx.sectionClass}">Nhân viên</div>`;

      if (!users.length) {
        html += `<div style="padding:14px;color:#64748b;font-weight:750;">Không tìm thấy nhân viên.</div>`;
      }

      users.forEach(u => {
        html += `
          <button class="${ctx.itemClass} ${parseInt(u.id) === parseInt(state.activeUserId) ? 'active' : ''}"
                  type="button"
                  data-user-id="${u.id}"
                  data-title="${escapeHtml(u.name || 'Không rõ')}"
                  data-subtitle="${escapeHtml(u.email || '')}"
                  data-initial="${escapeHtml(u.initial || initials(u.name))}">
            <div class="${ctx.avatarClass} ${u.online ? 'online' : ''}">${escapeHtml(u.initial || initials(u.name))}</div>
            <div class="${ctx.metaClass}">
              <div class="${ctx.nameClass}">${escapeHtml(u.name || 'Không rõ')}</div>
              <div class="${ctx.lastClass}">${escapeHtml(u.email || '')}</div>
            </div>
          </button>
        `;
      });
    }

    listEl.innerHTML = html;

    if (statusEl) {
      statusEl.textContent = `${state.conversations.length} cuộc trò chuyện · ${state.users.length} nhân viên`;
    }

    listEl.querySelectorAll('[data-conversation-id]').forEach(btn => {
      btn.addEventListener('click', () => openConversation(btn.dataset.conversationId, {
        title: btn.dataset.title,
        subtitle: btn.dataset.subtitle,
        initial: btn.dataset.initial
      }, ctx));
    });

    listEl.querySelectorAll('[data-user-id]').forEach(btn => {
      btn.addEventListener('click', () => openDirectUser(btn.dataset.userId, {
        title: btn.dataset.title,
        subtitle: btn.dataset.subtitle,
        initial: btn.dataset.initial
      }, ctx));
    });
  }

  function setCurrent(ctx, info) {
    if (ctx.title) ctx.title.textContent = info.title || 'Không rõ';
    if (ctx.sub) ctx.sub.textContent = info.subtitle || 'Đang hoạt động';
    if (ctx.avatar) ctx.avatar.textContent = info.initial || initials(info.title);

    if (ctx.input) ctx.input.disabled = false;
    if (ctx.send) ctx.send.disabled = false;

    ctx.input?.focus();
  }

  async function openConversation(conversationId, info, ctx) {
    state.activeConversationId = parseInt(conversationId);
    state.activeUserId = 0;
    state.lastId = 0;
    setCurrent(ctx, info);

    ctx.body.innerHTML = `<div class="ego-chat-empty ego-messenger-empty"><div>Đang tải tin nhắn...</div></div>`;

    try {
      const data = await api(`/chat/${conversationId}/messages/json`);
      renderMessages(data.messages || [], ctx.body);
      renderList(ctx);
    } catch (e) {
      ctx.body.innerHTML = `<div class="ego-chat-empty ego-messenger-empty"><div>Không tải được tin nhắn.</div></div>`;
    }
  }

  async function openDirectUser(userId, info, ctx) {
    state.activeUserId = parseInt(userId);
    setCurrent(ctx, info);

    ctx.body.innerHTML = `<div class="ego-chat-empty ego-messenger-empty"><div>Đang mở cuộc trò chuyện...</div></div>`;

    try {
      const form = new FormData();
      form.append('user_id', userId);

      const data = await api('/chat/direct-json', {
        method: 'POST',
        body: form
      });

      state.activeConversationId = parseInt(data.conversation_id);
      state.lastId = 0;

      const exists = state.conversations.some(c => parseInt(c.id) === parseInt(data.conversation.id));
      if (!exists) state.conversations.unshift(data.conversation);

      renderMessages(data.messages || [], ctx.body);
      renderList(ctx);
    } catch (e) {
      ctx.body.innerHTML = `<div class="ego-chat-empty ego-messenger-empty"><div>Không mở được cuộc trò chuyện.</div></div>`;
    }
  }

  async function sendMessage(ctx) {
    const body = (ctx.input?.value || '').trim();
    if (!body || !state.activeConversationId) return;

    ctx.send.disabled = true;

    try {
      const form = new FormData();
      form.append('body', body);

      const data = await api(`/chat/${state.activeConversationId}/send-json`, {
        method: 'POST',
        body: form
      });

      ctx.input.value = '';
      autoResize(ctx.input);

      if (data.message) {
        renderMessage(data.message, ctx.body);
        state.lastId = Math.max(state.lastId, parseInt(data.message.id || 0));
        ctx.body.scrollTop = ctx.body.scrollHeight;
      }

      if (data.conversation) {
        state.conversations = state.conversations.filter(c => parseInt(c.id) !== parseInt(data.conversation.id));
        state.conversations.unshift(data.conversation);
        renderList(ctx);
      }
    } catch (e) {
      alert('Không gửi được tin nhắn. Vui lòng thử lại.');
    } finally {
      ctx.send.disabled = false;
      ctx.input.focus();
    }
  }

  async function poll(ctx) {
    if (!state.activeConversationId || !ctx.body) return;

    try {
      const data = await api(`/chat/${state.activeConversationId}/messages/json?after_id=${state.lastId}`);
      const messages = data.messages || [];
      if (!messages.length) return;

      let hasOther = false;
      messages.forEach(m => {
        renderMessage(m, ctx.body);
        state.lastId = Math.max(state.lastId, parseInt(m.id || 0));
        if (parseInt(m.user_id) !== parseInt(state.myId)) hasOther = true;
      });

      ctx.body.scrollTop = ctx.body.scrollHeight;

      if (hasOther) {
        const ting = qs('#tingSound');
        if (ting) {
          ting.currentTime = 0;
          ting.play().catch(() => {});
        }
      }
    } catch (e) {}
  }

  function autoResize(input) {
    if (!input) return;
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  }

  async function boot(ctx) {
    state.root = ctx.root;
    state.mode = ctx.mode;
    state.baseUrl = ctx.root?.dataset.baseurl || '';
    state.csrf = ctx.root?.dataset.csrf || qs('meta[name="csrf-token"]')?.content || '';
    state.myId = parseInt(ctx.root?.dataset.myid || window.CHAT_ME_ID || 0);

    try {
      const data = await api('/chat/widget-data');
      state.users = data.users || [];
      state.conversations = data.conversations || [];
      renderList(ctx);

      const active = parseInt(ctx.root?.dataset.activeConversation || 0);
      if (active) {
        const c = state.conversations.find(x => parseInt(x.id) === active);
        openConversation(active, c || { title: 'Cuộc trò chuyện', initial: '?' }, ctx);
      }
    } catch (e) {
      if (ctx.status) ctx.status.textContent = 'Không tải được chat.';
    }

    ctx.search?.addEventListener('input', e => {
      state.search = e.target.value || '';
      renderList(ctx);
    });

    ctx.tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        ctx.tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        state.tab = tab.dataset.chatTab || tab.dataset.messengerTab || 'conversations';
        renderList(ctx);
      });
    });

    ctx.form?.addEventListener('submit', e => {
      e.preventDefault();
      sendMessage(ctx);
    });

    ctx.input?.addEventListener('input', () => autoResize(ctx.input));
    ctx.input?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(ctx);
      }
    });

    setInterval(() => poll(ctx), 2500);
  }

  function initWidget() {
    const box = qs('#chat-float-box');
    const btn = qs('#chat-float-btn');
    if (!box || !btn) return;

    const ctx = {
      mode: 'widget',
      root: box,
      list: qs('#chat-list'),
      status: qs('#chatStatus'),
      body: qs('#chat-float-body'),
      input: qs('#chat-float-input'),
      send: qs('#chat-float-send'),
      form: qs('#chat-float-form'),
      title: qs('#chat-right-title'),
      sub: qs('#chatCurrentSub'),
      avatar: qs('#chatCurrentAvatar'),
      search: qs('#chatSearch'),
      tabs: qsa('[data-chat-tab]'),
      itemClass: 'ego-chat-item',
      sectionClass: 'ego-chat-section',
      avatarClass: 'ego-chat-avatar',
      metaClass: 'ego-chat-meta',
      nameClass: 'ego-chat-name',
      lastClass: 'ego-chat-last',
      timeClass: 'ego-chat-time'
    };

    btn.addEventListener('click', () => {
      box.classList.toggle('is-open');
    });

    qs('#chat-float-close')?.addEventListener('click', () => {
      box.classList.remove('is-open');
    });

    boot(ctx);
  }

  function initMessengerPage() {
    const page = qs('.ego-messenger-page');
    if (!page) return;

    const ctx = {
      mode: 'page',
      root: page,
      list: qs('#messengerList'),
      status: qs('#messengerStatus'),
      body: qs('#messengerBody'),
      input: qs('#messengerInput'),
      send: qs('#messengerSend'),
      form: qs('#messengerForm'),
      title: qs('#messengerTitle'),
      sub: qs('#messengerSub'),
      avatar: qs('#messengerCurrentAvatar'),
      search: qs('#messengerSearch'),
      tabs: qsa('[data-messenger-tab]'),
      itemClass: 'ego-list-item',
      sectionClass: 'ego-list-section',
      avatarClass: 'ego-list-avatar',
      metaClass: 'ego-list-meta',
      nameClass: 'ego-list-name',
      lastClass: 'ego-list-last',
      timeClass: 'ego-list-time'
    };

    boot(ctx);
  }

  document.addEventListener('DOMContentLoaded', function () {
    initWidget();
    initMessengerPage();
  });
})();
