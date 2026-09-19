(() => {
    'use strict';

    const config = window.EgoWorkspaceCinematic || {};
    const apps = Array.isArray(config.apps) ? config.apps : [];

    const FAVORITES_KEY = 'ego.workspace.favorites.v5';
    const RECENT_KEY = 'ego.workspace.recent.v5';
    const THEME_KEY = 'ego.workspace.cinematic.theme.v5';

    const searchInput = document.getElementById('workspaceSearch');
    const cards = Array.from(document.querySelectorAll('[data-app-card]'));
    const pages = Array.from(document.querySelectorAll('[data-app-page]'));
    const pagePrev = document.getElementById('workspacePagePrev');
    const pageNext = document.getElementById('workspacePageNext');
    const pagination = document.getElementById('workspacePagination');
    const emptyState = document.getElementById('workspaceEmpty');
    const resetButton = document.getElementById('workspaceReset');
    const visibleCount = document.getElementById('workspaceVisibleCount');
    const filterState = document.getElementById('workspaceFilterState');
    const appHint = document.getElementById('workspaceAppHint');

    const favoriteFilter = document.getElementById('workspaceFavoriteFilter');
    const recentFilter = document.getElementById('workspaceRecentFilter');
    const allFilter = document.getElementById('workspaceAllFilter');

    const departmentRail = document.getElementById('workspaceDepartmentRail');
    const railPrev = document.getElementById('workspaceRailPrev');
    const railNext = document.getElementById('workspaceRailNext');
    const workspaceForms = Array.from(document.querySelectorAll('.ws5-workspace-form'));
    const switchOverlay = document.getElementById('workspaceSwitchOverlay');

    const themeToggle = document.getElementById('workspaceThemeToggle');
    const themeIcon = document.querySelector('[data-theme-icon]');
    const notificationBadge = document.getElementById('workspaceNotificationBadge');

    const account = document.getElementById('workspaceAccount');
    const accountToggle = document.getElementById('workspaceAccountToggle');
    const accountMenu = document.getElementById('workspaceAccountMenu');

    let activePage = 0;
    let filterMode = 'all';

    const safeParse = (value, fallback) => {
        try {
            const parsed = JSON.parse(value);
            return parsed ?? fallback;
        } catch (_) {
            return fallback;
        }
    };

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const getFavorites = () => safeParse(localStorage.getItem(FAVORITES_KEY), []).map(String);
    const setFavorites = (items) => localStorage.setItem(FAVORITES_KEY, JSON.stringify(items));
    const getRecent = () => safeParse(localStorage.getItem(RECENT_KEY), []).map(String);

    const rememberRecent = (id) => {
        if (!id) return;
        const normalizedId = String(id);
        const next = [normalizedId, ...getRecent().filter((item) => item !== normalizedId)].slice(0, 20);
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    };

    const visiblePages = () => pages.filter((page) => {
        return Array.from(page.querySelectorAll('[data-app-card]')).some((card) => !card.hidden);
    });

    const updateHint = (card = null) => {
        if (!appHint) return;

        if (!card) {
            appHint.innerHTML = '<i class="bi bi-grid-3x3-gap"></i><span>Chọn một ứng dụng để bắt đầu làm việc</span>';
            return;
        }

        const id = String(card.dataset.appId || '');
        const app = apps.find((item) => String(item.id) === id);
        if (!app) return;

        const name = String(app.name || 'Ứng dụng');
        const description = String(app.description || 'Mở ứng dụng');
        appHint.innerHTML = `<i class="bi ${String(app.icon || 'bi-grid')}"></i><span><strong>${escapeHtml(name)}</strong> · ${escapeHtml(description)}</span>`;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const syncFavoriteButtons = () => {
        const favorites = new Set(getFavorites());

        cards.forEach((card) => {
            const id = String(card.dataset.appId || '');
            const button = card.querySelector('[data-favorite-button]');
            if (!button) return;

            const favorite = favorites.has(id);
            button.classList.toggle('is-favorite', favorite);
            button.setAttribute('aria-pressed', favorite ? 'true' : 'false');
            button.setAttribute('title', favorite ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích');

            const icon = button.querySelector('i');
            if (icon) icon.className = `bi ${favorite ? 'bi-star-fill' : 'bi-star'}`;
        });
    };

    const syncFilterButtons = () => {
        const map = [
            [allFilter, filterMode === 'all'],
            [favoriteFilter, filterMode === 'favorites'],
            [recentFilter, filterMode === 'recent'],
        ];

        map.forEach(([button, active]) => {
            if (!button) return;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (favoriteFilter) {
            const icon = favoriteFilter.querySelector('i');
            if (icon) icon.className = `bi ${filterMode === 'favorites' ? 'bi-star-fill' : 'bi-star'}`;
        }
    };

    const renderPagination = () => {
        if (!pagination) return;
        const availablePages = visiblePages();

        if (availablePages.length <= 1) {
            pagination.innerHTML = '';
            return;
        }

        pagination.innerHTML = availablePages.map((_, index) => {
            return `<button type="button" class="ws5-page-dot ${index === activePage ? 'is-active' : ''}" data-page-dot="${index}" aria-label="Trang ${index + 1}"></button>`;
        }).join('');

        pagination.querySelectorAll('[data-page-dot]').forEach((dot) => {
            dot.addEventListener('click', () => showPage(Number(dot.dataset.pageDot || 0)));
        });
    };

    const showPage = (index) => {
        const availablePages = visiblePages();

        if (availablePages.length === 0) {
            pages.forEach((page) => page.classList.remove('is-active'));
            activePage = 0;
            if (pagePrev) pagePrev.disabled = true;
            if (pageNext) pageNext.disabled = true;
            renderPagination();
            return;
        }

        activePage = Math.min(Math.max(0, index), availablePages.length - 1);
        pages.forEach((page) => page.classList.remove('is-active'));
        availablePages[activePage].classList.add('is-active');

        if (pagePrev) pagePrev.disabled = activePage <= 0;
        if (pageNext) pageNext.disabled = activePage >= availablePages.length - 1;
        renderPagination();
    };

    const applyFilters = () => {
        const query = normalize(searchInput?.value || '');
        const favorites = new Set(getFavorites());
        const recent = new Set(getRecent());
        let count = 0;

        cards.forEach((card) => {
            const id = String(card.dataset.appId || '');
            const searchable = normalize(card.dataset.search || card.textContent || '');
            const searchMatch = query === '' || searchable.includes(query);
            const modeMatch = filterMode === 'favorites'
                ? favorites.has(id)
                : filterMode === 'recent'
                    ? recent.has(id)
                    : true;
            const visible = searchMatch && modeMatch;

            card.hidden = !visible;
            if (visible) count += 1;
        });

        pages.forEach((page) => {
            const hasVisible = Array.from(page.querySelectorAll('[data-app-card]')).some((card) => !card.hidden);
            page.hidden = !hasVisible;
        });

        if (visibleCount) visibleCount.textContent = String(count);
        if (emptyState) emptyState.hidden = count !== 0;

        if (filterState) {
            if (query && filterMode === 'favorites') {
                filterState.textContent = `Yêu thích · “${searchInput.value.trim()}”`;
            } else if (query && filterMode === 'recent') {
                filterState.textContent = `Gần đây · “${searchInput.value.trim()}”`;
            } else if (query) {
                filterState.textContent = `Kết quả · “${searchInput.value.trim()}”`;
            } else if (filterMode === 'favorites') {
                filterState.textContent = 'Ứng dụng yêu thích';
            } else if (filterMode === 'recent') {
                filterState.textContent = 'Ứng dụng mở gần đây';
            } else {
                filterState.textContent = `Tất cả ứng dụng · ${String(config.activeWorkspaceLabel || '')}`;
            }
        }

        activePage = 0;
        showPage(0);
        syncFilterButtons();
    };

    cards.forEach((card) => {
        const favoriteButton = card.querySelector('[data-favorite-button]');
        const appLink = card.querySelector('[data-app-link]');

        favoriteButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const id = String(card.dataset.appId || '');
            const favorites = getFavorites();
            const next = favorites.includes(id)
                ? favorites.filter((item) => item !== id)
                : [id, ...favorites].slice(0, 50);

            setFavorites(next);
            syncFavoriteButtons();
            applyFilters();
        });

        appLink?.addEventListener('click', () => rememberRecent(card.dataset.appId));
        card.addEventListener('mouseenter', () => updateHint(card));
        card.addEventListener('mouseleave', () => updateHint());
        appLink?.addEventListener('focus', () => updateHint(card));
        appLink?.addEventListener('blur', () => updateHint());
    });

    allFilter?.addEventListener('click', () => {
        filterMode = 'all';
        applyFilters();
    });

    favoriteFilter?.addEventListener('click', () => {
        filterMode = filterMode === 'favorites' ? 'all' : 'favorites';
        applyFilters();
    });

    recentFilter?.addEventListener('click', () => {
        filterMode = filterMode === 'recent' ? 'all' : 'recent';
        applyFilters();
    });

    searchInput?.addEventListener('input', applyFilters);

    resetButton?.addEventListener('click', () => {
        filterMode = 'all';
        if (searchInput) searchInput.value = '';
        applyFilters();
        searchInput?.focus();
    });

    pagePrev?.addEventListener('click', () => showPage(activePage - 1));
    pageNext?.addEventListener('click', () => showPage(activePage + 1));

    const updateRailButtons = () => {
        if (!departmentRail) return;
        const max = Math.max(0, departmentRail.scrollWidth - departmentRail.clientWidth);
        if (railPrev) railPrev.disabled = departmentRail.scrollLeft <= 2;
        if (railNext) railNext.disabled = departmentRail.scrollLeft >= max - 2;
    };

    railPrev?.addEventListener('click', () => {
        departmentRail?.scrollBy({ left: -320, behavior: 'smooth' });
    });

    railNext?.addEventListener('click', () => {
        departmentRail?.scrollBy({ left: 320, behavior: 'smooth' });
    });

    departmentRail?.addEventListener('scroll', updateRailButtons, { passive: true });
    window.addEventListener('resize', updateRailButtons);

    workspaceForms.forEach((form) => {
        form.addEventListener('submit', () => {
            if (switchOverlay) switchOverlay.hidden = false;
        });
    });

    const applyTheme = (theme) => {
        const normalized = theme === 'bright' ? 'bright' : 'cinematic';
        document.documentElement.dataset.theme = normalized;
        localStorage.setItem(THEME_KEY, normalized);
        if (themeIcon) themeIcon.className = `bi ${normalized === 'bright' ? 'bi-moon-stars' : 'bi-brightness-high'}`;
    };

    themeToggle?.addEventListener('click', () => {
        applyTheme(document.documentElement.dataset.theme === 'bright' ? 'cinematic' : 'bright');
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
            closeAccountMenu();
        }
    });

    const loadNotificationCount = async () => {
        if (!notificationBadge || !config.unreadNotificationsUrl) return;

        try {
            const response = await fetch(config.unreadNotificationsUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) return;
            const payload = await response.json();
            const count = Number(payload.count ?? payload.unread_count ?? 0);

            notificationBadge.textContent = count > 99 ? '99+' : String(Math.max(0, count));
            notificationBadge.hidden = count <= 0;
        } catch (_) {
            // Notification count is optional and must not block the launcher.
        }
    };

    syncFavoriteButtons();
    applyTheme(localStorage.getItem(THEME_KEY) || 'cinematic');
    applyFilters();
    updateRailButtons();
    loadNotificationCount();
})();
