@auth
    @php
        $egoPageAccessService = app(\App\Services\RolePermission\PageAccessService::class);
        $egoDeniedNavigationRules = $egoPageAccessService->deniedNavigationRules(auth()->user());
    @endphp

    @if(count($egoDeniedNavigationRules))
        <style>.ego-page-access-hidden{display:none!important}</style>
        <script>
            (() => {
                const rules = @json($egoDeniedNavigationRules);

                const matchesRule = (pathname, rule) => {
                    const excluded = (rule.exclude_prefixes || []).some((rawPrefix) => {
                        const prefix = '/' + String(rawPrefix || '').replace(/^\/+|\/+$/g, '');
                        return pathname === prefix || pathname.startsWith(prefix + '/');
                    });
                    if (excluded) return false;
                    if ((rule.exact || []).includes(pathname)) return true;

                    return (rule.prefixes || []).some((rawPrefix) => {
                        const prefix = '/' + String(rawPrefix || '').replace(/^\/+|\/+$/g, '');
                        return pathname === prefix || pathname.startsWith(prefix + '/');
                    });
                };

                const applyGuard = () => {
                    const sidebar = document.querySelector('#sidebar, .ego-sidebar');
                    if (!sidebar) return;

                    sidebar.querySelectorAll('a[href]').forEach((link) => {
                        const href = link.getAttribute('href') || '';
                        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

                        let pathname;
                        try {
                            pathname = new URL(link.href, window.location.origin).pathname;
                        } catch (_) {
                            return;
                        }

                        if (rules.some((rule) => matchesRule(pathname, rule))) {
                            const item = link.closest('li');
                            (item || link).classList.add('ego-page-access-hidden');
                        }
                    });

                    [...sidebar.querySelectorAll('li[data-ego-sub="true"], li.ego-item--has-sub')]
                        .reverse()
                        .forEach((parent) => {
                            const submenu = parent.querySelector(':scope > ul, :scope ul.ego-sub');
                            if (!submenu) return;

                            const visibleLeaf = [...submenu.querySelectorAll('a[href]')].some((link) => {
                                const href = link.getAttribute('href') || '';
                                const item = link.closest('li');
                                return !href.startsWith('#') && !(item || link).classList.contains('ego-page-access-hidden');
                            });

                            if (!visibleLeaf) parent.classList.add('ego-page-access-hidden');
                        });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', applyGuard, { once: true });
                } else {
                    applyGuard();
                }
            })();
        </script>
    @endif
@endauth
