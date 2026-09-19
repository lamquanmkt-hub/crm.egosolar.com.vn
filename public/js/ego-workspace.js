(() => {
    'use strict';

    const root = document.documentElement;
    const cards = Array.from(document.querySelectorAll('[data-app-card]'));
    const categoryButtons = Array.from(document.querySelectorAll('[data-category]'));
    const searchInput = document.getElementById('workspaceSearch');
    const emptyState = document.getElementById('workspaceEmpty');
    const resetButton = document.getElementById('workspaceReset');
    const visibleCount = document.getElementById('workspaceVisibleCount');
    const quickGrid = document.getElementById('workspaceQuickGrid');
    const themeToggle = document.getElementById('workspaceThemeToggle');
    const themeIcon = document.querySelector('[data-theme-icon]');
    const account = document.getElementById('workspaceAccount');
    const accountToggle = document.getElementById('workspaceAccountToggle');
    const accountMenu = document.getElementById('workspaceAccountMenu');
    const notificationBadge = document.getElementById('workspaceNotificationBadge');
    const workspaceRoot = document.getElementById('egoWorkspace');

    const apps = Array.isArray(window.EgoWorkspaceApps) ? window.EgoWorkspaceApps : [];
    const featuredIds = Array.isArray(window.EgoWorkspaceFeatured) ? window.EgoWorkspaceFeatured.map(String) : [];
    const appMap = new Map(apps.map((app) => [String(app.id), app]));
    const FAVORITES_KEY = 'ego_workspace_favorites_v1';
    const RECENT_KEY = 'ego_workspace_recent_v1';
    const THEME_KEY = 'ego_workspace_theme_v1';

    const requestedDefaultCategory = String(workspaceRoot?.dataset.defaultCategory || 'all');
    const availableCategories = new Set(categoryButtons.map((button) => String(button.dataset.category || '')));
    let activeCategory = availableCategories.has(requestedDefaultCategory) ? requestedDefaultCategory : 'all';

    const safeParse = (value, fallback = []) => {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : fallback;
        } catch (_) {
            return fallback;
        }
    };

    const getFavorites = () => safeParse(localStorage.getItem(FAVORITES_KEY), []);
    const setFavorites = (items) => localStorage.setItem(FAVORITES_KEY, JSON.stringify(items));
    const getRecent = () => safeParse(localStorage.getItem(RECENT_KEY), []);

    const rememberRecent = (id) => {
        if (!id) return;
        const next = [String(id), ...getRecent().filter((item) => String(item) !== String(id))].slice(0, 8);
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    };

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const syncFavoriteButtons = () => {
        const favorites = new Set(getFavorites().map(String));
        cards.forEach((card) => {
            const button = card.querySelector('[data-favorite-button]');
            if (!button) return;
            const isFavorite = favorites.has(String(card.dataset.appId));
            button.classList.toggle('is-favorite', isFavorite);
            button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
            button.setAttribute('title', isFavorite ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích');
            const icon = button.querySelector('i');
            if (icon) icon.className = `bi ${isFavorite ? 'bi-star-fill' : 'bi-star'}`;
        });
    };

    const renderQuickAccess = () => {
        if (!quickGrid || apps.length === 0) return;

        const recentIds = getRecent();
        const selected = [];
        const seen = new Set();

        [...recentIds, ...featuredIds, ...apps.map((app) => String(app.id))].forEach((id) => {
            const app = appMap.get(String(id));
            if (!app || seen.has(String(id)) || selected.length >= 3) return;
            seen.add(String(id));
            selected.push(app);
        });

        quickGrid.innerHTML = selected.map((app) => `
            <a href="${escapeAttribute(app.url)}" class="workspace-quick-card" data-quick-app-id="${escapeAttribute(app.id)}" data-app-link>
                <span class="workspace-app-icon tone-${escapeAttribute(app.tone)} workspace-app-icon--small">
                    <i class="bi ${escapeAttribute(app.icon)}"></i>
                </span>
                <span>
                    <strong>${escapeHtml(app.name)}</strong>
                    <small>Mở phân hệ</small>
                </span>
                <i class="bi bi-arrow-up-right"></i>
            </a>
        `).join('');

        bindRecentLinks(quickGrid);
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value);
    }

    const applyFilters = () => {
        const query = normalize(searchInput?.value);
        const favorites = new Set(getFavorites().map(String));
        let count = 0;

        cards.forEach((card) => {
            const category = String(card.dataset.category || '');
            const searchable = normalize(card.dataset.search || '');
            const id = String(card.dataset.appId || '');

            const categoryMatch = activeCategory === 'all'
                || (activeCategory === 'favorites' ? favorites.has(id) : category === activeCategory);
            const searchMatch = query === '' || searchable.includes(query);
            const visible = categoryMatch && searchMatch;

            card.hidden = !visible;
            if (visible) count += 1;
        });

        if (emptyState) emptyState.hidden = count !== 0;
        if (visibleCount) visibleCount.textContent = `${count} ứng dụng`;
    };

    const setActiveCategory = (key) => {
        activeCategory = String(key || 'all');
        categoryButtons.forEach((button) => {
            button.classList.toggle('is-active', String(button.dataset.category) === activeCategory);
        });
        applyFilters();
    };

    categoryButtons.forEach((button) => {
        button.addEventListener('click', () => setActiveCategory(button.dataset.category));
    });

    cards.forEach((card) => {
        const favoriteButton = card.querySelector('[data-favorite-button]');
        favoriteButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const id = String(card.dataset.appId || '');
            const favorites = getFavorites().map(String);
            const next = favorites.includes(id)
                ? favorites.filter((item) => item !== id)
                : [id, ...favorites];

            setFavorites(next);
            syncFavoriteButtons();
            if (activeCategory === 'favorites') applyFilters();
        });
    });

    const bindRecentLinks = (scope = document) => {
        scope.querySelectorAll('[data-app-link]').forEach((link) => {
            if (link.dataset.recentBound === '1') return;
            link.dataset.recentBound = '1';
            link.addEventListener('click', () => {
                const card = link.closest('[data-app-card]');
                const id = card?.dataset.appId || link.dataset.quickAppId;
                rememberRecent(id);
            });
        });
    };

    searchInput?.addEventListener('input', applyFilters);
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            searchInput.value = '';
            searchInput.blur();
            applyFilters();
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
    });

    resetButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        setActiveCategory('all');
        searchInput?.focus();
    });

    const updateThemeIcon = () => {
        if (!themeIcon) return;
        themeIcon.className = root.dataset.theme === 'dark'
            ? 'bi bi-sun'
            : 'bi bi-moon-stars';
    };

    const initialTheme = localStorage.getItem(THEME_KEY)
        || (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    root.dataset.theme = initialTheme === 'dark' ? 'dark' : 'light';
    updateThemeIcon();

    themeToggle?.addEventListener('click', () => {
        root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem(THEME_KEY, root.dataset.theme);
        updateThemeIcon();
    });

    accountToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = accountMenu?.hidden !== false;
        if (accountMenu) accountMenu.hidden = !willOpen;
        accountToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        if (!account?.contains(event.target)) {
            if (accountMenu) accountMenu.hidden = true;
            accountToggle?.setAttribute('aria-expanded', 'false');
        }
    });

    const loadNotificationCount = async () => {
        const url = window.EgoWorkspaceUrls?.unreadNotifications;
        if (!url || !notificationBadge) return;

        try {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;
            const payload = await response.json();
            const count = Math.max(0, Number(payload.count || 0));
            notificationBadge.textContent = count > 99 ? '99+' : String(count);
            notificationBadge.hidden = count === 0;
        } catch (_) {
            notificationBadge.hidden = true;
        }
    };

    syncFavoriteButtons();
    bindRecentLinks();
    renderQuickAccess();
    setActiveCategory(activeCategory);
    loadNotificationCount();
})();
