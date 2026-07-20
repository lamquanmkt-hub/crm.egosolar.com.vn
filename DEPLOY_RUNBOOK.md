# Deploy Runbook — crm.egosolar.vn (production 103.200.23.139)

Môi trường production (đã khảo sát 2026-07-19):
- Web root: `/home/crmegoso/www` — git clone nhánh `main`, working tree sạch tại commit `8a2fa03`.
- PHP CLI: `/usr/local/bin/php` (khớp `composer.json`). MariaDB 10.11.18, DB `crmegoso_lam25_crm_shop` (~260 bảng, ~20 MB, 32 users).
- `APP_ENV=production`, `APP_DEBUG=false`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=file`.
- Ổ đĩa: 1.3 TB trống. SSH: đã cài pubkey `~/.ssh/id_ed25519` của máy dev.

> ⚠️ Không tự động deploy. Đây là thao tác lên production — chỉ chạy khi có xác nhận của bạn. Các thay đổi code hiện đang ở local, chưa commit/push.

## 0. TRƯỚC KHI DEPLOY — backup bắt buộc (không mất dữ liệu)

```bash
ssh crmegoso@103.200.23.139
cd ~/www
TS=$(date +%Y%m%d_%H%M%S)

# (a) Backup DB đầy đủ (structure + data) — lấy creds từ .env
eval $(grep -E '^DB_(DATABASE|USERNAME|PASSWORD)=' .env | sed 's/^/EX_/')
mysqldump --single-transaction --routines --triggers \
  -u"$EX_DB_USERNAME" -p"$EX_DB_PASSWORD" "$EX_DB_DATABASE" \
  | gzip > ~/backup_db_${TS}.sql.gz
ls -lh ~/backup_db_${TS}.sql.gz    # xác nhận file > 0

# (b) Backup code hiện tại (rollback nhanh)
git rev-parse HEAD > ~/backup_commit_${TS}.txt
tar czf ~/backup_code_${TS}.tar.gz --exclude=vendor --exclude=node_modules --exclude=storage/logs .
```

## 1. Đưa code mới lên

Chọn 1 trong 2 (khuyến nghị git):

**Cách A — Git (khuyến nghị):** commit local → push nhánh `refactor/solid-2026-07` → trên server `git fetch && git checkout`:
```bash
# máy dev:
git checkout -b refactor/solid-2026-07
git add -A && git commit -m "Refactor SOLID/security/DB: contracts layer, XSS/mass-assignment fixes, safe indexes+FKs"
git push origin refactor/solid-2026-07
# server:
cd ~/www && git fetch origin && git checkout refactor/solid-2026-07
```

**Cách B — rsync trực tiếp** (nếu server không auth được GitHub), chỉ đẩy các thư mục đã đổi, loại trừ .env/storage/vendor:
```bash
# máy dev, từ thư mục repo:
rsync -avz --delete \
  --exclude='.env' --exclude='storage/' --exclude='vendor/' \
  --exclude='node_modules/' --exclude='public/build/' \
  app/ crmegoso@103.200.23.139:~/www/app/
rsync -avz database/migrations/ crmegoso@103.200.23.139:~/www/database/migrations/
rsync -avz routes/web.php resources/views/auth/login.blade.php database/seeders/ \
  crmegoso@103.200.23.139:~/www/  # điều chỉnh đường dẫn đích cho khớp
# LƯU Ý: file resources/views/auth/register.blade.php và RegisterController.php đã bị XÓA —
# xóa tương ứng trên server: rm -f ~/www/app/Http/Controllers/Auth/RegisterController.php ~/www/resources/views/auth/register.blade.php
```

## 2. Maintenance mode + cập nhật dependency + migrate

```bash
cd ~/www
php artisan down --render="errors::503" --retry=60   # bật bảo trì

composer install --no-dev --optimize-autoloader --no-interaction   # nếu composer.json đổi (đợt này KHÔNG đổi → có thể bỏ qua)

# Chạy 2 migration MỚI (chỉ 2 file, additive, đã test idempotent + orphan-safe trên bản sao):
php artisan migrate --force --path=database/migrations/2026_07_19_000001_add_performance_indexes_to_crm_orders.php
php artisan migrate --force --path=database/migrations/2026_07_19_000002_add_foreign_keys_zero_orphan_safe.php
# (hoặc `php artisan migrate --force` để chạy mọi migration pending — kiểm tra `php artisan migrate:status` trước)

# Rebuild cache:
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache
php artisan view:clear   && php artisan view:cache
php artisan event:clear  2>/dev/null

php artisan up   # tắt bảo trì
```

## 3. Verify sau deploy (smoke test)

```bash
php artisan migrate:status | tail -5          # 2 migration mới = Ran
php artisan about | grep -iE "environment|cache"
curl -sSI https://crm.egosolar.vn/login | head -1   # 200
# Đăng nhập thử 1 tài khoản, mở: /orders, /products, /sales/commissions, dashboard
# Kiểm tra tạo/sửa đơn hàng (đường refactor DIP OrderController) và trang tạo đơn (fix XSS @json)
tail -50 storage/logs/laravel.log   # không có lỗi mới
```

Xác nhận FK đã tạo (7 FK, có thể vài cái bị skip nếu phát sinh orphan mới):
```bash
php artisan tinker --execute='echo collect(DB::select("SELECT table_name,constraint_name FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_type=\"FOREIGN KEY\" AND constraint_name LIKE \"fk_%\""))->count()." FK\n";'
```

## 3b. 🔴 ĐỔI NHÁNH TRÊN SERVER — cạm bẫy đã gây sự cố thật (2026-07-20)

`git checkout main` trên server **KHÔNG** tự cập nhật nhánh: `main` local ở đó có thể còn ở commit cũ, nên checkout sẽ đưa working tree **về code cũ** trong khi config/route cache vẫn dựng từ code mới → **500 toàn site** (`Class "App\Providers\ContractServiceProvider" not found`). Đã xảy ra thật, downtime ~2 phút.

Cách đúng — gộp trong MỘT kết nối SSH (server throttle SSH nặng khi gọi liên tiếp):
```bash
cd ~/www
git fetch origin
git checkout -B main origin/main        # -B ép nhánh trỏ đúng origin/main
git rev-parse --short HEAD              # PHẢI khớp commit mong đợi TRƯỚC khi đi tiếp
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache
php artisan view:clear   && php artisan view:cache
```
Sau đó **luôn** kiểm tra từ ngoài, đừng tin mỗi exit code:
```bash
curl -sS -o /dev/null -w "%{http_code}\n" https://crm.egosolar.vn/login   # phải 200
```
Nếu SSH bị chặn (`kex_exchange_identification: Connection closed`), retry bằng vòng lặp thay vì gọi dồn:
`until ssh crmegoso@103.200.23.139 '<toàn bộ lệnh sửa>'; do sleep 20; done`

## 4. ROLLBACK (nếu có sự cố)

```bash
cd ~/www
php artisan down
# (a) Rollback code:
git checkout main            # hoặc: giải nén ~/backup_code_${TS}.tar.gz
# (b) Rollback 2 migration (down() đã test sạch, chỉ gỡ index + FK, KHÔNG đụng dữ liệu):
php artisan migrate:rollback --force --path=database/migrations/2026_07_19_000002_add_foreign_keys_zero_orphan_safe.php
php artisan migrate:rollback --force --path=database/migrations/2026_07_19_000001_add_performance_indexes_to_crm_orders.php
# (c) Chỉ khi dữ liệu hỏng nặng — phục hồi DB từ backup:
#   gunzip < ~/backup_db_${TS}.sql.gz | mysql -u"$EX_DB_USERNAME" -p"$EX_DB_PASSWORD" "$EX_DB_DATABASE"
php artisan config:cache && php artisan route:cache && php artisan up
```

## 5. VIỆC BẢO MẬT CẦN LÀM NGAY (thủ công, không tự động vì đụng dữ liệu production)

- 🔴 **Tài khoản `admin@egosolar.test` có role `admin` và mật khẩu yếu `12345678`** (phát hiện trên production). Đây là lỗ hổng đăng nhập trực tiếp. Đề xuất: đăng nhập admin thật (`admin@egosolar.vn`), rồi **đổi mật khẩu hoặc vô hiệu hóa/xóa** tài khoản `admin@egosolar.test`. Chưa xử lý tự động để tránh khóa nhầm tài khoản đang dùng.
- Đặt env `SEED_ADMIN_PASSWORD` / `SEED_USER_PASSWORD` nếu sau này chạy seeder trên staging.
- Cân nhắc cài cron `php artisan queue:work --stop-when-empty` hoặc supervisor cho `QUEUE_CONNECTION=database` (hiện chưa thấy worker).
