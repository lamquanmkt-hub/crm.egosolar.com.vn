(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    };

    ready(() => {
        const root = document.getElementById('crmTopbar');
        if (!root) return;

        const backdrop = document.getElementById('crmTopbarBackdrop');
        const toastContainer = document.getElementById('notifyToastContainer');
        const panelToggles = Array.from(root.querySelectorAll('[data-crm-panel-toggle]'));
        const panels = Array.from(root.querySelectorAll('[data-crm-panel]'));
        const panelCloseButtons = Array.from(root.querySelectorAll('[data-crm-panel-close]'));

        const csrf = root.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
        const notificationUnreadUrl = root.dataset.notificationUnreadUrl || '';
        const notificationListUrl = root.dataset.notificationListUrl || '';
        const notificationMarkAllUrl = root.dataset.notificationMarkAllUrl || '';
        const notificationReadTemplate = root.dataset.notificationReadTemplate || '';
        const notificationIndexUrl = root.dataset.notificationIndexUrl || '#';
        const pushSubscribeUrl = root.dataset.pushSubscribeUrl || '';
        const vapidKey = root.dataset.vapidKey || '';
        const chatListUrl = root.dataset.chatListUrl || '';
        const chatBaseUrl = (root.dataset.chatBaseUrl || '/chat').replace(/\/$/, '');

        const isMobilePanelMode = () => window.matchMedia('(max-width: 991.98px)').matches;

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const closePanels = (except = null) => {
            panels.forEach((panel) => {
                if (except && panel.dataset.crmPanel === except) return;
                panel.classList.remove('is-open');
                panel.setAttribute('aria-hidden', 'true');
            });

            panelToggles.forEach((button) => {
                if (except && button.dataset.crmPanelToggle === except) return;
                button.setAttribute('aria-expanded', 'false');
            });

            const anyOpen = panels.some((panel) => panel.classList.contains('is-open'));
            backdrop?.classList.toggle('is-open', anyOpen && isMobilePanelMode());
            backdrop?.setAttribute('aria-hidden', anyOpen && isMobilePanelMode() ? 'false' : 'true');
        };

        const openPanel = (name) => {
            const panel = root.querySelector(`[data-crm-panel="${CSS.escape(name)}"]`);
            const toggle = root.querySelector(`[data-crm-panel-toggle="${CSS.escape(name)}"]`);
            if (!panel || !toggle) return;

            const alreadyOpen = panel.classList.contains('is-open');
            closePanels();
            if (alreadyOpen) return;

            panel.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            backdrop?.classList.toggle('is-open', isMobilePanelMode());
            backdrop?.setAttribute('aria-hidden', isMobilePanelMode() ? 'false' : 'true');

            const search = panel.querySelector('input[type="search"]');
            if (search) window.setTimeout(() => search.focus(), 80);

            if (name === 'notifications') refreshNotifications(true);
            if (name === 'chat') refreshChat(true);
        };

        panelToggles.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openPanel(button.dataset.crmPanelToggle || '');
            });
        });

        panelCloseButtons.forEach((button) => button.addEventListener('click', () => closePanels()));
        backdrop?.addEventListener('click', () => closePanels());

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) closePanels();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closePanels();
        });

        window.addEventListener('resize', () => {
            const anyOpen = panels.some((panel) => panel.classList.contains('is-open'));
            backdrop?.classList.toggle('is-open', anyOpen && isMobilePanelMode());
        }, { passive: true });

        root.querySelectorAll('[data-crm-open-panel]').forEach((button) => {
            button.addEventListener('click', () => openPanel(button.dataset.crmOpenPanel || ''));
        });

        const mobileSidebarButton = document.getElementById('openSidebarBtn');
        mobileSidebarButton?.addEventListener('click', () => {
            closePanels();
            if (window.EgoSidebar?.open) window.EgoSidebar.open();
        });

        const desktopSidebarButton = document.getElementById('crmSidebarToggle');
        desktopSidebarButton?.addEventListener('click', () => {
            const existingToggle = document.getElementById('toggleSidebar');
            if (existingToggle) {
                existingToggle.click();
                return;
            }

            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            const collapsed = !sidebar.classList.contains('ego-collapsed');
            sidebar.classList.toggle('ego-collapsed', collapsed);
            document.body.classList.toggle('ego-sidebar-collapsed', collapsed);
            localStorage.setItem('ego_sidebar_collapsed', collapsed ? '1' : '0');
        });

        root.querySelectorAll('[data-crm-brand-logo]').forEach((image) => {
            image.addEventListener('error', () => {
                image.hidden = true;
                image.closest('.crm-topbar__mobile-brand')?.querySelector('[data-crm-brand-fallback]')?.style.setProperty('display', 'inline-flex');
            }, { once: true });
        });

        root.querySelectorAll('[data-crm-avatar-image]').forEach((image) => {
            image.addEventListener('error', () => {
                image.hidden = true;
                image.closest('.crm-topbar__avatar')?.querySelector('[data-crm-avatar-fallback]')?.classList.remove('is-hidden');
            }, { once: true });
        });

        const fetchJson = async (url, options = {}) => {
            if (!url) throw new Error('Missing URL');
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers || {}),
                },
                ...options,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        };

        const setBadge = (element, count) => {
            if (!element) return;
            const total = Number(count || 0);
            if (total > 0) {
                element.hidden = false;
                element.textContent = total > 99 ? '99+' : String(total);
            } else {
                element.hidden = true;
                element.textContent = '0';
            }
        };

        const createToast = (title, message, link) => {
            if (!toastContainer) return;
            const toast = document.createElement('article');
            toast.className = 'crm-topbar__toast';
            toast.innerHTML = `
                <div class="crm-topbar__toast-head">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    <strong>${escapeHtml(title || 'Thông báo mới')}</strong>
                    <button type="button" aria-label="Đóng thông báo"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="crm-topbar__toast-body">
                    <div>${escapeHtml(message || '')}</div>
                    ${link && link !== '#' ? `<a href="${escapeHtml(link)}">Xem chi tiết</a>` : ''}
                </div>
            `;
            toast.querySelector('button')?.addEventListener('click', () => toast.remove());
            toastContainer.prepend(toast);
            window.setTimeout(() => toast.remove(), 6500);
        };

        let audioContext = null;
        const unlockAudio = async () => {
            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) return null;
                if (!audioContext) audioContext = new AudioContextClass();
                if (audioContext.state === 'suspended') await audioContext.resume();
                return audioContext;
            } catch (_) {
                return null;
            }
        };

        const playNotificationSound = async () => {
            const context = await unlockAudio();
            if (!context) return;
            try {
                const oscillator = context.createOscillator();
                const gain = context.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = 820;
                gain.gain.value = 0.08;
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start();
                window.setTimeout(() => oscillator.stop(), 120);
            } catch (_) {}
        };

        document.getElementById('notifyDropdownBtn')?.addEventListener('click', unlockAudio);

        const notificationBadge = document.getElementById('notifyBadge');
        const notificationList = document.getElementById('notifyList');
        const notificationTabs = Array.from(root.querySelectorAll('[data-notification-filter]'));
        const notificationReadAllButton = document.getElementById('notifyReadAllBtn');
        const pushButton = document.getElementById('pushEnableBtn');
        let notificationItems = [];
        let notificationFilter = 'all';
        let notificationsInitialized = false;
        let lastToastId = localStorage.getItem('notify_last_toast_id') || '0';
        let notificationRequestRunning = false;

        const notificationIcon = (item) => {
            if (item.source === 'hr') return 'bi-megaphone';
            if (item.source === 'task') return 'bi-clipboard-check';
            if (item.order_id) return 'bi-receipt';
            return 'bi-bell';
        };

        const renderNotifications = () => {
            if (!notificationList) return;
            const visibleItems = notificationItems.filter((item) => notificationFilter === 'all' || !Boolean(item.is_read));

            if (!visibleItems.length) {
                notificationList.innerHTML = '<div class="crm-topbar__empty-state">Không có thông báo phù hợp.</div>';
                return;
            }

            notificationList.innerHTML = visibleItems.map((item) => {
                const link = item.link || (item.order_id ? `/orders/${encodeURIComponent(item.order_id)}` : notificationIndexUrl);
                return `
                    <a href="${escapeHtml(link)}"
                       class="crm-topbar__notification-item ${item.is_read ? '' : 'is-unread'}"
                       data-notification-id="${escapeHtml(item.id)}">
                        <span class="crm-topbar__item-icon"><i class="bi ${notificationIcon(item)}"></i></span>
                        <span class="crm-topbar__item-main">
                            <span class="crm-topbar__item-title">${escapeHtml(item.title || 'Thông báo')}</span>
                            <span class="crm-topbar__item-text">${escapeHtml(item.message || '')}</span>
                            <span class="crm-topbar__item-time">${escapeHtml(item.time_text || item.created_at || '')}</span>
                        </span>
                        ${item.is_read ? '<span></span>' : '<span class="crm-topbar__unread-dot" aria-label="Chưa đọc"></span>'}
                    </a>
                `;
            }).join('');

            notificationList.querySelectorAll('[data-notification-id]').forEach((anchor) => {
                anchor.addEventListener('click', async (event) => {
                    const id = anchor.dataset.notificationId;
                    if (!id || !notificationReadTemplate) return;
                    event.preventDefault();
                    const destination = anchor.href;
                    try {
                        await fetch(notificationReadTemplate.replace('__ID__', encodeURIComponent(id)), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });
                    } catch (_) {}
                    window.location.href = destination;
                });
            });
        };

        const refreshNotifications = async (includeList = false) => {
            if (notificationRequestRunning) return;
            notificationRequestRunning = true;
            try {
                const requests = [fetchJson(notificationUnreadUrl)];
                if (includeList || !notificationsInitialized) requests.push(fetchJson(`${notificationListUrl}?limit=10`));
                const results = await Promise.all(requests);
                setBadge(notificationBadge, results[0]?.count || 0);

                if (results[1]) {
                    notificationItems = Array.isArray(results[1]?.items?.data) ? results[1].items.data : [];
                    const newest = notificationItems[0];
                    const newestId = String(newest?.id ?? '0');
                    if (notificationsInitialized && newest && newestId !== '0' && newestId !== lastToastId) {
                        await playNotificationSound();
                        createToast(newest.title, newest.message, newest.link || (newest.order_id ? `/orders/${newest.order_id}` : notificationIndexUrl));
                        lastToastId = newestId;
                        localStorage.setItem('notify_last_toast_id', newestId);
                    }
                    if (!notificationsInitialized && newestId !== '0' && lastToastId === '0') {
                        lastToastId = newestId;
                        localStorage.setItem('notify_last_toast_id', newestId);
                    }
                    renderNotifications();
                }
                notificationsInitialized = true;
            } catch (_) {
                if (notificationList && !notificationsInitialized) {
                    notificationList.innerHTML = '<div class="crm-topbar__empty-state">Không thể tải thông báo. Vui lòng thử lại.</div>';
                }
            } finally {
                notificationRequestRunning = false;
            }
        };

        notificationTabs.forEach((button) => {
            button.addEventListener('click', () => {
                notificationFilter = button.dataset.notificationFilter || 'all';
                notificationTabs.forEach((item) => item.classList.toggle('is-active', item === button));
                renderNotifications();
            });
        });

        notificationReadAllButton?.addEventListener('click', async () => {
            notificationReadAllButton.disabled = true;
            try {
                await fetch(notificationMarkAllUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                notificationItems = notificationItems.map((item) => ({ ...item, is_read: true }));
                setBadge(notificationBadge, 0);
                renderNotifications();
            } catch (_) {
                createToast('Không thể cập nhật', 'Vui lòng thử lại thao tác đánh dấu đã đọc.');
            } finally {
                notificationReadAllButton.disabled = false;
            }
        });

        const urlBase64ToUint8Array = (base64String) => {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            return Uint8Array.from([...rawData].map((character) => character.charCodeAt(0)));
        };

        pushButton?.addEventListener('click', async () => {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !vapidKey || !pushSubscribeUrl) {
                createToast('Không hỗ trợ', 'Trình duyệt hoặc hệ thống chưa hỗ trợ thông báo đẩy.');
                return;
            }

            pushButton.disabled = true;
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') throw new Error('Permission denied');
                const registration = await navigator.serviceWorker.register('/service-worker.js');
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey),
                });
                const json = subscription.toJSON();
                await fetch(pushSubscribeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        publicKey: json.keys?.p256dh,
                        authToken: json.keys?.auth,
                        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                    }),
                });
                pushButton.innerHTML = '<i class="bi bi-check2-circle" aria-hidden="true"></i><span>Đã bật</span>';
            } catch (_) {
                createToast('Chưa bật được thông báo', 'Hãy cho phép thông báo trong cài đặt trình duyệt rồi thử lại.');
            } finally {
                pushButton.disabled = false;
            }
        });

        const chatBadge = document.getElementById('chatNavbarBadge');
        const chatList = document.getElementById('chatNavbarList');
        const chatSearch = document.getElementById('chatNavbarSearch');
        const chatTabs = Array.from(root.querySelectorAll('[data-chat-filter]'));
        let chatItems = [];
        let chatFilter = 'all';
        let chatRequestRunning = false;

        const chatUrl = (id) => `${chatBaseUrl}/${encodeURIComponent(id)}`;

        const renderChat = () => {
            if (!chatList) return;
            const query = String(chatSearch?.value || '').trim().toLowerCase();
            const visibleItems = chatItems.filter((item) => {
                if (chatFilter === 'unread' && Number(item.unread || 0) <= 0) return false;
                if (chatFilter === 'group' && item.type !== 'group') return false;
                if (chatFilter === 'department' && item.type !== 'department') return false;
                if (!query) return true;
                return [item.name, item.last_message, item.type].join(' ').toLowerCase().includes(query);
            });

            if (!visibleItems.length) {
                chatList.innerHTML = '<div class="crm-topbar__empty-state">Không có cuộc trò chuyện phù hợp.</div>';
                return;
            }

            chatList.innerHTML = visibleItems.map((item) => {
                const unread = Number(item.unread || 0);
                const avatar = item.avatar_url
                    ? `<img src="${escapeHtml(item.avatar_url)}" alt="${escapeHtml(item.name || 'Avatar')}" loading="lazy">`
                    : escapeHtml(item.initials || 'CH');
                return `
                    <a href="${chatUrl(item.id)}" class="crm-topbar__message-item ${unread > 0 ? 'is-unread' : ''}">
                        <span class="crm-topbar__item-avatar">${avatar}</span>
                        <span class="crm-topbar__item-main">
                            <span class="crm-topbar__item-title">${escapeHtml(item.name || 'Cuộc trò chuyện')}</span>
                            <span class="crm-topbar__item-text">${escapeHtml(item.last_message || 'Chưa có tin nhắn')}</span>
                        </span>
                        <span class="crm-topbar__item-meta">
                            <span class="crm-topbar__item-time">${escapeHtml(item.last_message_at || '')}</span>
                            ${unread > 0 ? `<span class="crm-topbar__item-count">${unread > 99 ? '99+' : unread}</span>` : ''}
                        </span>
                    </a>
                `;
            }).join('');
        };

        const refreshChat = async (renderAfter = false) => {
            if (!chatListUrl || chatRequestRunning) return;
            chatRequestRunning = true;
            try {
                const data = await fetchJson(chatListUrl);
                chatItems = Array.isArray(data?.conversations) ? data.conversations : [];
                const unreadCount = chatItems.reduce((sum, item) => sum + Number(item.unread || 0), 0);
                setBadge(chatBadge, unreadCount);
                if (renderAfter || document.getElementById('crmChatPanel')?.classList.contains('is-open')) renderChat();
            } catch (_) {
                if (chatList && !chatItems.length) chatList.innerHTML = '<div class="crm-topbar__empty-state">Không thể tải tin nhắn.</div>';
            } finally {
                chatRequestRunning = false;
            }
        };

        chatSearch?.addEventListener('input', renderChat);
        chatTabs.forEach((button) => {
            button.addEventListener('click', () => {
                chatFilter = button.dataset.chatFilter || 'all';
                chatTabs.forEach((item) => item.classList.toggle('is-active', item === button));
                renderChat();
            });
        });

        const refreshVisibleData = () => {
            refreshNotifications(document.getElementById('crmNotificationPanel')?.classList.contains('is-open'));
            refreshChat(document.getElementById('crmChatPanel')?.classList.contains('is-open'));
        };

        refreshNotifications(true);
        refreshChat(false);

        const notificationInterval = window.setInterval(() => {
            if (!document.hidden) refreshNotifications(true);
        }, 30000);

        const chatInterval = window.setInterval(() => {
            if (!document.hidden) refreshChat(false);
        }, 15000);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshVisibleData();
        });

        window.addEventListener('beforeunload', () => {
            window.clearInterval(notificationInterval);
            window.clearInterval(chatInterval);
        }, { once: true });

        const announcementModal = document.getElementById('companyAnnouncementModal');
        if (announcementModal && window.bootstrap?.Modal) {
            const ttl = 30 * 60 * 1000;
            const storageKey = 'ego_announce_tatnien_next_show_at_v1';
            const nextShowAt = Number(localStorage.getItem(storageKey) || 0);
            if (!nextShowAt || Date.now() >= nextShowAt) {
                const modal = new window.bootstrap.Modal(announcementModal, { backdrop: 'static', keyboard: true });
                modal.show();
                const remember = () => localStorage.setItem(storageKey, String(Date.now() + ttl));
                document.getElementById('announceAcknowledgeBtn')?.addEventListener('click', () => {
                    remember();
                    modal.hide();
                }, { once: true });
                announcementModal.addEventListener('hidden.bs.modal', remember, { once: true });
            }
        }
    });
})();
