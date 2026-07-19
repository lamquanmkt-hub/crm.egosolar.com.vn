document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    const content = document.querySelector('.main-content');

    // =========================
    // Toggle sidebar (giữ nguyên)
    // =========================
    if (toggleBtn && sidebar && content) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            if (sidebar.classList.contains('collapsed')) {
                content.style.marginLeft = '70px';
            } else {
                content.style.marginLeft = '240px';
            }
            window.dispatchEvent(new Event('resize'));
        });
    }

    // ==========================================
    // 1) AUTO WRAP tables (để mọi trang đều có wrapper cuộn ngang)
    // - Nếu table chưa nằm trong .table-responsive thì bọc lại
    // ==========================================
    function autoWrapTables() {
        const tables = Array.from(document.querySelectorAll('table.table'));
        tables.forEach((tbl) => {
            // bỏ qua nếu đã có table-responsive gần đó
            if (tbl.closest('.table-responsive')) return;

            // tạo wrapper
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive ego-auto-wrap';
            wrap.style.overflowX = 'auto';
            wrap.style.webkitOverflowScrolling = 'touch';

            // bọc table
            tbl.parentNode.insertBefore(wrap, tbl);
            wrap.appendChild(tbl);
        });
    }
    autoWrapTables();

    // ==========================================
    // 2) GLOBAL Sticky Horizontal Scrollbar
    // ==========================================
    const sticky = document.getElementById('egoStickyScrollbar');
    const inner = document.getElementById('egoStickyScrollbarInner');
    if (!sticky || !inner) return;

    const getWrappers = () =>
        Array.from(document.querySelectorAll('.table-responsive, .ego-table-wrap, .ego-auto-wrap'));

    let activeWrap = null;
    let lockFromSticky = false;
    let lockFromWrap = false;

    function isVisible(el) {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        // wrapper phải nằm trong viewport một phần
        return rect.bottom > 140 && rect.top < (window.innerHeight - 120);
    }

    function needsHorizontalScroll(wrap) {
    if (!wrap) return false;

    const tbl = wrap.querySelector('table');
    const colCount = tbl ? (tbl.querySelectorAll('thead th').length || 0) : 0;

    // ✅ Điều kiện bật sticky:
    // - Có overflow ngang thật
    // - HOẶC table có nhiều cột (>=8) để luôn có thanh kéo
    // - HOẶC wrapper có class force-sticky-scroll
    const hasOverflow = (wrap.scrollWidth - wrap.clientWidth) > 5;
    const forceByCols = colCount >= 8;
    const forceByClass = wrap.classList.contains('force-sticky-scroll');

    return hasOverflow || forceByCols || forceByClass;
}

    function showSticky(on) {
        sticky.style.display = on ? 'block' : 'none';
        document.body.classList.toggle('has-sticky-scrollbar', !!on);
    }

    function pickActiveWrapper() {
        const wrappers = getWrappers();

        // chỉ lấy wrapper có table bên trong (tránh wrapper rỗng)
        const candidates = wrappers.filter(w => w.querySelector('table'));

        // ưu tiên cái đang visible và cần scroll ngang
        const found = candidates.find(w => isVisible(w) && needsHorizontalScroll(w));
        activeWrap = found || null;
        return activeWrap;
    }

    function updateSticky() {
        autoWrapTables(); // phòng khi trang load ajax/partial

        const wrap = pickActiveWrapper();
        if (!wrap) {
            showSticky(false);
            return;
        }

        if (!needsHorizontalScroll(wrap)) {
            showSticky(false);
            return;
        }

        // set width "ảo"
        inner.style.width = wrap.scrollWidth + 'px';

        // sync vị trí
        if (!lockFromWrap) {
            sticky.scrollLeft = wrap.scrollLeft;
        }

        showSticky(true);
    }

    // Sync sticky -> wrapper
    sticky.addEventListener('scroll', () => {
        if (!activeWrap) return;
        if (lockFromWrap) return;

        lockFromSticky = true;
        activeWrap.scrollLeft = sticky.scrollLeft;
        lockFromSticky = false;
    });

    // Bind scroll cho wrappers (chỉ bind 1 lần)
    function bindWraps() {
        getWrappers().forEach(wrap => {
            if (wrap.__egoBound) return;
            wrap.__egoBound = true;

            wrap.addEventListener('scroll', () => {
                if (!isVisible(wrap)) return;
                if (!needsHorizontalScroll(wrap)) return;

                activeWrap = wrap;
                inner.style.width = wrap.scrollWidth + 'px';

                if (lockFromSticky) return;

                lockFromWrap = true;
                sticky.scrollLeft = wrap.scrollLeft;
                lockFromWrap = false;
            });
        });
    }

    // events
    window.addEventListener('scroll', () => {
        bindWraps();
        updateSticky();
    }, { passive: true });

    window.addEventListener('resize', () => {
        bindWraps();
        updateSticky();
    });

    // init
    bindWraps();
    updateSticky();
});
