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
            if (! Schema::hasColumn('project_test_projects', 'request_source')) {
                $table->string('request_source', 40)->default('sales')->index()->after('created_by');
            }
            if (! Schema::hasColumn('project_test_projects', 'project_type')) {
                $table->string('project_type', 50)->default('commercial')->index()->after('request_source');
            }
            if (! Schema::hasColumn('project_test_projects', 'customer_confirmation_required')) {
                $table->boolean('customer_confirmation_required')->default(true)->after('project_type');
            }
            if (! Schema::hasColumn('project_test_projects', 'customer_confirmation_status')) {
                $table->string('customer_confirmation_status', 30)->default('pending')->index()->after('customer_confirmation_required');
            }
            if (! Schema::hasColumn('project_test_projects', 'customer_confirmed_by')) {
                $table->unsignedBigInteger('customer_confirmed_by')->nullable()->index()->after('customer_confirmation_status');
            }
            if (! Schema::hasColumn('project_test_projects', 'customer_confirmed_at')) {
                $table->timestamp('customer_confirmed_at')->nullable()->after('customer_confirmed_by');
            }
            if (! Schema::hasColumn('project_test_projects', 'customer_confirmation_note')) {
                $table->text('customer_confirmation_note')->nullable()->after('customer_confirmed_at');
            }
        });

        // Giữ tương thích dữ liệu cũ: hồ sơ có Sales được nhận diện là nguồn Sales.
        if (Schema::hasColumn('project_test_projects', 'request_source')) {
            DB::table('project_test_projects')
                ->whereNull('request_source')
                ->orWhere('request_source', '')
                ->update(['request_source' => 'sales']);
        }
        if (Schema::hasColumn('project_test_projects', 'project_type')) {
            DB::table('project_test_projects')
                ->whereNull('project_type')
                ->orWhere('project_type', '')
                ->update(['project_type' => 'commercial']);
        }

        // Các hồ sơ cũ đã qua giai đoạn phương án được xem là đã hoàn tất xác nhận,
        // tránh hiển thị sai rằng công trình đang thi công/bảo hành vẫn còn chờ Sales.
        if (Schema::hasColumn('project_test_projects', 'customer_confirmation_status')) {
            DB::table('project_test_projects')
                ->whereIn('status', [
                    'installation_pending', 'installation_reschedule', 'materials_pending',
                    'materials_admin_review', 'materials_revision', 'warehouse_preparing',
                    'warehouse_issued', 'assignment_pending', 'ready_install', 'installing',
                    'acceptance_pending', 'warranty_active', 'completed',
                ])
                ->update([
                    'customer_confirmation_status' => 'confirmed',
                    'customer_confirmed_at' => DB::raw('COALESCE(customer_confirmed_at, updated_at, created_at)'),
                ]);
        }
    }

    public function down(): void
    {
        // Không tự xóa các cột đã phát sinh dữ liệu nghiệp vụ để tránh mất lịch sử.
    }
};
