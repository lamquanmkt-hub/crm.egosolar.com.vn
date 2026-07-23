(() => {
    'use strict';

    const customerRoot =
        document.getElementById('egoCustomers');

    const profileRoot =
        document.getElementById('egoCustomerProfile');

    const root = customerRoot || profileRoot;

    if (!root || root.dataset.cxInitialized === '1') {
        return;
    }

    root.dataset.cxInitialized = '1';

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    const finePointer = window.matchMedia(
        '(hover: hover) and (pointer: fine)'
    ).matches;

    const modalElement =
        document.getElementById('customerModal');

    const modalContent =
        document.getElementById('customerModalContent');

    const escapeHtml = (value) => {
        const node = document.createElement('div');

        node.textContent = value ?? '';

        return node.innerHTML;
    };

    const getModal = () => {
        if (
            !modalElement
            || typeof bootstrap === 'undefined'
        ) {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance(
            modalElement
        );
    };

    const showFormErrors = (form, errors) => {
        let alert = form.querySelector(
            '[data-form-errors]'
        );

        if (!alert) {
            alert = document.createElement('div');
            alert.className =
                'cx-alert cx-alert--danger';

            alert.dataset.formErrors = 'true';

            const body = form.querySelector(
                '.cx-modal-form-body'
            );

            (body || form).prepend(alert);
        }

        const messages = Object.values(errors || {})
            .flat()
            .map((message) => escapeHtml(message));

        alert.innerHTML = messages.length
            ? messages.join('<br>')
            : 'Không thể lưu dữ liệu. Vui lòng kiểm tra lại.';
    };

    const bindCustomerTabs = (form) => {
        const tabs = [
            ...form.querySelectorAll(
                '[data-customer-tab]'
            ),
        ];

        const panes = [
            ...form.querySelectorAll(
                '[data-customer-pane]'
            ),
        ];

        const previousButton = form.querySelector(
            '[data-customer-prev]'
        );

        const nextButton = form.querySelector(
            '[data-customer-next]'
        );

        const submitButton = form.querySelector(
            '[data-customer-submit]'
        );

        const stepText = form.querySelector(
            '[data-customer-step-text]'
        );

        const progress = [
            ...form.querySelectorAll(
                '.cx-form-progress i'
            ),
        ];

        const labels = [
            'Thông tin',
            'Phân loại',
            'Liên hệ',
            'Hóa đơn',
        ];

        let current = 0;

        const requiredFieldsValid = (pane) => {
            const fields = [
                ...pane.querySelectorAll(
                    '[required]'
                ),
            ];

            for (const field of fields) {
                if (!field.checkValidity()) {
                    field.reportValidity();
                    field.focus();

                    return false;
                }
            }

            return true;
        };

        const show = (index) => {
            current = Math.max(
                0,
                Math.min(index, tabs.length - 1)
            );

            tabs.forEach((tab, tabIndex) => {
                tab.classList.toggle(
                    'is-active',
                    tabIndex === current
                );
            });

            panes.forEach((pane, paneIndex) => {
                pane.classList.toggle(
                    'is-active',
                    paneIndex === current
                );
            });

            progress.forEach((item, itemIndex) => {
                item.classList.toggle(
                    'is-active',
                    itemIndex <= current
                );
            });

            if (stepText) {
                stepText.textContent =
                    `Bước ${current + 1}/${tabs.length} · ${labels[current]}`;
            }

            if (previousButton) {
                previousButton.hidden =
                    current === 0;
            }

            if (nextButton) {
                nextButton.hidden =
                    current === tabs.length - 1;
            }

            if (submitButton) {
                submitButton.hidden =
                    current !== tabs.length - 1;
            }

            form.querySelector(
                '.cx-modal-form-body'
            )?.scrollTo({
                top: 0,
                behavior: reduceMotion
                    ? 'auto'
                    : 'smooth',
            });
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => {
                show(index);
            });
        });

        previousButton?.addEventListener(
            'click',
            () => show(current - 1)
        );

        nextButton?.addEventListener(
            'click',
            () => {
                const currentPane = panes[current];

                if (
                    currentPane
                    && !requiredFieldsValid(currentPane)
                ) {
                    return;
                }

                show(current + 1);
            }
        );

        show(0);
    };

    const bindDuplicateCheck = (form) => {
        const endpoint = root.dataset.duplicateUrl;

        if (!endpoint) {
            return;
        }

        const phone = form.querySelector(
            '[name="phone"]'
        );

        const email = form.querySelector(
            '[name="email"]'
        );

        const taxCode = form.querySelector(
            '[name="billing_tax_code"]'
        );

        const excludeId = form.querySelector(
            '[name="customer_id"]'
        );

        const alert = form.querySelector(
            '[data-duplicate-alert]'
        );

        if (!alert) {
            return;
        }

        let timer = null;
        let controller = null;

        const runCheck = () => {
            window.clearTimeout(timer);

            timer = window.setTimeout(async () => {
                const params = new URLSearchParams();

                if (phone?.value.trim()) {
                    params.set(
                        'phone',
                        phone.value.trim()
                    );
                }

                if (email?.value.trim()) {
                    params.set(
                        'email',
                        email.value.trim()
                    );
                }

                if (taxCode?.value.trim()) {
                    params.set(
                        'tax_code',
                        taxCode.value.trim()
                    );
                }

                if (excludeId?.value) {
                    params.set(
                        'exclude_id',
                        excludeId.value
                    );
                }

                if (
                    ![...params.keys()].some(
                        (key) => key !== 'exclude_id'
                    )
                ) {
                    alert.classList.remove(
                        'is-visible'
                    );

                    alert.innerHTML = '';

                    return;
                }

                controller?.abort();
                controller = new AbortController();

                try {
                    const response = await fetch(
                        `${endpoint}?${params.toString()}`,
                        {
                            headers: {
                                Accept:
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            signal: controller.signal,
                        }
                    );

                    if (!response.ok) {
                        return;
                    }

                    const data =
                        await response.json();

                    const matches =
                        data.matches || [];

                    if (!matches.length) {
                        alert.classList.remove(
                            'is-visible'
                        );

                        alert.innerHTML = '';

                        return;
                    }

                    alert.innerHTML = `
                        <strong>
                            <i class="bi bi-exclamation-triangle"></i>
                            Phát hiện ${matches.length}
                            hồ sơ có thể bị trùng
                        </strong>

                        ${matches.map((item) => `
                            <div style="margin-top:6px">
                                <a
                                    href="${escapeHtml(item.url)}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    ${escapeHtml(item.name)}
                                </a>

                                · ${escapeHtml(
                                    item.phone
                                    || item.email
                                    || item.tax_code
                                    || ''
                                )}

                                ${item.owner
                                    ? ` · Phụ trách: ${escapeHtml(item.owner)}`
                                    : ''
                                }
                            </div>
                        `).join('')}
                    `;

                    alert.classList.add(
                        'is-visible'
                    );
                } catch (error) {
                    if (
                        error.name !== 'AbortError'
                    ) {
                        console.warn(
                            'Duplicate check failed',
                            error
                        );
                    }
                }
            }, 430);
        };

        [phone, email, taxCode]
            .filter(Boolean)
            .forEach((input) => {
                input.addEventListener(
                    'input',
                    runCheck
                );

                input.addEventListener(
                    'change',
                    runCheck
                );
            });
    };

    const bindCustomerForm = (form) => {
        if (!form || form.dataset.bound === '1') {
            return;
        }

        form.dataset.bound = '1';

        bindCustomerTabs(form);
        bindDuplicateCheck(form);

        form.addEventListener(
            'submit',
            async (event) => {
                event.preventDefault();

                if (!form.checkValidity()) {
                    const invalid =
                        form.querySelector(':invalid');

                    const pane =
                        invalid?.closest(
                            '[data-customer-pane]'
                        );

                    if (pane) {
                        const paneName =
                            pane.dataset.customerPane;

                        form.querySelector(
                            `[data-customer-tab="${paneName}"]`
                        )?.click();
                    }

                    invalid?.reportValidity();
                    invalid?.focus();

                    return;
                }

                const submitButton =
                    form.querySelector(
                        '[data-customer-submit]'
                    );

                const oldContent =
                    submitButton?.innerHTML;

                if (submitButton) {
                    submitButton.disabled = true;

                    submitButton.innerHTML = `
                        <span
                            class="spinner-border spinner-border-sm"
                            aria-hidden="true"
                        ></span>
                        Đang lưu...
                    `;
                }

                try {
                    const response = await fetch(
                        form.action,
                        {
                            method: 'POST',
                            body: new FormData(form),

                            headers: {
                                Accept:
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },
                        }
                    );

                    const data =
                        await response.json()
                            .catch(() => ({}));

                    if (response.status === 422) {
                        showFormErrors(
                            form,
                            data.errors || {}
                        );

                        return;
                    }

                    if (!response.ok) {
                        throw new Error(
                            data.message
                            || 'Không thể lưu khách hàng.'
                        );
                    }

                    window.location.reload();
                } catch (error) {
                    showFormErrors(form, {
                        general: [
                            error.message
                            || 'Có lỗi khi lưu khách hàng.',
                        ],
                    });
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;

                        submitButton.innerHTML =
                            oldContent;
                    }
                }
            }
        );
    };

    window.openCustomerForm = async (url) => {
        const modal = getModal();

        if (!modal || !modalContent) {
            window.location.href = url;
            return;
        }

        modalContent.innerHTML = `
            <div class="cx-modal-loading">
                <div
                    class="spinner-border text-info"
                    role="status"
                ></div>

                <strong>
                    Đang chuẩn bị biểu mẫu...
                </strong>
            </div>
        `;

        modal.show();

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With':
                        'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(
                    'Không thể tải biểu mẫu.'
                );
            }

            modalContent.innerHTML =
                await response.text();

            bindCustomerForm(
                modalContent.querySelector(
                    '[data-customer-form]'
                )
            );
        } catch (error) {
            modalContent.innerHTML = `
                <div class="p-4">
                    <div
                        class="cx-alert cx-alert--danger"
                    >
                        ${escapeHtml(error.message)}
                    </div>
                </div>
            `;
        }
    };

    /*
     * EGO_CUSTOMER_HANDOVER_JS_V32
     */
    const bindHandoverDrawer = () => {
        const drawer =
            document.getElementById(
                'customerHandoverDrawer'
            );

        const overlay =
            document.querySelector(
                '[data-handover-drawer-overlay]'
            );

        const form =
            document.querySelector(
                '[data-handover-form]'
            );

        if (!drawer || !overlay) {
            return;
        }

        const open = () => {
            drawer.classList.add('is-open');
            overlay.classList.add('is-open');

            drawer.setAttribute(
                'aria-hidden',
                'false'
            );

            document.body.classList.add(
                'cx-handover-open'
            );

            window.setTimeout(() => {
                drawer
                    .querySelector(
                        '[name="owner_id"]'
                    )
                    ?.focus();
            }, 390);
        };

        const close = () => {
            drawer.classList.remove('is-open');
            overlay.classList.remove('is-open');

            drawer.setAttribute(
                'aria-hidden',
                'true'
            );

            document.body.classList.remove(
                'cx-handover-open'
            );
        };

        document
            .querySelectorAll(
                '[data-open-handover-drawer]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    open
                );
            });

        document
            .querySelectorAll(
                '[data-close-handover-drawer]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    close
                );
            });

        overlay.addEventListener('click', close);

        document.addEventListener(
            'keydown',
            (event) => {
                if (
                    event.key === 'Escape'
                    && drawer.classList.contains(
                        'is-open'
                    )
                ) {
                    close();
                }
            }
        );

        form?.addEventListener(
            'submit',
            (event) => {
                const select = form.querySelector(
                    '[name="owner_id"]'
                );

                if (
                    !select
                    || !select.value
                ) {
                    event.preventDefault();

                    select?.reportValidity();
                    select?.focus();

                    return;
                }

                const selectedName =
                    select.options[
                        select.selectedIndex
                    ]?.textContent?.trim()
                    || 'nhân viên đã chọn';

                const confirmed =
                    window.confirm(
                        `Xác nhận bàn giao khách hàng cho ${selectedName}?`
                    );

                if (!confirmed) {
                    event.preventDefault();
                    return;
                }

                const submitButton =
                    form.querySelector(
                        '[type="submit"]'
                    );

                if (submitButton) {
                    submitButton.disabled = true;

                    submitButton.innerHTML = `
                        <span
                            class="spinner-border spinner-border-sm"
                            aria-hidden="true"
                        ></span>
                        Đang bàn giao...
                    `;
                }
            }
        );
    };

    const bindCareDrawer = () => {
        const drawer =
            document.getElementById(
                'customerCareDrawer'
            );

        const overlay =
            document.querySelector(
                '[data-care-drawer-overlay]'
            );

        if (!drawer || !overlay) {
            return;
        }

        const open = () => {
            drawer.classList.add('is-open');
            overlay.classList.add('is-open');

            drawer.setAttribute(
                'aria-hidden',
                'false'
            );

            document.body.classList.add(
                'cx-drawer-open'
            );

            window.setTimeout(() => {
                drawer.querySelector(
                    '[name="interaction_type"]'
                )?.focus();
            }, 380);
        };

        const close = () => {
            drawer.classList.remove('is-open');
            overlay.classList.remove('is-open');

            drawer.setAttribute(
                'aria-hidden',
                'true'
            );

            document.body.classList.remove(
                'cx-drawer-open'
            );
        };

        document
            .querySelectorAll(
                '[data-open-care-drawer]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    open
                );
            });

        document
            .querySelectorAll(
                '[data-close-care-drawer]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    close
                );
            });

        overlay.addEventListener('click', close);

        document.addEventListener(
            'keydown',
            (event) => {
                if (
                    event.key === 'Escape'
                    && drawer.classList.contains(
                        'is-open'
                    )
                ) {
                    close();
                }
            }
        );
    };

    const revealElements = () => {
        const elements = [
            ...root.querySelectorAll(
                '.cx-reveal'
            ),
        ];

        if (
            reduceMotion
            || !('IntersectionObserver' in window)
        ) {
            elements.forEach((element) => {
                element.classList.add(
                    'is-visible'
                );
            });

            return;
        }

        const observer =
            new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add(
                            'is-visible'
                        );

                        observer.unobserve(
                            entry.target
                        );
                    });
                },
                {
                    threshold: .07,
                    rootMargin:
                        '0px 0px -25px',
                }
            );

        elements.forEach((element, index) => {
            element.style.transitionDelay =
                `${Math.min(index * 34, 240)}ms`;

            observer.observe(element);
        });
    };

    const animateCounters = () => {
        if (reduceMotion) {
            return;
        }

        root
            .querySelectorAll(
                '[data-count-value]'
            )
            .forEach((element) => {
                const target = Number(
                    element.dataset.countValue
                );

                if (
                    !Number.isFinite(target)
                    || target <= 0
                ) {
                    return;
                }

                const started = performance.now();
                const duration = 700;

                const frame = (now) => {
                    const progress = Math.min(
                        1,
                        (now - started) / duration
                    );

                    const eased =
                        1 - Math.pow(
                            1 - progress,
                            3
                        );

                    element.textContent =
                        new Intl.NumberFormat(
                            'vi-VN'
                        ).format(
                            Math.round(
                                target * eased
                            )
                        );

                    if (progress < 1) {
                        requestAnimationFrame(frame);
                    }
                };

                requestAnimationFrame(frame);
            });
    };

    const bindSpotlight = () => {
        if (!finePointer || reduceMotion) {
            return;
        }

        root.addEventListener(
            'pointermove',
            (event) => {
                root.style.setProperty(
                    '--cx-pointer-x',
                    `${event.clientX}px`
                );

                root.style.setProperty(
                    '--cx-pointer-y',
                    `${event.clientY}px`
                );
            },
            {
                passive: true,
            }
        );
    };

    const bindRipples = () => {
        document.addEventListener(
            'click',
            (event) => {
                const button =
                    event.target.closest(
                        '.cx-btn, .cx-icon-btn, .cx-form-tab'
                    );

                if (!button || reduceMotion) {
                    return;
                }

                const style =
                    getComputedStyle(button);

                if (style.position === 'static') {
                    button.style.position =
                        'relative';
                }

                button.style.overflow = 'hidden';

                const rect =
                    button.getBoundingClientRect();

                const ripple =
                    document.createElement('span');

                ripple.className = 'cx-ripple';

                ripple.style.left =
                    `${event.clientX - rect.left}px`;

                ripple.style.top =
                    `${event.clientY - rect.top}px`;

                ripple.style.width =
                    ripple.style.height =
                        `${Math.max(
                            rect.width,
                            rect.height
                        )}px`;

                button.appendChild(ripple);

                window.setTimeout(
                    () => ripple.remove(),
                    680
                );
            }
        );
    };

    document.addEventListener(
        'click',
        (event) => {
            const editButton =
                event.target.closest(
                    '[data-customer-form-url]'
                );

            if (editButton) {
                event.preventDefault();

                window.openCustomerForm(
                    editButton.dataset
                        .customerFormUrl
                );

                return;
            }

            const confirmButton =
                event.target.closest(
                    '[data-confirm]'
                );

            if (
                confirmButton
                && !window.confirm(
                    confirmButton.dataset.confirm
                )
            ) {
                event.preventDefault();
            }
        }
    );

    bindHandoverDrawer();
    bindCareDrawer();
    revealElements();
    animateCounters();
    bindSpotlight();
    bindRipples();
})();
