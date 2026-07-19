<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EGO SOLAR CRM')</title>

    {{-- Font modern --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Bootstrap + Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root{
            --ego:  #06b6d4;
            --ego2: #0891b2;
            --ink:  #0b1220;
            --muted: rgba(11,18,32,.68);

            --border: rgba(11,18,32,.10);
            --glass: rgba(255,255,255,.78);

            --r-xl: 28px;
            --r-lg: 22px;
            --r-md: 16px;

            --shadow-xl: 0 30px 90px rgba(11,18,32,.16);
            --shadow-lg: 0 18px 50px rgba(11,18,32,.12);

            /* ĐỔI ẢNH NỀN Ở ĐÂY */
            --bg-img: url("/assets/img/solar-factory.jpg");
        }

        *{ box-sizing: border-box; }
        html, body{ height: 100%; }
        body{
            margin: 0;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--ink);
            background: #f5f8ff;
            overflow-x: hidden;
        }

        /* ===== FULLSCREEN BACKGROUND ===== */
        body::before{
            content:"";
            position: fixed;
            inset: 0;
            z-index: -2;
            background-image: var(--bg-img);
            background-size: cover;
            background-position: center;
            transform: scale(1.06);
            filter: blur(22px);
            opacity: .28;
        }
        body::after{
            content:"";
            position: fixed;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(1200px 700px at 18% 16%, rgba(6,182,212,.22), transparent 60%),
                radial-gradient(1000px 650px at 88% 10%, rgba(8,145,178,.14), transparent 60%),
                linear-gradient(180deg, rgba(255,255,255,.78), rgba(245,248,255,.92));
        }

        /* ===== Shell ===== */
        .auth-shell{
            min-height: 100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding: 28px 16px;
        }

        /* Wrapper chính (2 cột) */
        .auth-wrap{
            width: 100%;
            max-width: 1220px; /* muốn rộng hơn: tăng hoặc bỏ max-width */
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 22px;

            border-radius: var(--r-xl);
            border: 1px solid rgba(11,18,32,.08);
            background: rgba(255,255,255,.40);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--shadow-lg);
            overflow: hidden;

            min-height: min(760px, calc(100vh - 56px)); /* nhìn “full” hơn */
        }

        /* ===== LEFT: Intro (không còn là card mờ mịt nữa) ===== */
        .auth-hero{
            position: relative;
            padding: 44px 44px;
            display:flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 26px;
            background:
                radial-gradient(900px 520px at 20% 20%, rgba(6,182,212,.10), transparent 60%),
                radial-gradient(900px 520px at 80% 10%, rgba(8,145,178,.08), transparent 62%),
                rgba(255,255,255,.16);
        }

        .hero-brand{
            display:flex;
            align-items:center;
            gap: 14px;
        }
        .hero-logo{
            width: 54px; height: 54px;
            border-radius: 20px;
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(11,18,32,.10);
            display:flex;
            align-items:center;
            justify-content:center;
            box-shadow: 0 12px 26px rgba(11,18,32,.10);
            color: var(--ego2);
            font-weight: 900;
        }
        .hero-brand .name{
            font-weight: 900;
            letter-spacing: .2px;
            line-height: 1.1;
        }
        .hero-brand .sub{
            color: var(--muted);
            font-weight: 700;
            font-size: 12px;
            margin-top: 2px;
        }

        .hero-title{
            font-size: clamp(30px, 3.2vw, 46px);
            font-weight: 900;
            letter-spacing: -0.8px;
            margin: 10px 0 10px;
        }
        .hero-desc{
            max-width: 560px;
            color: rgba(11,18,32,.74);
            font-weight: 600;
            line-height: 1.6;
        }

        .hero-list{
            margin: 18px 0 0;
            padding: 0;
            list-style:none;
            display: grid;
            gap: 12px;
            max-width: 580px;
        }
        .hero-item{
            display:flex;
            gap: 12px;
            align-items:flex-start;
            padding: 14px 14px;
            border-radius: 18px;
            background: rgba(255,255,255,.68);
            border: 1px solid rgba(11,18,32,.08);
            box-shadow: 0 10px 26px rgba(11,18,32,.06);
        }
        .hero-item i{
            width: 36px; height: 36px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius: 14px;
            background: rgba(6,182,212,.14);
            color: var(--ego2);
            border: 1px solid rgba(6,182,212,.22);
            flex: 0 0 auto;
        }
        .hero-item .t{
            font-weight: 850;
            margin:0;
            line-height: 1.25;
        }
        .hero-item .d{
            margin:2px 0 0;
            color: rgba(11,18,32,.70);
            font-weight: 600;
            font-size: 13px;
            line-height: 1.35;
        }

        .hero-foot{
            display:flex;
            align-items:center;
            justify-content: space-between;
            gap: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(11,18,32,.08);
            color: rgba(11,18,32,.62);
            font-size: 12px;
            font-weight: 700;
        }
        .pill{
            display:inline-flex;
            align-items:center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,.70);
            border: 1px solid rgba(11,18,32,.08);
        }

        /* ===== RIGHT: Login panel (card thật sự) ===== */
        .auth-panel{
            display:flex;
            flex-direction: column;
            background: rgba(255,255,255,.46);
            padding: 18px;
        }
        .panel-card{
            height: 100%;
            border-radius: var(--r-xl);
            border: 1px solid rgba(11,18,32,.10);
            background: var(--glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: var(--shadow-xl);
            overflow:hidden;
            display:flex;
            flex-direction: column;
        }

        .panel-top{
            padding: 22px 24px 16px;
            display:flex;
            align-items:center;
            justify-content: space-between;
            gap: 10px;
            border-bottom: 1px solid rgba(11,18,32,.06);
            background: rgba(255,255,255,.40);
        }

        .brand-mini{
            display:flex;
            align-items:center;
            gap: 12px;
        }
        .brand-mini .badge-logo{
            width: 44px; height: 44px;
            border-radius: 18px;
            display:flex;
            align-items:center;
            justify-content:center;
            background: rgba(6,182,212,.14);
            border: 1px solid rgba(6,182,212,.22);
            color: var(--ego2);
            font-weight: 900;
        }
        .brand-mini .t1{ font-weight: 900; line-height: 1.1; }
        .brand-mini .t2{ font-size: 12px; font-weight: 700; color: var(--muted); margin-top: 2px; }

        .secure-badge{
            display:inline-flex;
            align-items:center;
            gap: 8px;
            font-weight: 800;
            font-size: 12px;
            color: rgba(11,18,32,.68);
            background: rgba(255,255,255,.70);
            border: 1px solid rgba(11,18,32,.10);
            border-radius: 999px;
            padding: 8px 12px;
        }

        .panel-body{
            padding: 22px 24px;
            flex: 1 1 auto;
            display:flex;
            flex-direction: column;
            justify-content:center;
        }

        /* Form (scoped) */
        .panel-body .auth-title{
            font-size: 26px;
            font-weight: 900;
            letter-spacing: -0.4px;
            margin: 0 0 18px;
        }

        .panel-body .form-label{
            font-size: 12px;
            font-weight: 850;
            color: rgba(11,18,32,.72);
        }
        .panel-body .form-control,
        .panel-body .form-select{
            border-radius: 16px;
            border: 1px solid rgba(11,18,32,.12);
            padding: 12px 14px;
            font-weight: 650;
            background: rgba(255,255,255,.86);
        }
        .panel-body .form-control:focus,
        .panel-body .form-select:focus{
            box-shadow: 0 0 0 .25rem rgba(6,182,212,.18);
            border-color: rgba(6,182,212,.45);
        }

        .panel-body .form-check-input{
            width: 18px; height: 18px;
            border-radius: 6px;
            border: 1px solid rgba(11,18,32,.18);
        }
        .panel-body .form-check-input:checked{
            background-color: var(--ego2);
            border-color: var(--ego2);
        }

        .btn-ego{
            border: none;
            border-radius: 16px;
            padding: 12px 14px;
            font-weight: 900;
            letter-spacing: .2px;
            background: linear-gradient(135deg, var(--ego), var(--ego2));
            box-shadow: 0 16px 38px rgba(8,145,178,.20);
            transition: .16s ease;
            color: #fff !important;
        }
        .btn-ego:hover{
            transform: translateY(-1px);
            box-shadow: 0 20px 48px rgba(8,145,178,.26);
        }

        .link-ego{
            color: var(--ego2);
            font-weight: 800;
            text-decoration: none;
        }
        .link-ego:hover{ text-decoration: underline; }

        .panel-foot{
            padding: 16px 24px 18px;
            border-top: 1px solid rgba(11,18,32,.06);
            display:flex;
            align-items:center;
            justify-content: space-between;
            gap: 10px;
            color: rgba(11,18,32,.58);
            font-weight: 700;
            font-size: 12px;
            background: rgba(255,255,255,.40);
        }

        /* ===== Responsive ===== */
        @media (max-width: 992px){
            .auth-wrap{
                grid-template-columns: 1fr;
                max-width: 680px;
                min-height: auto;
            }
            .auth-hero{ padding: 28px 22px; }
            .auth-panel{ padding: 14px; }
        }
        @media (max-width: 420px){
            .panel-top{ padding: 18px 18px 14px; }
            .panel-body{ padding: 18px; }
            .panel-foot{ padding: 14px 18px 16px; }
        }
    </style>

    @stack('styles')
</head>

<body>
<div class="auth-shell">
    <div class="auth-wrap">

        {{-- LEFT: Intro / Hero --}}
        <section class="auth-hero">
            <div class="hero-brand">
                <div class="hero-logo">E</div>
                <div>
                    <div class="name">EGO SOLAR</div>
                    <div class="sub">CRM System</div>
                </div>
            </div>

            <div>
                @hasSection('hero')
                    @yield('hero')
                @else
                    <div class="hero-title">Quản lý khách hàng nhanh, rõ, chuẩn.</div>
                    <div class="hero-desc">
                        Tập trung vào hiệu suất: theo dõi lead, tiến độ dự án, báo cáo trực quan và quy trình chăm sóc khách hàng.
                    </div>

                    <ul class="hero-list">
                        <li class="hero-item">
                            <i class="bi bi-check2-circle"></i>
                            <div>
                                <p class="t">Quản lý lead & khách hàng</p>
                                <p class="d">Phân loại – nhắc việc – theo dõi trạng thái minh bạch.</p>
                            </div>
                        </li>
                        <li class="hero-item">
                            <i class="bi bi-bar-chart"></i>
                            <div>
                                <p class="t">Báo cáo thông minh</p>
                                <p class="d">Dashboard gọn gàng, dễ xem theo ngày/tuần/tháng.</p>
                            </div>
                        </li>
                        <li class="hero-item">
                            <i class="bi bi-lightning-charge"></i>
                            <div>
                                <p class="t">Tối ưu quy trình bán hàng</p>
                                <p class="d">Hạn chế thất thoát lead, tăng tỷ lệ chốt.</p>
                            </div>
                        </li>
                    </ul>
                @endif
            </div>

            <div class="hero-foot">
                <span class="pill"><i class="bi bi-shield-check"></i> Bảo mật & phân quyền</span>
                <span class="pill"><i class="bi bi-headset"></i> Hỗ trợ nội bộ</span>
            </div>
        </section>

        {{-- RIGHT: Login Panel --}}
        <section class="auth-panel">
            <div class="panel-card">
                <div class="panel-top">
                    <div class="brand-mini">
                        <div class="badge-logo">E</div>
                        <div>
                            <div class="t1">EGO SOLAR</div>
                            <div class="t2">CRM System</div>
                        </div>
                    </div>
                    <div class="secure-badge">
                        <i class="bi bi-shield-lock"></i> Secure
                    </div>
                </div>

                <div class="panel-body">
                    @yield('content')
                </div>

                <div class="panel-foot">
                    <span>© {{ date('Y') }} EGO SOLAR • CRM</span>
                    <span><i class="bi bi-lock"></i> HTTPS</span>
                </div>
            </div>
        </section>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')


<!-- EGO_PR_ATTACHMENT_PREVIEW_JS_START -->
<style>
    .ego-pr-attachment-actions{
        display:flex;
        align-items:center;
        gap:8px;
        margin-top:8px;
        flex-wrap:wrap;
    }

    .ego-pr-preview-btn,
    .ego-pr-download-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        padding:7px 11px;
        border-radius:10px;
        font-size:12px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        border:1px solid #bfdbfe;
        background:#eff6ff;
        color:#1d4ed8;
    }

    .ego-pr-preview-btn{
        border-color:#99f6e4;
        background:#ecfeff;
        color:#047481;
    }

    .ego-pr-preview-modal{
        position:fixed;
        inset:0;
        z-index:999999;
        display:none;
        align-items:center;
        justify-content:center;
        background:rgba(15,23,42,.62);
        backdrop-filter:blur(7px);
        padding:28px;
    }

    .ego-pr-preview-modal.show{
        display:flex;
    }

    .ego-pr-preview-box{
        width:min(1120px,96vw);
        height:min(820px,92vh);
        background:#fff;
        border-radius:18px;
        overflow:hidden;
        box-shadow:0 30px 90px rgba(15,23,42,.35);
        display:flex;
        flex-direction:column;
    }

    .ego-pr-preview-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        padding:12px 14px;
        background:linear-gradient(90deg,#0f3b78,#0891b2);
        color:#fff;
    }

    .ego-pr-preview-title{
        font-size:14px;
        font-weight:900;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .ego-pr-preview-close{
        border:1px solid rgba(255,255,255,.35);
        color:#fff;
        background:rgba(255,255,255,.12);
        border-radius:10px;
        padding:7px 10px;
        cursor:pointer;
        font-weight:900;
    }

    .ego-pr-preview-frame{
        width:100%;
        height:100%;
        border:0;
        background:#f8fafc;
        flex:1;
    }
</style>

<script>
(function(){
    function ready(fn){
        if(document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function ensureModal(){
        var modal = document.querySelector('.ego-pr-preview-modal');

        if(modal) return modal;

        modal = document.createElement('div');
        modal.className = 'ego-pr-preview-modal';
        modal.innerHTML =
            '<div class="ego-pr-preview-box">' +
                '<div class="ego-pr-preview-head">' +
                    '<div class="ego-pr-preview-title">Xem trước chứng từ</div>' +
                    '<button type="button" class="ego-pr-preview-close">Đóng</button>' +
                '</div>' +
                '<iframe class="ego-pr-preview-frame"></iframe>' +
            '</div>';

        document.body.appendChild(modal);

        modal.addEventListener('click', function(e){
            if(e.target === modal) closeModal();
        });

        modal.querySelector('.ego-pr-preview-close').addEventListener('click', closeModal);

        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape') closeModal();
        });

        return modal;
    }

    function openPreview(url, title){
        var modal = ensureModal();
        modal.querySelector('.ego-pr-preview-title').textContent = title || 'Xem trước chứng từ';
        modal.querySelector('.ego-pr-preview-frame').src = url;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(){
        var modal = document.querySelector('.ego-pr-preview-modal');

        if(!modal) return;

        modal.classList.remove('show');
        modal.querySelector('.ego-pr-preview-frame').src = 'about:blank';
        document.body.style.overflow = '';
    }

    function enhance(){
        if(!/^\/payment-requests\/\d+/.test(location.pathname)) return;

        var links = Array.prototype.slice.call(document.querySelectorAll(
            'a[href*="/payment-requests/"][href*="/attachments-thao/"][href$="/download"]'
        ));

        links.forEach(function(link){
            if(link.dataset.egoPreviewReady === '1') return;

            link.dataset.egoPreviewReady = '1';

            var downloadUrl = link.href;
            var previewUrl = downloadUrl.replace(/\/download(\?.*)?$/, '/preview$1');
            var title = (link.textContent || 'Chứng từ đính kèm').trim().replace(/\s+/g, ' ');

            link.addEventListener('click', function(e){
                if(e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

                e.preventDefault();
                openPreview(previewUrl, title);
            });

            link.style.cursor = 'pointer';

            var actions = document.createElement('div');
            actions.className = 'ego-pr-attachment-actions';

            var previewBtn = document.createElement('button');
            previewBtn.type = 'button';
            previewBtn.className = 'ego-pr-preview-btn';
            previewBtn.innerHTML = '👁 Xem trước';
            previewBtn.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                openPreview(previewUrl, title);
            });

            var downloadBtn = document.createElement('a');
            downloadBtn.className = 'ego-pr-download-btn';
            downloadBtn.href = downloadUrl;
            downloadBtn.innerHTML = '⬇ Tải xuống';
            downloadBtn.addEventListener('click', function(e){
                e.stopPropagation();
            });

            actions.appendChild(previewBtn);
            actions.appendChild(downloadBtn);

            link.insertAdjacentElement('afterend', actions);
        });
    }

    ready(function(){
        enhance();

        var obs = new MutationObserver(function(){
            enhance();
        });

        obs.observe(document.body, {childList:true, subtree:true});
    });
})();
</script>
<!-- EGO_PR_ATTACHMENT_PREVIEW_JS_END -->

</body>
</html>