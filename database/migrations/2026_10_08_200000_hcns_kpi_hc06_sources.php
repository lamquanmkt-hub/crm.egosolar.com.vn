<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nguồn KPI HC06 (VPP, kiểm kê tài sản, sự cố văn phòng) — chỉ THÊM cột nullable/bảng mới.
 *
 * - hr_vpp_requests: phiếu cấp phát theo dõi KPI có người yêu cầu/xử lý bằng ID, mốc nhận yêu cầu, hạn,
 *   giao thực tế và người nhận xác nhận. Phiếu cũ giữ nguyên (không theo dõi KPI).
 * - hr_office_incidents: người xử lý, người báo xong, lần xử lý xong đầu tiên, thời gian chờ duyệt chi, giờ hủy.
 * - hr_asset_inventories (+ items): đợt kiểm kê theo kỳ, người phụ trách/kiểm, hạn và kết quả từng tài sản.
 * - hr_kpi_source_files: tệp minh chứng riêng tư cho hồ sơ nguồn KPI chưa có kho tệp riêng.
 * - hr_kpi_source_logs: lịch sử thao tác của hồ sơ nguồn chưa có bảng lịch sử riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_vpp_requests')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table): void {
                $add = fn (string $c) => ! Schema::hasColumn('hr_vpp_requests', $c);
                if ($add('company_id')) { $table->unsignedBigInteger('company_id')->nullable()->index(); }
                if ($add('kpi_tracked')) { $table->boolean('kpi_tracked')->default(false)->index(); }
                if ($add('handler_id')) { $table->unsignedBigInteger('handler_id')->nullable()->index(); }
                if ($add('reviewer_id')) { $table->unsignedBigInteger('reviewer_id')->nullable(); }
                if ($add('requester_user_id')) { $table->unsignedBigInteger('requester_user_id')->nullable(); }
                if ($add('request_received_at')) { $table->timestamp('request_received_at')->nullable(); }
                if ($add('due_at')) { $table->timestamp('due_at')->nullable(); }
                if ($add('delivered_at')) { $table->timestamp('delivered_at')->nullable(); }
                if ($add('receipt_confirmed_by')) { $table->unsignedBigInteger('receipt_confirmed_by')->nullable(); }
                if ($add('receipt_confirmed_at')) { $table->timestamp('receipt_confirmed_at')->nullable(); }
            });
        }

        if (Schema::hasTable('hr_office_incidents')) {
            Schema::table('hr_office_incidents', function (Blueprint $table): void {
                $add = fn (string $c) => ! Schema::hasColumn('hr_office_incidents', $c);
                if ($add('company_id')) { $table->unsignedBigInteger('company_id')->nullable()->index(); }
                if ($add('handler_id')) { $table->unsignedBigInteger('handler_id')->nullable()->index(); }
                if ($add('resolved_by')) { $table->unsignedBigInteger('resolved_by')->nullable(); }
                if ($add('first_resolved_at')) { $table->timestamp('first_resolved_at')->nullable(); }
                if ($add('waiting_since')) { $table->timestamp('waiting_since')->nullable(); }
                if ($add('approval_wait_minutes')) { $table->unsignedInteger('approval_wait_minutes')->default(0); }
                if ($add('cancelled_at')) { $table->timestamp('cancelled_at')->nullable(); }
            });
        }

        if (! Schema::hasTable('hr_asset_inventories')) {
            Schema::create('hr_asset_inventories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 40)->nullable();
                $table->char('period_month', 7)->index();
                $table->string('title', 255);
                $table->string('scope', 30)->default('hr_operation');
                $table->unsignedBigInteger('handler_id')->nullable()->index();
                $table->unsignedBigInteger('reviewer_id')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('status', 20)->default('planned')->index();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_asset_inventory_items')) {
            Schema::create('hr_asset_inventory_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('inventory_id')->index();
                $table->string('asset_source', 20);
                $table->unsignedBigInteger('asset_id');
                $table->string('asset_code', 80)->nullable();
                $table->string('asset_name', 255);
                $table->string('expected_holder', 255)->nullable();
                $table->string('result', 20)->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->unsignedBigInteger('checked_by')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['inventory_id', 'asset_source', 'asset_id'], 'hr_asset_inventory_items_unique');
            });
        }

        if (! Schema::hasTable('hr_kpi_source_files')) {
            Schema::create('hr_kpi_source_files', function (Blueprint $table): void {
                $table->id();
                $table->string('source_type', 60);
                $table->unsignedBigInteger('source_id');
                $table->string('disk', 20)->default('local');
                $table->string('path', 500);
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 190)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
                $table->index(['source_type', 'source_id']);
            });
        }

        // Lịch sử thao tác của hồ sơ nguồn chưa có bảng lịch sử riêng (đợt kiểm kê): lập, sửa kế hoạch, ghi kết quả, hủy.
        if (! Schema::hasTable('hr_kpi_source_logs')) {
            Schema::create('hr_kpi_source_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('source_type', 60);
                $table->unsignedBigInteger('source_id');
                $table->string('action', 60);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['source_type', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_kpi_source_logs');
        Schema::dropIfExists('hr_kpi_source_files');
        Schema::dropIfExists('hr_asset_inventory_items');
        Schema::dropIfExists('hr_asset_inventories');
        // Cột thêm vào bảng nghiệp vụ được giữ lại khi rollback để không mất mốc đã ghi.
    }
};
