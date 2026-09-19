<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_test_projects')) {
            return;
        }

        Schema::table('project_test_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_test_projects', 'sales_order_id')) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->index()->after('customer_id');
            }
            if (! Schema::hasColumn('project_test_projects', 'contract_reference')) {
                $table->string('contract_reference', 180)->nullable()->after('sales_order_id');
            }
            if (! Schema::hasColumn('project_test_projects', 'handover_note')) {
                $table->text('handover_note')->nullable()->after('contract_reference');
            }
            if (! Schema::hasColumn('project_test_projects', 'handover_at')) {
                $table->timestamp('handover_at')->nullable()->after('handover_note');
            }
        });

        // Dữ liệu cũ có sales_user_id được xem là hồ sơ do Sales bàn giao.
        if (Schema::hasColumn('project_test_projects', 'request_source')) {
            DB::table('project_test_projects')
                ->whereNotNull('sales_user_id')
                ->where(function ($query): void {
                    $query->whereNull('request_source')->orWhere('request_source', '');
                })
                ->update(['request_source' => 'sales']);
        }

        if (Schema::hasColumn('project_test_projects', 'handover_at')) {
            DB::table('project_test_projects')
                ->where('request_source', 'sales')
                ->whereNull('handover_at')
                ->update(['handover_at' => DB::raw('COALESCE(created_at, updated_at)')]);
        }
    }

    public function down(): void
    {
        // Không xóa cột đã phát sinh dữ liệu nghiệp vụ để tránh mất lịch sử.
    }
};
