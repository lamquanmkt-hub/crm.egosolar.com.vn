# Bảo hành & O&M V13 — Kanban, Workspace & Checklist Builder

## Điều kiện

- Website đã triển khai V12.
- Sao lưu source và database trước khi cập nhật.

## Nội dung V13

- Trang Điều hành chuyển từ bảng dài sang Kanban theo bốn nhóm: Đầu vào, Đang thực hiện, Chờ duyệt, Cần chú ý.
- Công trình Sales bàn giao nhưng chưa lập kế hoạch xuất hiện trực tiếp trong cột Đầu vào.
- Kho hồ sơ hoàn thành có giao diện thẻ riêng.
- Trang Công việc chuyển sang workspace: thanh tiến trình bên trái, nội dung ở giữa, hồ sơ tại chỗ ở bên phải.
- Checklist mở từng bước; ghi chú, minh chứng và trạng thái hoàn thành nằm trong đúng bước đang xử lý.
- Trang Cài đặt chuyển thành Checklist Builder ba vùng: thư viện khối, luồng thực hiện, bảng thuộc tính.
- Hỗ trợ kéo-thả và lưu thứ tự checklist vào database.
- Giữ nguyên quy tắc V12: thiếu file theo từng mục thì không thể hoàn tất, gửi duyệt hoặc phê duyệt.
- Giữ nguyên phê duyệt độc lập theo từng đợt.

## Vị trí upload

```text
/home/egosola1/bao-hanh-om-v13-workspace-builder-20260809.tar.gz
```

## Lệnh triển khai

```bash
APP_ROOT="/home/egosola1/crm.egosolar.com.vn"
PATCH_FILE="/home/egosola1/bao-hanh-om-v13-workspace-builder-20260809.tar.gz"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_FILE="/home/egosola1/bao-hanh-om-before-v13-${STAMP}.tar.gz"
DEPLOY_TMP="$(mktemp -d /tmp/ego-om-v13.XXXXXX)"

cd "$APP_ROOT" || exit 1

tar -czf "$BACKUP_FILE" \
    routes/web.php \
    app/Http/Controllers/Technical \
    app/Http/Requests/Technical \
    app/Models/SolarMaintenanceAttachment.php \
    app/Models/SolarMaintenanceChecklistItem.php \
    app/Models/SolarMaintenanceChecklistTemplate.php \
    app/Services/Technical \
    resources/views/technical/maintenance \
    public/css/technical-maintenance-v10.css \
    public/js/technical-maintenance-v10.js

tar --no-same-owner -xzf "$PATCH_FILE" -C "$DEPLOY_TMP"
cp -a "$DEPLOY_TMP/source/." "$APP_ROOT/"

cd "$APP_ROOT" || exit 1
php artisan migrate --force
php artisan optimize:clear
php artisan route:list --path=ky-thuat/bao-tri-bao-hanh/cai-dat-checklist

echo "Backup source: $BACKUP_FILE"
echo "Đã triển khai Bảo hành & O&M V13"
```

## Kiểm tra

1. Mở `/ky-thuat/bao-tri-bao-hanh` và kiểm tra bốn cột Kanban.
2. Mở một đợt đang thực hiện và thử mở lần lượt từng bước checklist.
3. Tải file vào một bước, đánh dấu xong và lưu checklist.
4. Mở `/ky-thuat/bao-tri-bao-hanh/cai-dat-checklist`.
5. Kéo một khối sang vị trí mới, bấm **Lưu thứ tự**, sau đó tải lại trang.
6. Chọn một khối, sửa thuộc tính và xác nhận dữ liệu được lưu.
7. Kiểm tra Admin vẫn chỉ duyệt riêng đợt đang chọn.

## Khôi phục source V12

V13 không tạo migration mới so với V12 nên chỉ cần khôi phục source:

```bash
APP_ROOT="/home/egosola1/crm.egosolar.com.vn"
BACKUP_FILE="/home/egosola1/bao-hanh-om-before-v13-YYYYMMDD-HHMMSS.tar.gz"

tar -xzf "$BACKUP_FILE" -C "$APP_ROOT"
cd "$APP_ROOT" || exit 1
php artisan optimize:clear
```
