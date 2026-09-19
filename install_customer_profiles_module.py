#!/usr/bin/env python3
from pathlib import Path
import shutil
import datetime
import re
import sys

ROOT = Path.cwd()
SRC = Path(__file__).resolve().parent
STAMP = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')

FILES = [
    ('app/Http/Controllers/CustomerProfileController.php', 'app/Http/Controllers/CustomerProfileController.php'),
    ('app/Models/CustomerProfile.php', 'app/Models/CustomerProfile.php'),
    ('app/Models/CustomerProfileDocument.php', 'app/Models/CustomerProfileDocument.php'),
    ('database/migrations/2026_05_13_000002_create_customer_profiles_module_tables.php', 'database/migrations/2026_05_13_000002_create_customer_profiles_module_tables.php'),
    ('resources/views/customer-profiles/_style.blade.php', 'resources/views/customer-profiles/_style.blade.php'),
    ('resources/views/customer-profiles/_messages.blade.php', 'resources/views/customer-profiles/_messages.blade.php'),
    ('resources/views/customer-profiles/_form.blade.php', 'resources/views/customer-profiles/_form.blade.php'),
    ('resources/views/customer-profiles/index.blade.php', 'resources/views/customer-profiles/index.blade.php'),
    ('resources/views/customer-profiles/create.blade.php', 'resources/views/customer-profiles/create.blade.php'),
    ('resources/views/customer-profiles/edit.blade.php', 'resources/views/customer-profiles/edit.blade.php'),
    ('resources/views/customer-profiles/show.blade.php', 'resources/views/customer-profiles/show.blade.php'),
]


def backup(path: Path):
    if path.exists():
        b = path.with_suffix(path.suffix + '.bak_customer_profiles_' + STAMP)
        shutil.copy2(path, b)
        print('BACKUP:', b)


def copy_file(src_rel: str, dst_rel: str):
    src = SRC / src_rel
    dst = ROOT / dst_rel
    if not src.exists():
        raise SystemExit(f'Missing source: {src}')
    dst.parent.mkdir(parents=True, exist_ok=True)
    backup(dst)
    shutil.copy2(src, dst)
    print('COPY:', dst_rel)


def patch_routes():
    web = ROOT / 'routes/web.php'
    if not web.exists():
        raise SystemExit('ERROR: Không thấy routes/web.php')
    backup(web)
    text = web.read_text(encoding='utf-8')
    marker = 'EGO_CUSTOMER_PROFILES_ROUTES_START'
    route_block = r'''
/* EGO_CUSTOMER_PROFILES_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('customer-profiles')
    ->name('customer-profiles.')
    ->controller(\App\Http\Controllers\CustomerProfileController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/sync-customers', 'syncCustomers')->name('sync-customers');
        Route::get('/export/csv', 'export')->name('export');
        Route::get('/{customerProfile}', 'show')->whereNumber('customerProfile')->name('show');
        Route::get('/{customerProfile}/edit', 'edit')->whereNumber('customerProfile')->name('edit');
        Route::put('/{customerProfile}', 'update')->whereNumber('customerProfile')->name('update');
        Route::delete('/{customerProfile}', 'destroy')->whereNumber('customerProfile')->name('destroy');
        Route::post('/{customerProfile}/documents', 'storeDocument')->whereNumber('customerProfile')->name('documents.store');
        Route::get('/{customerProfile}/documents/{document}', 'downloadDocument')->whereNumber('customerProfile')->whereNumber('document')->name('documents.download');
        Route::delete('/{customerProfile}/documents/{document}', 'destroyDocument')->whereNumber('customerProfile')->whereNumber('document')->name('documents.destroy');
    });
/* EGO_CUSTOMER_PROFILES_ROUTES_END */
'''
    if marker not in text:
        # Put near customer resource if possible, otherwise append before require hr.php or end.
        candidates = [
            "Route::resource('customers', CustomerController::class);",
            'Route::resource("customers", CustomerController::class);',
            "require __DIR__ . '/hr.php';",
        ]
        inserted = False
        for c in candidates:
            pos = text.find(c)
            if pos >= 0:
                if 'Route::resource' in c:
                    end = text.find('\n', pos)
                    if end < 0:
                        end = pos + len(c)
                    text = text[:end + 1] + route_block + text[end + 1:]
                else:
                    text = text[:pos] + route_block + '\n' + text[pos:]
                inserted = True
                break
        if not inserted:
            text += '\n' + route_block + '\n'
        web.write_text(text, encoding='utf-8')
        print('PATCH: routes/web.php added customer profile routes')
    else:
        print('SKIP: routes already patched')


def clone_menu_item(block: str) -> str:
    new = block
    # href route / url replacements.
    new = re.sub(r"route\(\s*['\"]customers\.[^'\"]+['\"]\s*\)", "route('customer-profiles.index')", new)
    new = re.sub(r"route\(\s*['\"]customers['\"]\s*\)", "route('customer-profiles.index')", new)
    new = re.sub(r"url\(\s*['\"]/customers[^'\"]*['\"]\s*\)", "route('customer-profiles.index')", new)
    new = re.sub(r"href\s*=\s*['\"][^'\"]*/customers[^'\"]*['\"]", "href=\"{{ route('customer-profiles.index') }}\"", new)
    new = re.sub(r"href=\"\{\{\s*route\('customers[^}]+\}\}\"", "href=\"{{ route('customer-profiles.index') }}\"", new)

    # Active state.
    new = re.sub(r"routeIs\(\s*['\"]customers\.[^'\"]+['\"]\s*\)", "routeIs('customer-profiles.*')", new)
    new = re.sub(r"is\(\s*['\"]customers[^'\"]*['\"]\s*\)", "is('customer-profiles*')", new)
    new = re.sub(r"request\(\)->is\(\s*['\"]customers[^'\"]*['\"]\s*\)", "request()->is('customer-profiles*')", new)

    labels = ['Khách hàng', 'Danh sách khách hàng', 'Customers', 'Customer']
    replaced = False
    for label in labels:
        if label in new:
            new = new.replace(label, 'Hồ sơ khách hàng')
            replaced = True
    if not replaced and 'Hồ sơ khách hàng' not in new:
        new = new.replace('</a>', '<span>Hồ sơ khách hàng</span></a>')

    new = new.replace('👥', '📁').replace('👤', '📁')
    if "customer-profiles.index" not in new:
        new = re.sub(r'href="[^"]*"', "href=\"{{ route('customer-profiles.index') }}\"", new, count=1)
    return new


def extract_anchor_block(text: str, pos: int):
    start = text.rfind('<a', 0, pos)
    if start < 0:
        return None
    end = text.find('</a>', pos)
    if end < 0:
        return None
    end += len('</a>')
    line_start = text.rfind('\n', 0, start)
    if line_start >= 0:
        start = line_start + 1
    line_end = text.find('\n', end)
    if line_end >= 0:
        end = line_end
    return start, end, text[start:end]


def patch_menu():
    views = ROOT / 'resources/views'
    if not views.exists():
        return
    candidates = []
    for p in views.rglob('*.blade.php'):
        try:
            text = p.read_text(encoding='utf-8')
        except Exception:
            continue
        score = 0
        low = str(p).lower()
        if 'customers.index' in text or '/customers' in text or 'Khách hàng' in text:
            score += 6
        if any(x in low for x in ['sidebar', 'menu', 'layout', 'partials', 'navigation', 'nav']):
            score += 6
        if '<a' in text and ('route(' in text or 'href=' in text):
            score += 2
        if score >= 8:
            candidates.append((score, p))
    candidates.sort(reverse=True)
    for _, p in candidates:
        text = p.read_text(encoding='utf-8')
        if 'customer-profiles.index' in text or '/customer-profiles' in text:
            print('SKIP: menu already has customer profiles:', p)
            return
        positions = []
        for pat in ['customers.index', '/customers', 'Khách hàng']:
            pos = text.find(pat)
            if pos >= 0:
                positions.append(pos)
        if not positions:
            continue
        block_info = extract_anchor_block(text, min(positions))
        if not block_info:
            continue
        start, end, block = block_info
        new_block = clone_menu_item(block)
        backup(p)
        text = text[:end] + '\n' + new_block + text[end:]
        p.write_text(text, encoding='utf-8')
        print('PATCH: menu item added in', p)
        return
    print('WARN: Không tự thêm được menu. Route vẫn chạy: /customer-profiles')


def main():
    for src, dst in FILES:
        copy_file(src, dst)
    patch_routes()
    patch_menu()
    storage_dir = ROOT / 'storage/app/public/customer-profile-documents'
    storage_dir.mkdir(parents=True, exist_ok=True)
    print('DONE: Module Hồ sơ khách hàng đã được cài file. Chạy tiếp: php artisan migrate && php artisan optimize:clear')


if __name__ == '__main__':
    main()
