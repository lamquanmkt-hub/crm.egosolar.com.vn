<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bàn làm việc Kỹ thuật — Giai đoạn 1
|--------------------------------------------------------------------------
|
| Được require ở cuối routes/technical.php (bản thân file đó được require từ
| routes/web.php), nên KHÔNG cần sửa routes/web.php.
|
| Quy ước đặt tên:
| - URL vẫn nằm dưới prefix `/ky-thuat` để `EnforcePageAccess` tiếp tục áp
|   đúng quyền `page.technical` (config/role_permissions.php khai báo
|   path_prefixes = ['/ky-thuat']). Không tạo lớp phân quyền mới.
| - Tên route dùng tiền tố MỚI `technical.*` để không đụng bất kỳ tên
|   `ky-thuat.*` nào đang tồn tại — đặc biệt là `ky-thuat.luong.*` đang bị
|   trùng giữa web.php và technical.php (vấn đề của giai đoạn khác, không
|   sửa ở đây).
|
| Mọi màn hình dưới đây CHỈ ĐỌC dữ liệu Công trình / Task / Bảo trì.
*/

use App\Http\Controllers\Technical\TechnicalDailyReportController;
use App\Http\Controllers\Technical\TechnicalDashboardController;
use App\Http\Controllers\Technical\TechnicalDashboardKpiController;
use App\Http\Controllers\Technical\TechnicalDashboardPlanController;
use App\Http\Controllers\Technical\TechnicalDashboardReportController;
use App\Http\Controllers\Technical\TechnicalGuideController;
use App\Http\Controllers\Technical\TechnicalPlanBoardController;
use App\Http\Controllers\Technical\TechnicalWeekPlanController;
use App\Http\Controllers\Technical\TechnicalWorkboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('ky-thuat')
    ->name('technical.')
    ->group(function (): void {

        /*
        | Bàn làm việc: công việc của tôi, lịch, quản lý.
        | (Tổng quan `/ky-thuat` được khai báo ở routes/technical.php để giữ
        |  nguyên tên route `ky-thuat.tong-quan` mà sidebar/link cũ đang dùng.)
        */
        Route::controller(TechnicalWorkboardController::class)->group(function (): void {
            Route::get('/cong-viec-cua-toi', 'myWork')->name('work.my');
            Route::get('/lich-cong-viec', 'calendar')->name('work.calendar');
            Route::get('/quan-ly-ky-thuat', 'management')->name('work.management');
        });

        /*
        |----------------------------------------------------------------------
        | Hướng dẫn sử dụng module Kỹ thuật (CHỈ ĐỌC, nội dung tĩnh)
        |----------------------------------------------------------------------
        | Nằm trong CHÍNH nhóm middleware `auth` + prefix `ky-thuat` của các
        | route Kỹ thuật khác, nên `EnforcePageAccess` vẫn áp `page.technical`
        | như mọi trang còn lại — không tạo lớp phân quyền mới. Controller kiểm
        | tra vai trò lần nữa và trả 403 cho slug không đúng vai trò.
        |
        | Đặt TRƯỚC `bao-cao-ngay` chỉ cho dễ đọc; hai URI không giao nhau.
        */
        Route::prefix('huong-dan')
            ->name('guides.')
            ->controller(TechnicalGuideController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/{slug}', 'show')
                    ->where('slug', '[a-z0-9\-]+')
                    ->name('show');
            });

        /*
        | Báo cáo ngày.
        | URI `/ky-thuat/bao-cao-ngay` — KHÔNG trùng `/ky-thuat/bao-cao` của
        | màn hình báo cáo cũ (`ky-thuat.bao-cao`), vốn vẫn được giữ nguyên.
        */
        Route::prefix('bao-cao-ngay')
            ->name('daily-reports.')
            ->controller(TechnicalDailyReportController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/tao', 'create')->name('create');
                Route::post('/', 'store')->name('store');

                Route::get('/{report}', 'show')->whereNumber('report')->name('show');
                Route::get('/{report}/sua', 'edit')->whereNumber('report')->name('edit');
                Route::put('/{report}', 'update')->whereNumber('report')->name('update');

                Route::post('/{report}/gui-duyet', 'submit')->whereNumber('report')->name('submit');
                Route::post('/{report}/duyet', 'approve')->whereNumber('report')->name('approve');
                Route::post('/{report}/yeu-cau-sua', 'requestRevision')->whereNumber('report')->name('request-revision');
                Route::post('/{report}/mo-lai', 'reopen')->whereNumber('report')->name('reopen');

                Route::get('/{report}/tep/{file}', 'downloadFile')
                    ->whereNumber('report')->whereNumber('file')->name('files.download');
                Route::delete('/{report}/tep/{file}', 'destroyFile')
                    ->whereNumber('report')->whereNumber('file')->name('files.destroy');
            });

        /*
        |----------------------------------------------------------------------
        | Giai đoạn 2 — Kế hoạch tuần do nhân viên tự lập
        |----------------------------------------------------------------------
        | Nhân viên chỉ thao tác trên kế hoạch của CHÍNH MÌNH; controller kiểm
        | tra lại quyền sở hữu từng dòng, không tin tham số URL.
        */
        Route::prefix('ke-hoach-tuan')
            ->name('week-plan.')
            ->controller(TechnicalWeekPlanController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');

                Route::post('/viec', 'storeItem')->name('items.store');
                Route::put('/viec/{item}', 'updateItem')->whereNumber('item')->name('items.update');
                Route::delete('/viec/{item}', 'destroyItem')->whereNumber('item')->name('items.destroy');
                Route::post('/viec/{item}/chuyen-ngay', 'moveItem')->whereNumber('item')->name('items.move');
                Route::post('/viec/{item}/sao-chep', 'copyItem')->whereNumber('item')->name('items.copy');
                Route::post('/viec/{item}/trang-thai', 'updateItemStatus')->whereNumber('item')->name('items.status');
                Route::post('/viec/{item}/chuyen-ngay-sau', 'proposeMoveToNextDay')->whereNumber('item')->name('items.defer');

                Route::post('/sao-chep-tuan-truoc', 'copyPreviousWeek')->name('copy-previous');
                Route::post('/danh-dau-ngay', 'markDay')->name('mark-day');
                Route::post('/luu-nhap', 'saveDraft')->name('save-draft');
                Route::post('/hoan-tat', 'finalize')->name('finalize');
            });

        /* Công việc hôm nay (nhân viên). */
        Route::get('/cong-viec-hom-nay', [TechnicalWeekPlanController::class, 'today'])
            ->name('today');

        /*
        |----------------------------------------------------------------------
        | Giai đoạn 2 — Điều phối của Trưởng phòng Kỹ thuật
        |----------------------------------------------------------------------
        */
        Route::prefix('quan-ly')
            ->name('manager.')
            ->controller(TechnicalPlanBoardController::class)
            ->group(function (): void {
                Route::get('/tong-quan', 'overview')->name('overview');
                Route::get('/ke-hoach', 'board')->name('board');
                Route::get('/tong-ket-tuan', 'weeklySummary')->name('weekly-summary');

                /*
                 | Luồng TẠO / GIAO kế hoạch trực tiếp từ trang ma trận (2026-09).
                 | `dau-viec` là endpoint CHỈ ĐỌC nạp danh sách đầu việc theo
                 | nhân viên cho ô chọn trong drawer; `luu` là LỐI GHI DÙNG CHUNG
                 | cho cả form tạo nhiều dòng lẫn form giao việc nhanh.
                 | Đặt TRƯỚC `/ke-hoach/{member}` cho rõ ý (route {member} vốn đã
                 | `whereNumber` nên không thể trùng với hai URI chữ này).
                 */
                Route::get('/ke-hoach/dau-viec', 'workItems')->name('work-items');
                Route::post('/ke-hoach/luu', 'storePlan')->name('plan.store');

                Route::get('/ke-hoach/{member}', 'detail')->whereNumber('member')->name('detail');
                Route::post('/ke-hoach/{member}/giao-viec', 'assign')->whereNumber('member')->name('assign');
                Route::post('/ke-hoach/{member}/yeu-cau-cap-nhat', 'requestUpdate')
                    ->whereNumber('member')->name('request-update');

                Route::put('/viec/{item}', 'adjust')->whereNumber('item')->name('items.adjust');
                Route::post('/viec/{item}/chuyen-ngay', 'move')->whereNumber('item')->name('items.move');
            });

        /*
        |----------------------------------------------------------------------
        | Giai đoạn 2 — Dashboard kết quả (Admin / Giám đốc, CHỈ XEM)
        |----------------------------------------------------------------------
        */
        /*
        | BỐN TRANG RIÊNG BIỆT (2026-09, bổ sung sau nghiệm thu):
        | mỗi mục menu của Admin có URL riêng, controller riêng, view riêng,
        | H1 riêng và breadcrumb riêng — không còn hai mục chung một URL.
        | Tất cả đều CHỈ ĐỌC và cùng một điều kiện quyền
        | (`AuthorizesTechnicalDashboard`): isAdmin() hoặc technical.dashboard.view.
        */
        /*
        | HỢP NHẤT 2026-09 (đợt khôi phục): mỗi trang MỘT controller riêng.
        |   - Tổng quan          -> TechnicalDashboardController
        |   - Kế hoạch (+chi tiết) -> TechnicalDashboardPlanController
        |   - Báo cáo tuần/tháng  -> TechnicalDashboardReportController
        |   - KPIs (bản tham khảo cũ) -> TechnicalDashboardKpiController, CHỈ
        |     chuyển hướng 302 sang trang KPI chính thức `ky-thuat.kpis.index`.
        |     Quyền được kiểm TRƯỚC khi chuyển hướng nên người không có quyền
        |     vẫn nhận 403 chứ không bị đẩy sang trang khác.
        */
        Route::prefix('dashboard')->group(function (): void {
            Route::get('/', [TechnicalDashboardController::class, 'index'])->name('dashboard');

            Route::controller(TechnicalDashboardPlanController::class)->group(function (): void {
                Route::get('/ke-hoach', 'index')->name('dashboard.plans');
                Route::get('/ke-hoach/{member}', 'show')->whereNumber('member')->name('dashboard.plans.detail');
            });

            Route::get('/bao-cao', [TechnicalDashboardReportController::class, 'index'])
                ->name('dashboard.reports');

            Route::controller(TechnicalDashboardKpiController::class)->group(function (): void {
                Route::get('/kpis', 'index')->name('dashboard.kpis');
                Route::get('/kpis/{member}', 'show')->whereNumber('member')->name('dashboard.kpis.detail');
            });
        });
    });
