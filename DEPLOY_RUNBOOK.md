# Deploy Runbook — crm.egosolar.com.vn

> Cập nhật 2026-09-21. Thông tin server cũ (103.200.23.139, `/home/crmegoso/www`, DB `crmegoso_lam25_crm_shop`) đã SAI/CŨ — **tuyệt đối không kết nối hay deploy lên 103.200.23.139**.
> Tài liệu này KHÔNG chứa credential. Mật khẩu DB chỉ nằm trong `.env` trên server và không được sao chép về máy local.

## Môi trường production

| Mục | Giá trị |
|---|---|
| Server | `103.200.23.68` (host68.vietnix.vn, LiteSpeed) |
| SSH user | `egosola1` (đăng nhập bằng khóa riêng, không dùng mật khẩu) |
| Thư mục Laravel | `/home/egosola1/crm.egosolar.com.vn` (git clone, remote `origin` = GitHub `lamquanmkt-hub/crm.egosolar.com.vn` qua SSH) |
| Domain | https://crm.egosolar.com.vn (`APP_URL` trong `.env` khớp) |
| PHP | 8.4.x (`php` mặc định), composer tại `/usr/local/bin/composer` |
| Database | tên đọc từ `DB_DATABASE` trong `.env` trên server (hiện là `egosola1_crm_shop`, MySQL/MariaDB `localhost`) — không đoán, luôn đọc lại từ `.env` |
| Cache/queue/session | `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=file` |
| Cache Laravel | production KHÔNG dùng config/route cache (`bootstrap/cache` chỉ có `packages.php`, `services.php`); giữ nguyên tư thế này. `routes/web.php` có closure nên `route:cache` sẽ lỗi. |
| Git hiện tại | HEAD detached tại commit đã deploy; nhánh `main` trên server giữ ở commit cũ để rollback |

Lưu ý hosting chia sẻ: server giới hạn SSH nặng nếu gọi liên tiếp. Gộp lệnh vào MỘT kết nối, nghỉ vài giây giữa các kết nối, và retry bằng vòng lặp nếu bị `Connection closed`.

## Nguyên tắc bắt buộc

1. Không deploy khi chưa có backup DB + code đã KIỂM TRA không rỗng.
2. Deploy theo ĐÚNG commit SHA (`git checkout --detach <SHA>`), không theo tên nhánh mơ hồ.
3. Không `migrate:fresh`, `db:wipe`, `db:seed`, rollback, import DB local lên production.
4. Đếm dòng các bảng nghiệp vụ trước và sau; bảng cũ không được giảm.
5. Đọc toàn bộ migration pending trước khi chạy: `up()` không được có `DROP`/`TRUNCATE`/`DELETE FROM`/`RENAME`.
6. Không thay đổi `.env` trên production.

## 0. Trước deploy — backup bắt buộc

Không trích mật khẩu bằng `eval`/`sed` (ký tự đặc biệt làm sai mật khẩu và ra file backup rỗng). Dùng parser dotenv của Laravel ghi ra file option MySQL quyền 600, dùng xong xóa ngay:

```bash
ssh egosola1@103.200.23.68        # dùng đúng khóa SSH của hosting
cd ~/crm.egosolar.com.vn
TS=$(date +%Y%m%d_%H%M%S)

# (a) file option MySQL tạm — đọc .env bằng Dotenv, không in mật khẩu
cat > _mkopt_tmp.php <<'PHP'
<?php
require __DIR__.'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->load();
$u=$_ENV['DB_USERNAME']; $p=$_ENV['DB_PASSWORD']; $d=$_ENV['DB_DATABASE'];
file_put_contents(__DIR__.'/.mysql_backup_opt.cnf', "[client]\nuser={$u}\npassword=\"".str_replace(['\\','"'],['\\\\','\\"'],$p)."\"\n");
chmod(__DIR__.'/.mysql_backup_opt.cnf', 0600);
echo $d;
PHP
DBNAME=$(php _mkopt_tmp.php)

# (b) dump + backup code + ghi lại commit
mysqldump --defaults-extra-file=.mysql_backup_opt.cnf --single-transaction --routines --triggers "$DBNAME" | gzip > ~/backup_db_${TS}.sql.gz
rm -f _mkopt_tmp.php .mysql_backup_opt.cnf
git rev-parse HEAD > ~/backup_commit_${TS}.txt
tar czf ~/backup_code_${TS}.tar.gz --exclude=vendor --exclude=node_modules --exclude=storage/logs .

# (c) KIỂM TRA backup (đừng tin exit code của pipe)
ls -lh ~/backup_*_${TS}*
gunzip -t ~/backup_db_${TS}.sql.gz && zcat ~/backup_db_${TS}.sql.gz | grep -c '^CREATE TABLE'   # phải > 0 (~350)
```

Đếm dòng bảng nghiệp vụ TRƯỚC deploy (chỉ đọc) và lưu kết quả: `users`, `payment_requests`, `payment_request_approvals`, `payment_attachments`, `tasks`, `crm_orders`, `crm_customers`, `sites`, `roles`, `permissions`, `role_has_permissions`, `model_has_roles`.

## 1. Đưa code lên bằng Git (không rsync)

Máy dev: commit, push nhánh (KHÔNG force push), ghi lại SHA.

```bash
git push -u origin <branch>
git rev-parse HEAD                    # SHA sẽ deploy
```

Server — Gate A (chưa đổi working tree):

```bash
cd ~/crm.egosolar.com.vn
git tag pre-<mô-tả>-$(date +%Y%m%d) $(git rev-parse HEAD)    # điểm rollback
git fetch origin <branch>
git cat-file -t <SHA>                                        # phải là "commit"
git merge-base --is-ancestor $(git rev-parse HEAD) <SHA> && echo OK
git diff --name-status HEAD <SHA> -- database/migrations     # liệt kê migration mới
git diff --name-only  HEAD <SHA> | grep -E '^(\.env|\.htaccess|composer\.)'   # phải rỗng
```

## 2. Maintenance + checkout + migrate

```bash
cd ~/crm.egosolar.com.vn
php artisan down --retry=60
git checkout --detach <SHA> && [ "$(git rev-parse HEAD)" = "<SHA>" ] || exit 1
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate:status | grep -i pending      # PHẢI đúng danh sách migration đã đọc; sai thì DỪNG, giữ maintenance
# đếm dòng lần nữa, rồi:
php artisan migrate --force
# đếm dòng SAU migrate: bảng cũ không giảm, bảng mới tồn tại
php artisan permission:cache-reset                # cache permission Spatie nằm trong DB cache, phải reset sau khi thêm permission
php artisan view:clear && php artisan config:clear && php artisan route:clear
php artisan up
```

Không chạy `npm run build` (giao diện dùng asset tĩnh trong `public/`, không dùng Vite).
Không chạy `cache:clear`/`optimize:clear` (cache store là database, tránh xóa cache không cần thiết).

## 3. Kiểm tra sau deploy

```bash
curl -sS -o /dev/null -w "%{http_code}\n" https://crm.egosolar.com.vn/login     # 200
# các route protected phải 302 -> /login, không được 500
git rev-parse HEAD ; git status --short | wc -l        # đúng SHA, working tree sạch (0)
grep "production.ERROR" storage/logs/laravel.log | tail -5   # không có lỗi mới sau thời điểm deploy
```

Nên đăng nhập thật bằng từng vai trò (nhân viên / trưởng phòng / Admin) và mở các trang chính. Nếu chỉ kiểm bằng script: chạy request GET nội bộ dưới danh nghĩa user thật (chỉ đọc), không in dữ liệu.

## 4. Rollback (nếu lỗi đăng nhập, HTTP 500, migration lỗi hoặc số dòng bảng cũ giảm)

Dừng ngay, không tự sửa dữ liệu, đưa site về maintenance, báo lỗi.

```bash
cd ~/crm.egosolar.com.vn
php artisan down
git checkout --detach <tag-điểm-rollback>          # hoặc: git checkout main (main giữ ở commit cũ)
php artisan view:clear && php artisan permission:cache-reset
php artisan up
```

- Các bảng/cột/permission do migration mới thêm chỉ THÊM, code cũ không tham chiếu nên có thể để nguyên; KHÔNG `migrate:rollback` trừ khi có lý do rõ ràng và đã đọc `down()`.
- Chỉ khôi phục DB từ `~/backup_db_<TS>.sql.gz` khi dữ liệu hỏng nặng (sẽ mất dữ liệu phát sinh sau thời điểm backup).
- Sau rollback, nhánh `main` trên GitHub phải khớp với commit đang chạy để lần deploy sau không hoàn tác nhầm.

## 5. Bẫy đã gặp

- `git checkout main` trên server có thể đưa working tree về code cũ trong khi cache vẫn dựng từ code mới (500 toàn site, sự cố 2026-07-20). Luôn kiểm `git rev-parse HEAD` đúng SHA rồi mới đi tiếp, và luôn kiểm từ ngoài bằng `curl`.
- Nếu `main` trên GitHub chưa chứa commit đang chạy production thì không được `git checkout -B main origin/main` trên server (sẽ hoàn tác tính năng). Merge nhánh đã deploy vào `main` trước.
- Backup DB bằng `eval`/`sed` trích mật khẩu có thể ra file rỗng 20 byte mà exit code vẫn 0 (do pipe). Luôn `gunzip -t` và đếm `CREATE TABLE`.
- Không lưu credential vào tài liệu, script hay lịch sử chat.

## 6. Việc bảo mật còn tồn đọng (thủ công, ngoài phạm vi deploy)

- Toàn bộ mã nguồn Laravel nằm ở document root, chỉ được che bởi `.htaccess`; các script gốc như `RESET_CT_OLD_000036_CHUA_AI_PHE_DUYET.php`, `force_*.php`, `install_*.py` nên được chuyển ra ngoài web root hoặc xóa khi đã xác nhận không dùng.
- File upload (chứng từ thanh toán, HR, hồ sơ) vẫn lưu trên disk public, cần kế hoạch chuyển sang private + di chuyển file cũ.
- Rà tài khoản test/mật khẩu yếu trên production (ví dụ tài khoản `admin@egosolar.test` nếu còn tồn tại).
