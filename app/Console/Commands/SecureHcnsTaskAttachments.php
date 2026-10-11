<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Tasks\TaskController;
use App\Services\Hr\Kpi\HcnsAdminTasks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Chuyển minh chứng của thủ tục hành chính HCNS (KPI HC09) đang nằm trên disk public sang disk riêng tư.
 * Mặc định chỉ liệt kê (dry-run); --apply mới chép → kiểm kích thước → cập nhật disk → xóa bản public.
 * Link trên trang việc đi qua route theo ID tệp nên không mất liên kết sau khi chuyển.
 */
final class SecureHcnsTaskAttachments extends Command
{
    protected $signature = 'hcns:secure-task-attachments {--apply : Thực hiện chuyển (mặc định chỉ liệt kê)}';

    protected $description = 'Chuyển tệp minh chứng thủ tục HCNS từ disk public sang riêng tư (an toàn, giữ liên kết)';

    public function handle(): int
    {
        if (! HcnsAdminTasks::ready() || ! Schema::hasColumn('task_attachments', 'disk')) {
            $this->error('DB chưa áp migration nguồn HCNS / cột task_attachments.disk — không làm gì.');

            return self::FAILURE;
        }

        $taskIds = DB::table('tasks')->where('task_type', HcnsAdminTasks::TYPE)->pluck('id')->all();
        $files = DB::table('task_attachments')->whereIn('task_id', $taskIds ?: [0])
            ->where(fn ($q) => $q->whereNull('disk')->orWhere('disk', 'public'))->orderBy('id')->get();
        $this->info('Thủ tục HCNS: '.count($taskIds).'; tệp còn ở disk public: '.$files->count().'.');
        foreach ($files as $file) {
            $exists = Storage::disk('public')->exists((string) $file->file_path);
            $this->line(sprintf('  #%d việc %d · %s · %s', $file->id, $file->task_id, $file->file_name, $exists ? 'có tệp' : 'KHÔNG thấy tệp — giữ nguyên'));
        }

        if (! $this->option('apply')) {
            $this->comment('Chạy lại với --apply để chuyển. Không có thay đổi nào được ghi.');

            return self::SUCCESS;
        }

        $moved = 0;
        foreach (collect($files)->pluck('task_id')->unique() as $taskId) {
            $moved += TaskController::securePublicAttachments((int) $taskId);
        }
        $this->info('Đã chuyển '.$moved.' tệp sang riêng tư.');

        return self::SUCCESS;
    }
}
