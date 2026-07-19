<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chọn công ty</title>

    <style>
        :root {
            --bg1: #eef9ff;
            --bg2: #f8fbff;
            --primary: #0b6fae;
            --primary-dark: #075985;
            --cyan: #14b8c6;
            --text: #102033;
            --muted: #63758a;
            --border: #dbe8f5;
            --card: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 20% 20%, rgba(20, 184, 198, .16), transparent 28%),
                radial-gradient(circle at 80% 80%, rgba(14, 165, 233, .14), transparent 32%),
                linear-gradient(135deg, var(--bg1), var(--bg2));
            -webkit-font-smoothing: antialiased;
            text-rendering: geometricPrecision;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 18px;
        }

        .select-wrap {
            width: min(980px, 100%);
        }

        .panel {
            background: rgba(255, 255, 255, .92);
            border: 1px solid rgba(219, 232, 245, .95);
            border-radius: 30px;
            padding: 32px;
            box-shadow:
                0 26px 70px rgba(15, 35, 52, .13),
                inset 0 1px 0 rgba(255,255,255,.9);
            backdrop-filter: blur(14px);
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
        }

        .logo-mark {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            background: linear-gradient(135deg, #0b6fae, #14b8c6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 18px;
            box-shadow: 0 12px 28px rgba(11, 111, 174, .26);
            letter-spacing: -.02em;
        }

        h1 {
            margin: 0;
            font-size: clamp(26px, 3vw, 34px);
            line-height: 1.15;
            font-weight: 760;
            letter-spacing: -.035em;
            color: #0f263d;
        }

        .sub {
            margin-top: 7px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.55;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-top: 24px;
        }

        form {
            margin: 0;
        }

        .company-btn {
            width: 100%;
            min-height: 178px;
            border: 1px solid var(--border);
            background:
                linear-gradient(180deg, rgba(255,255,255,.98), rgba(244,251,255,.98));
            border-radius: 24px;
            padding: 24px;
            cursor: pointer;
            text-align: left;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            position: relative;
            overflow: hidden;
        }

        .company-btn::after {
            content: "";
            position: absolute;
            right: -44px;
            bottom: -44px;
            width: 130px;
            height: 130px;
            border-radius: 999px;
            background: rgba(20, 184, 198, .12);
            transition: .18s ease;
        }

        .company-btn:hover {
            transform: translateY(-3px);
            border-color: rgba(20, 184, 198, .85);
            box-shadow: 0 20px 42px rgba(15, 35, 52, .12);
        }

        .company-btn:hover::after {
            transform: scale(1.15);
            background: rgba(20, 184, 198, .18);
        }

        .company-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            position: relative;
            z-index: 1;
        }

        .company-icon {
            flex: 0 0 auto;
            width: 42px;
            height: 42px;
            border-radius: 15px;
            background: #e6f7fb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 21px;
        }

        .name {
            margin-top: 16px;
            position: relative;
            z-index: 1;
            color: var(--primary-dark);
            font-size: 19px;
            line-height: 1.28;
            font-weight: 760;
            letter-spacing: -.018em;
            text-transform: none;
        }

        .hint {
            margin-top: 12px;
            position: relative;
            z-index: 1;
            color: #52677d;
            font-size: 14px;
            line-height: 1.6;
            max-width: 92%;
        }

        .arrow {
            flex: 0 0 auto;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            background: #f1f7fb;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            position: relative;
            z-index: 1;
            transition: .18s ease;
        }

        .company-btn:hover .arrow {
            background: var(--primary);
            color: #fff;
            transform: translateX(2px);
        }

        .footer-note {
            margin-top: 18px;
            color: #7a8ba0;
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 760px) {
            body {
                align-items: flex-start;
                padding-top: 22px;
            }

            .panel {
                padding: 22px;
                border-radius: 24px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .company-btn {
                min-height: 155px;
            }
        }
    </style>
</head>
<body>
    <main class="select-wrap">
        <section class="panel">
            <div class="brand-row">
                <div class="logo-mark">EGO</div>
                <div>
                    <h1>Chọn công ty làm việc</h1>
                    <div class="sub">
                        Dữ liệu CRM sẽ được lọc theo công ty đã chọn để tránh lẫn đơn hàng, công nợ, kho và đề nghị thanh toán.
                    </div>
                </div>
            </div>

            <div class="grid">
                @foreach($companies as $company)
                    <form method="POST" action="{{ url('/chon-cong-ty') }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $company->id }}">

                        <button class="company-btn" type="submit">
                            <div class="company-top">
                                <div class="company-icon">🏢</div>
                                <div class="arrow">→</div>
                            </div>

                            <div class="name">{{ $company->name }}</div>

                            <div class="hint">
                                Vào hệ thống với phạm vi dữ liệu riêng của công ty này.
                            </div>
                        </button>
                    </form>
                @endforeach
            </div>

            <div class="footer-note">
                Có thể đổi công ty bất kỳ lúc nào bằng nút “Đổi công ty” trong hệ thống.
            </div>
        </section>
    </main>
</body>
</html>
