<?php

declare(strict_types=1);

/**
 * RESET CT-OLD-000036 VỀ TRẠNG THÁI CHƯA AI PHÊ DUYỆT
 *
 * Căn cứ workflow hiện tại trong ProjectTestController:
 * - project.status = materials_admin_review
 * - project.current_owner_role = admin
 * - project.progress = 56
 * - material_request.status = pending_admin
 * - reviewed_by/reviewed_at/review_note = null
 *
 * Đặt file tại thư mục gốc Laravel rồi chạy:
 * php RESET_CT_OLD_000036_CHUA_AI_PHE_DUYET.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

define('LARAVEL_START', microtime(true));

$autoload = __DIR__.'/vendor/autoload.php';
$bootstrap = __DIR__.'/bootstrap/app.php';

if (!is_file($autoload) || !is_file($bootstrap)) {
    fwrite(STDERR, "LỖI: File phải nằm cùng cấp với artisan, vendor và bootstrap.\n");
    exit(1);
}

require $autoload;

/** @var \Illuminate\Foundation\Application $app */
$app = require $bootstrap;
$app->make(Kernel::class)->bootstrap();

const PROJECT_ID = 36;
const PROJECT_CODE = 'CT-OLD-000036';
const REQUEST_CODE = 'VT-202607-0001';

try {
    foreach ([
        'project_test_projects',
        'project_test_material_requests',
        'project_test_material_items',
        'project_test_histories',
    ] as $table) {
        if (!Schema::hasTable($table)) {
            throw new RuntimeException("Thiếu bảng: {$table}");
        }
    }

    $result = DB::transaction(function (): array {
        $project = DB::table('project_test_projects')
            ->where('id', PROJECT_ID)
            ->where('code', PROJECT_CODE)
            ->lockForUpdate()
            ->first();

        if (!$project) {
            throw new RuntimeException('Không tìm thấy đúng công trình #36 - CT-OLD-000036.');
        }

        $materialRequest = DB::table('project_test_material_requests')
            ->where('project_id', PROJECT_ID)
            ->where('code', REQUEST_CODE)
            ->lockForUpdate()
            ->first();

        if (!$materialRequest) {
            throw new RuntimeException('Không tìm thấy phiếu VT-202607-0001 của công trình này.');
        }

        if (
            (string)$project->status === 'materials_admin_review'
            && (string)$project->current_owner_role === 'admin'
            && (string)$materialRequest->status === 'pending_admin'
            && empty($materialRequest->reviewed_by)
            && empty($materialRequest->reviewed_at)
        ) {
            return [
                'already_done' => true,
                'project' => $project,
                'request' => $materialRequest,
                'backup' => null,
            ];
        }

        if (!in_array((string)$project->status, ['warehouse_preparing', 'materials_admin_review'], true)) {
            throw new RuntimeException(
                "Công trình đang ở trạng thái '{$project->status}'. "
                ."Chỉ cho phép hoàn từ warehouse_preparing hoặc chuẩn hóa materials_admin_review."
            );
        }

        if (!in_array((string)$materialRequest->status, ['approved', 'pending_admin'], true)) {
            throw new RuntimeException(
                "Phiếu đang ở trạng thái '{$materialRequest->status}'. "
                ."Không tự động hoàn để tránh tác động sai nghiệp vụ."
            );
        }

        $issuedCount = DB::table('project_test_material_items')
            ->where('material_request_id', $materialRequest->id)
            ->where('issued_quantity', '>', 0)
            ->count();

        if ($issuedCount > 0 || !empty($materialRequest->issued_at) || !empty($materialRequest->handed_over_at)) {
            throw new RuntimeException('Phiếu đã có dữ liệu xuất/bàn giao kho nên không được hoàn trực tiếp.');
        }

        $allocationCount = 0;
        if (Schema::hasTable('project_test_material_allocations')) {
            $allocationCount = DB::table('project_test_material_allocations as a')
                ->join('project_test_material_items as i', 'i.id', '=', 'a.material_item_id')
                ->where('i.material_request_id', $materialRequest->id)
                ->count();
        }

        if ($allocationCount > 0) {
            throw new RuntimeException(
                "Đã có {$allocationCount} dòng ghép/giữ hàng trong kho. "
                ."Cần thu hồi ghép hàng đúng nghiệp vụ trước khi hoàn."
            );
        }

        $backupDir = storage_path('app/ego-backups');
        File::ensureDirectoryExists($backupDir);

        $backupFile = $backupDir.'/CT-OLD-000036_before_unapprove_'.now()->format('Ymd_His').'.json';
        $backup = [
            'created_at' => now()->toDateTimeString(),
            'project' => (array)$project,
            'material_request' => (array)$materialRequest,
            'material_items' => DB::table('project_test_material_items')
                ->where('material_request_id', $materialRequest->id)
                ->orderBy('id')
                ->get()
                ->map(fn($row) => (array)$row)
                ->all(),
        ];

        if (file_put_contents(
            $backupFile,
            json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ) === false) {
            throw new RuntimeException('Không tạo được file backup.');
        }

        $now = now();

        DB::table('project_test_material_requests')
            ->where('id', $materialRequest->id)
            ->update([
                'status' => 'pending_admin',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
                'warehouse_status' => null,
                'warehouse_id' => null,
                'issued_by' => null,
                'receiver_id' => null,
                'reserved_at' => null,
                'issued_at' => null,
                'handed_over_at' => null,
                'issue_note' => null,
                'updated_at' => $now,
            ]);

        DB::table('project_test_projects')
            ->where('id', PROJECT_ID)
            ->update([
                'status' => 'materials_admin_review',
                'current_owner_role' => 'admin',
                'progress' => 56,
                'updated_at' => $now,
            ]);

        DB::table('project_test_histories')->insert([
            'project_id' => PROJECT_ID,
            'user_id' => null,
            'action' => 'Hoàn phiếu VT-202607-0001 về trạng thái chưa ai phê duyệt',
            'from_status' => (string)$project->status,
            'to_status' => 'materials_admin_review',
            'note' => 'Đã hủy dấu vết phê duyệt của phiếu; Admin cần thực hiện phê duyệt lại từ đầu.',
            'meta' => json_encode([
                'reset_approval' => true,
                'material_request_id' => (int)$materialRequest->id,
                'material_request_code' => REQUEST_CODE,
                'request_from_status' => (string)$materialRequest->status,
                'request_to_status' => 'pending_admin',
                'reviewed_by_reset' => true,
                'reviewed_at_reset' => true,
                'review_note_reset' => true,
                'source' => 'RESET_CT_OLD_000036_CHUA_AI_PHE_DUYET.php',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'already_done' => false,
            'project' => DB::table('project_test_projects')->where('id', PROJECT_ID)->first(),
            'request' => DB::table('project_test_material_requests')->where('id', $materialRequest->id)->first(),
            'backup' => $backupFile,
        ];
    }, 3);

    echo "\n============================================================\n";
    echo $result['already_done']
        ? "KHÔNG CẦN CẬP NHẬT: Đơn đã ở trạng thái chưa ai phê duyệt.\n"
        : "THÀNH CÔNG: Đã hoàn đơn về trạng thái chưa ai phê duyệt.\n";
    echo "Công trình : {$result['project']->status}\n";
    echo "Giữ bước   : {$result['project']->current_owner_role}\n";
    echo "Tiến độ    : {$result['project']->progress}%\n";
    echo "Phiếu VT   : {$result['request']->status}\n";
    echo "Người duyệt: ".($result['request']->reviewed_by ?? 'NULL')."\n";
    echo "Lúc duyệt  : ".($result['request']->reviewed_at ?? 'NULL')."\n";
    echo "Ghi chú    : ".($result['request']->review_note ?? 'NULL')."\n";
    if ($result['backup']) {
        echo "Backup     : {$result['backup']}\n";
    }
    echo "============================================================\n\n";
    echo "Chạy tiếp: php artisan optimize:clear\n";
    echo "Sau đó mở lại công trình và nhấn Ctrl + F5.\n\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\nKHÔNG CẬP NHẬT DỮ LIỆU.\n");
    fwrite(STDERR, "LỖI: {$e->getMessage()}\n\n");
    exit(1);
}
