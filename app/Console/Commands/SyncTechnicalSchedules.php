<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Technical\TechnicalScheduleSyncService;
use Illuminate\Console\Command;

final class SyncTechnicalSchedules extends Command
{
    protected $signature = 'technical:schedule-sync {--company= : Chỉ đồng bộ một company_id}';

    protected $description = 'Đồng bộ lịch khảo sát, thi công và bảo trì vào lịch điều hành Kỹ thuật';

    public function handle(TechnicalScheduleSyncService $service): int
    {
        if (! $service->ready()) {
            $this->error('Chưa có bảng lịch Kỹ thuật. Hãy chạy php artisan migrate trước.');
            return self::FAILURE;
        }

        $companyId = $this->option('company');
        $result = $service->syncAll($companyId !== null ? (int) $companyId : null);

        $this->info('Đã đồng bộ '.$result['projects'].' công trình và '.$result['maintenance'].' lịch bảo trì.');
        return self::SUCCESS;
    }
}
