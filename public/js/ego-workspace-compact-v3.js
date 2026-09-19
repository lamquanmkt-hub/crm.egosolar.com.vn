(() => {
    'use strict';

    const data = window.EgoWorkspaceCompact || {};
    const apps = Array.isArray(data.apps) ? data.apps : [];
    const featuredIds = Array.isArray(data.featuredIds) ? data.featuredIds.map(String) : [];
    const activeWorkspace = String(data.activeWorkspace || 'general');

    const searchInput = document.getElementById('workspaceSearch');
    const favoriteFilter = document.getElementById('workspaceFavoriteFilter');
    const cards = Array.from(document.querySelectorAll('[data-app-card]'));
    const groups = Array.from(document.querySelectorAll('[data-app-group]'));
    const priorityGrid = document.getElementById('workspacePriorityGrid');
    const prioritySection = document.getElementById('workspacePrioritySection');
    const emptyState = document.getElementById('workspaceEmpty');
    const resetButton = document.getElementById('workspaceReset');
    const visibleCount = document.getElementById('workspaceVisibleCount');
    const filterState = document.getElementById('workspaceFilterState');
    const themeToggle = document.getElementById('workspaceThemeToggle');
    const themeIcon = document.querySelector('[data-theme-icon]');
    const account = document.getElementById('workspaceAccount');
    const accountToggle = document.getElementById('workspaceAccountToggle');
    const accountMenu = document.getElementById('workspaceAccountMenu');
    const notificationBadge = document.getElementById('workspaceNotificationBadge');
    const switcher = document.getElementById('workspaceSwitcher');
    const switchForm = document.getElementById('workspaceSwitchForm');
    const switchOverlay = document.getElementById('workspaceSwitchOverlay');

    const FAVORITES_KEY = 'ego_workspace_favorites_v1';
    const RECENT_KEY = `ego_workspace_recent_v2_${activeWorkspace}`;
    const THEME_KEY = 'ego_workspace_theme_v1';

    let favoritesOnly = false;

    const safeParse = (value, fallback = []) => {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : fallback;
        } catch (_) {
            return fallback;
        }
    };

    const getFavorites = () => safeParse(localStorage.getItem(FAVORITES_KEY), []).map(String);
    const setFavorites = (items) => localStorage.setItem(FAVORITES_KEY, JSON.stringify(items));
    const getRecent = () => safeParse(localStorage.getItem(RECENT_KEY), []).map(String);

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const rememberRecent = (id) => {
        if (!id) return;
        const normalizedId = String(id);
        const next = [normalizedId, ...getRecent().filter((item) => item !== normalizedId)].slice(0, 10);
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    };

    const appMap = new Map(apps.map((app) => [String(app.id), app]));

    const syncFavoriteButtons = () => {
        const favorites = new Set(getFavorites());

        cards.forEach((card) => {
            const id = String(card.dataset.appId || '');
            const button = card.querySelector('[data-favorite-button]');
            if (!button) return;

            const isFavorite = favorites.has(id);
            button.classList.toggle('is-favorite', isFavorite);
            button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
            button.setAttribute('title', isFavorite ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích');

            const icon = button.querySelector('i');
            if (icon) icon.className = `bi ${isFavorite ? 'bi-star-fill' : 'bi-star'}`;
        });

        if (favoriteFilter) {
            favoriteFilter.classList.toggle('is-active', favoritesOnly);
            favoriteFilter.setAttribute('aria-pressed', favoritesOnly ? 'true' : 'false');
            const icon = favoriteFilter.querySelector('i');
            if (icon) icon.className = `bi ${favoritesOnly ? 'bi-star-fill' : 'bi-star'}`;
        }
    };

    const renderPriority = () => {
        if (!priorityGrid) return;

        const favorites = getFavorites();
        const recent = getRecent();
        const selected = [];
        const seen = new Set();

        [...recent, ...favorites, ...featuredIds, ...apps.map((app) => String(app.id))].forEach((id) => {
            const app = appMap.get(String(id));
            if (!app || seen.has(String(id)) || selected.length >= 6) return;
            seen.add(String(id));
            selected.push(app);
        });

        priorityGrid.innerHTML = selected.map((app) => {
            const badge = Number(app.badge || 0) > 0
                ? `<span class="ws3-badge">${Number(app.badge) > 99 ? '99+' : Number(app.badge)}</span>`
                : '';

            return `
                <a href="${escapeHtml(app.url)}" class="ws3-priority-card" data-app-link data-priority-app-id="${escapeHtml(app.id)}">
                    <span class="ws3-app-icon tone-${escapeHtml(app.tone)}">
                        <i class="bi ${escapeHtml(app.icon)}"></i>
                    </span>
                    <span class="ws3-priority-card__copy">
                        <strong>${escapeHtml(app.name)}</strong>
                        <small>${escapeHtml(app.description)}</small>
                    </span>
                    ${badge}
                    <i class="bi bi-arrow-up-right ws3-open-icon"></i>
                </a>
            `;
        }).join('');

        if (prioritySection) prioritySection.hidden = selected.length === 0;
        bindRecentLinks(priorityGrid);
    };

    const applyFilters = () => {
        const query = normalize(searchInput?.value || '');
        const favorites = new Set(getFavorites());
        let count = 0;

        cards.forEach((card) => {
            const id = String(card.dataset.appId || '');
            const searchable = normalize(card.dataset.search || card.textContent || '');
            const favoriteMatch = !favoritesOnly || favorites.has(id);
            const searchMatch = query === '' || searchable.includes(query);
            const visible = favoriteMatch && searchMatch;

            card.hidden = !visible;
            if (visible) count += 1;
        });

        groups.forEach((group) => {
            const visibleCards = Array.from(group.querySelectorAll('[data-app-card]'))
                .some((card) => !card.hidden);
            group.hidden = !visibleCards;
        });

        if (visibleCount) visibleCount.textContent = String(count);
        if (emptyState) emptyState.hidden = count !== 0;
        if (prioritySection) prioritySection.hidden = query !== '' || favoritesOnly || apps.length === 0;

        if (filterState) {
            if (query !== '' && favoritesOnly) {
                filterState.textContent = 'Đang tìm trong ứng dụng yêu thích';
            } else if (query !== '') {
                filterState.textContent = `Kết quả cho “${searchInput.value.trim()}”`;
            } else if (favoritesOnly) {
                filterState.textContent = 'Chỉ hiện ứng dụng yêu thích';
            } else {
                filterState.textContent = 'Hiển thị theo nhóm nghiệp vụ';
            }
        }
    };

    const bindRecentLinks = (root = document) => {
        root.querySelectorAll('[data-app-link]').forEach((link) => {
            if (link.dataset.ws3Bound === '1') return;
            link.dataset.ws3Bound = '1';

            link.addEventListener('click', () => {
                const card = link.closest('[data-app-card]');
                const priority = link.closest('[data-priority-app-id]');
                const id = card?.dataset.appId || priority?.dataset.priorityAppId;
                rememberRecent(id);
            });
        });
    };

    cards.forEach((card) => {
        const button = card.querySelector('[data-favorite-button]');
        if (!button) return;

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const id = String(card.dataset.appId || '');
            const favorites = getFavorites();
            const next = favorites.includes(id)
                ? favorites.filter((item) => item !== id)
                : [id, ...favorites].slice(0, 30);

            setFavorites(next);
            syncFavoriteButtons();
            renderPriority();
            applyFilters();
        });
    });

    favoriteFilter?.addEventListener('click', () => {
        favoritesOnly = !favoritesOnly;
        syncFavoriteButtons();
        applyFilters();
    });

    searchInput?.addEventListener('input', applyFilters);

    resetButton?.addEventListener('click', () => {
        favoritesOnly = false;
        if (searchInput) searchInput.value = '';
        syncFavoriteButtons();
        applyFilters();
        searchInput?.focus();
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }

        if (event.key === 'Escape') {
            if (searchInput?.value) {
                searchInput.value = '';
                applyFilters();
            }
            if (accountMenu && !accountMenu.hidden) closeAccountMenu();
        }
    });

    const applyTheme = (theme) => {
        const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.dataset.theme = normalizedTheme;
        localStorage.setItem(THEME_KEY, normalizedTheme);
        if (themeIcon) themeIcon.className = `bi ${normalizedTheme === 'dark' ? 'bi-sun' : 'bi-moon-stars'}`;
    };

    themeToggle?.addEventListener('click', () => {
        applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
    });

    const closeAccountMenu = () => {
        if (!accountMenu || !accountToggle) return;
        accountMenu.hidden = true;
        accountToggle.setAttribute('aria-expanded', 'false');
    };

    accountToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        if (!accountMenu) return;
        accountMenu.hidden = !accountMenu.hidden;
        accountToggle.setAttribute('aria-expanded', accountMenu.hidden ? 'false' : 'true');
    });

    document.addEventListener('click', (event) => {
        if (account && !account.contains(event.target)) closeAccountMenu();
    });

    switcher?.addEventListener('change', () => {
        if (!switchForm) return;
        if (switchOverlay) switchOverlay.hidden = false;
        switcher.disabled = true;
        switchForm.submit();
    });

    const refreshNotificationCount = async () => {
        if (!notificationBadge || !data.unreadNotificationsUrl || document.hidden) return;

        try {
            const response = await fetch(data.unreadNotificationsUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const payload = await response.json();
            const count = Number(payload.count ?? payload.unread_count ?? 0);
            notificationBadge.hidden = count <= 0;
            notificationBadge.textContent = count > 99 ? '99+' : String(count);
        } catch (_) {
            // Không làm gián đoạn Workspace khi endpoint thông báo tạm lỗi.
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshNotificationCount();
    });

    const savedTheme = localStorage.getItem(THEME_KEY);
    const preferredTheme = savedTheme || (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(preferredTheme);
    syncFavoriteButtons();
    bindRecentLinks();
    renderPriority();
    applyFilters();
    refreshNotificationCount();
    window.setInterval(refreshNotificationCount, 120000);
})();
