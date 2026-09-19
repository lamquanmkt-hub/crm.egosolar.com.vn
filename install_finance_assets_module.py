from pathlib import Path
import datetime
import shutil

root = Path.cwd()
web = root / 'routes' / 'web.php'

if not web.exists():
    raise SystemExit('ERROR: Hãy chạy ở thư mục gốc Laravel, không thấy routes/web.php')

stamp = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
backup = web.with_suffix(web.suffix + '.bak_finance_assets_' + stamp)
shutil.copy2(web, backup)
print('BACKUP:', backup)

text = web.read_text(encoding='utf-8')

route_block = """

        /* EGO_FINANCE_ASSETS_ROUTES_START */
        Route::prefix('assets')
            ->name('assets.')
            ->controller(\\App\\Http\\Controllers\\Finance\\AssetController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export/csv', 'exportCsv')->name('export');
                Route::post('/', 'store')->name('store');
                Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
                Route::post('/categories', 'storeCategory')->name('categories.store');
                Route::post('/{assetId}/events', 'storeEvent')->whereNumber('assetId')->name('events.store');
                Route::delete('/events/{eventId}', 'destroyEvent')->whereNumber('eventId')->name('events.destroy');
                Route::get('/files/{fileId}/download', 'downloadFile')->whereNumber('fileId')->name('files.download');
            });
        /* EGO_FINANCE_ASSETS_ROUTES_END */
"""

if 'EGO_FINANCE_ASSETS_ROUTES_START' not in text:
    marker = "        Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');"
    if marker not in text:
        raise SystemExit('ERROR: Không tìm thấy finance route group để chèn route tài sản.')
    text = text.replace(marker, marker + route_block, 1)
    web.write_text(text, encoding='utf-8')
    print('DONE: Đã thêm route /finance/assets')
else:
    print('SKIP: routes/web.php đã có route tài sản')

# Optional sidebar injection: best-effort only, duplicate an existing finance link line if safe.
if 'finance.assets.index' not in ''.join(p.read_text(encoding='utf-8', errors='ignore') for p in (root / 'resources' / 'views').rglob('*.blade.php') if p.is_file()):
    candidates = []
    for p in (root / 'resources' / 'views').rglob('*.blade.php'):
        try:
            s = p.read_text(encoding='utf-8')
        except Exception:
            continue
        score = 0
        if 'finance.supplier-debts.index' in s: score += 5
        if 'finance.accounts.index' in s: score += 3
        if 'finance.budget' in s: score += 2
        if 'payment_requests.index' in s: score += 1
        if 'sidebar' in str(p).lower() or 'layouts' in str(p).lower(): score += 2
        if score >= 5:
            candidates.append((score, p, s))

    if candidates:
        candidates.sort(reverse=True, key=lambda x: x[0])
        p, s = candidates[0][1], candidates[0][2]
        b = p.with_suffix(p.suffix + '.bak_finance_assets_menu_' + stamp)
        shutil.copy2(p, b)
        lines = s.splitlines()
        inserted = False
        for i, line in enumerate(lines):
            if 'finance.supplier-debts.index' in line and '<a' in line:
                new_line = line.replace("route('finance.supplier-debts.index')", "route('finance.assets.index')")
                new_line = new_line.replace('Công nợ nhà cung cấp', 'Tài sản').replace('Công nợ NCC', 'Tài sản')
                new_line = new_line.replace('supplier-debts', 'assets')
                lines.insert(i + 1, new_line)
                inserted = True
                break
        if inserted:
            p.write_text('\n'.join(lines) + '\n', encoding='utf-8')
            print('DONE: Đã thêm link Tài sản vào menu:', p)
        else:
            print('NOTE: Chưa tự thêm menu vì không nhận diện được cấu trúc sidebar. Route đã chạy tại /finance/assets')
    else:
        print('NOTE: Chưa tìm thấy file sidebar để tự thêm menu. Route đã chạy tại /finance/assets')
else:
    print('SKIP: Menu hoặc view đã có finance.assets.index')

print('DONE ALL')
