<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung cột `tasks.task_group_id` một cách an toàn (idempotent).
 *
 * Lý do: migration `2026_05_29_165701_add_task_group_id_to_tasks_table.php`
 * có thân hàm RỖNG (chỉ `//`), nên cột có thể chưa bao giờ được thêm dù tên
 * file khẳng định đã thêm. Bản `.save` cạnh nó mới chứa code thật.
 * Hai file cũ được giữ nguyên làm chứng tích lịch sử; migration này chỉ bổ
 * sung thêm, có guard `Schema::hasColumn` nên chạy được ở cả DB đã có cột
 * lẫn DB chưa có.
 *
 * Kiểu cột lấy đúng theo bản `.save`: string, nullable, có index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks') || Schema::hasColumn('tasks', 'task_group_id')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_group_id')->nullable()->after('id')->index();
        });
    }

    /**
     * Rollback có bảo vệ dữ liệu.
     *
     * up() là additive-có-guard: nếu cột đã tồn tại từ trước (ví dụ ai đó
     * thêm tay trên production) thì up() KHÔNG làm gì. Vì vậy down() không
     * được phép xóa cột một cách vô điều kiện — làm thế sẽ phá dữ liệu mà
     * migration này chưa bao giờ tạo ra.
     *
     * Quy tắc: chỉ drop khi cột đang RỖNG hoàn toàn (không có giá trị
     * non-null nào). Nếu đã có dữ liệu, giữ nguyên cột và không làm gì.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasColumn('tasks', 'task_group_id')) {
            return;
        }

        $hasData = DB::table('tasks')->whereNotNull('task_group_id')->exists();

        if ($hasData) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('task_group_id');
        });
    }
};
