(() => {
    'use strict';

    const loader = document.getElementById('egoPageTransition');

    if (!loader || loader.dataset.egoReady === '1') {
        return;
    }

    loader.dataset.egoReady = '1';

    const options = Object.assign({
        bootDuration: 650,
        navigationDuration: 2000,
        maximumDuration: 3500,
    }, window.EGO_TRANSITION_OPTIONS || {});

    const titleNode = loader.querySelector(
        '[data-ego-transition-title]'
    );

    const messageNode = loader.querySelector(
        '[data-ego-transition-message]'
    );

    let navigating = false;
    let safetyTimer = null;
    let messageTimer = null;

    const messages = [
        'Đang đồng bộ dữ liệu...',
        'Đang kiểm tra quyền truy cập...',
        'Đang chuẩn bị giao diện...',
        'Sắp hoàn tất...',
    ];

    let messageIndex = 0;

    const setText = (title, message) => {
        if (titleNode && title) {
            titleNode.textContent = title;
        }

        if (messageNode && message) {
            messageNode.textContent = message;
        }
    };

    const startMessages = () => {
        window.clearInterval(messageTimer);

        messageTimer = window.setInterval(() => {
            messageIndex =
                (messageIndex + 1) % messages.length;

            if (messageNode) {
                messageNode.textContent =
                    messages[messageIndex];
            }
        }, 520);
    };

    const stopMessages = () => {
        window.clearInterval(messageTimer);
        messageTimer = null;
    };

    const showLoader = (
        title = 'Đang chuyển không gian làm việc',
        message = 'Đang đồng bộ dữ liệu...'
    ) => {
        setText(title, message);

        loader.classList.remove('is-hiding');
        loader.classList.add('is-visible');

        loader.setAttribute('aria-hidden', 'false');

        document.body.classList.add(
            'ego-transition-locked'
        );

        startMessages();

        window.clearTimeout(safetyTimer);

        safetyTimer = window.setTimeout(() => {
            hideLoader();
        }, options.maximumDuration);
    };

    const hideLoader = () => {
        stopMessages();
        window.clearTimeout(safetyTimer);

        loader.classList.add('is-hiding');

        window.setTimeout(() => {
            loader.classList.remove(
                'is-visible',
                'is-hiding',
                'is-page-boot'
            );

            loader.setAttribute('aria-hidden', 'true');

            document.body.classList.remove(
                'ego-transition-locked'
            );

            navigating = false;
        }, 270);
    };

    /*
     * Hiệu ứng lúc trang vừa tải xong.
     */
    window.setTimeout(() => {
        hideLoader();
    }, options.bootDuration);

    const ignoreLink = (link, event) => {
        if (
            !link
            || event.button !== 0
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || event.metaKey
            || link.target === '_blank'
            || link.hasAttribute('download')
            || link.dataset.noTransition !== undefined
            || link.dataset.egoTransition === 'off'
        ) {
            return true;
        }

        const href = link.getAttribute('href');

        if (
            !href
            || href.startsWith('#')
            || href.startsWith('javascript:')
            || href.startsWith('mailto:')
            || href.startsWith('tel:')
        ) {
            return true;
        }

        let destination;

        try {
            destination = new URL(
                link.href,
                window.location.href
            );
        } catch {
            return true;
        }

        if (destination.origin !== window.location.origin) {
            return true;
        }

        const sameDocument =
            destination.pathname === location.pathname
            && destination.search === location.search;

        if (sameDocument && destination.hash) {
            return true;
        }

        return false;
    };

    /*
     * Capture phase để chạy trước code click của sidebar.
     */
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (
            navigating
            || ignoreLink(link, event)
        ) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        navigating = true;

        const label =
            link.dataset.transitionTitle
            || link.getAttribute('aria-label')
            || link.textContent.trim()
            || 'trang mới';

        showLoader(
            'Đang mở trang mới',
            `Đang chuẩn bị ${label}...`
        );

        window.setTimeout(() => {
            window.location.href = link.href;
        }, options.navigationDuration);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (
            !(form instanceof HTMLFormElement)
            || form.dataset.noTransition !== undefined
            || form.dataset.egoTransition === 'off'
        ) {
            return;
        }

        showLoader(
            'Đang xử lý yêu cầu',
            'Vui lòng chờ trong giây lát...'
        );
    }, true);

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            hideLoader();
        }
    });
})();
