<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeClaims();
        $this->createStockMovements();
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_warranty_stock_movements');
        // Không xóa các cột đã bổ sung trên crm_serial_warranty_claims để tránh mất dữ liệu vận hành.
    }

    private function upgradeClaims(): void
    {
        if (! Schema::hasTable('crm_serial_warranty_claims')) {
            Schema::create('crm_serial_warranty_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('serial_unit_id')->nullable()->index();
                $table->string('serial_code')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('status', 40)->default('received')->index();
                $table->date('received_at')->nullable()->index();
                $table->date('resolved_at')->nullable();
                $table->text('issue_description')->nullable();
                $table->text('resolution')->nullable();
                $table->decimal('cost', 15, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        $definitions = [
            'claim_code' => fn (Blueprint $table) => $table->string('claim_code', 40)->nullable()->unique()->after('id'),
            'company_id' => fn (Blueprint $table) => $table->unsignedBigInteger('company_id')->nullable()->index()->after('claim_code'),
            'site_id' => fn (Blueprint $table) => $table->unsignedBigInteger('site_id')->nullable()->index()->after('company_id'),
            'maintenance_schedule_id' => fn (Blueprint $table) => $table->unsignedBigInteger('maintenance_schedule_id')->nullable()->index()->after('site_id'),
            'claim_type' => fn (Blueprint $table) => $table->string('claim_type', 40)->default('warranty')->index()->after('maintenance_schedule_id'),
            'priority' => fn (Blueprint $table) => $table->string('priority', 20)->default('normal')->index()->after('claim_type'),
            'approval_status' => fn (Blueprint $table) => $table->string('approval_status', 30)->default('not_submitted')->index()->after('status'),
            'assigned_to' => fn (Blueprint $table) => $table->unsignedBigInteger('assigned_to')->nullable()->index()->after('approval_status'),
            'assigned_name' => fn (Blueprint $table) => $table->string('assigned_name', 190)->nullable()->after('assigned_to'),
            'diagnosis' => fn (Blueprint $table) => $table->text('diagnosis')->nullable()->after('issue_description'),
            'proposed_solution' => fn (Blueprint $table) => $table->text('proposed_solution')->nullable()->after('diagnosis'),
            'submitted_at' => fn (Blueprint $table) => $table->timestamp('submitted_at')->nullable()->after('proposed_solution'),
            'submitted_by' => fn (Blueprint $table) => $table->unsignedBigInteger('submitted_by')->nullable()->index()->after('submitted_at'),
            'approved_at' => fn (Blueprint $table) => $table->timestamp('approved_at')->nullable()->after('submitted_by'),
            'approved_by' => fn (Blueprint $table) => $table->unsignedBigInteger('approved_by')->nullable()->index()->after('approved_at'),
            'approval_note' => fn (Blueprint $table) => $table->text('approval_note')->nullable()->after('approved_by'),
            'replacement_serial_unit_id' => fn (Blueprint $table) => $table->unsignedBigInteger('replacement_serial_unit_id')->nullable()->index()->after('approval_note'),
            'replacement_serial_code' => fn (Blueprint $table) => $table->string('replacement_serial_code', 190)->nullable()->index()->after('replacement_serial_unit_id'),
            'returned_serial_unit_id' => fn (Blueprint $table) => $table->unsignedBigInteger('returned_serial_unit_id')->nullable()->index()->after('replacement_serial_code'),
            'returned_serial_code' => fn (Blueprint $table) => $table->string('returned_serial_code', 190)->nullable()->index()->after('returned_serial_unit_id'),
            'returned_at' => fn (Blueprint $table) => $table->timestamp('returned_at')->nullable()->after('returned_serial_code'),
            'customer_confirmed_at' => fn (Blueprint $table) => $table->timestamp('customer_confirmed_at')->nullable()->after('returned_at'),
            'closed_at' => fn (Blueprint $table) => $table->timestamp('closed_at')->nullable()->after('customer_confirmed_at'),
            'closed_by' => fn (Blueprint $table) => $table->unsignedBigInteger('closed_by')->nullable()->index()->after('closed_at'),
            'is_chargeable' => fn (Blueprint $table) => $table->boolean('is_chargeable')->default(false)->after('closed_by'),
            'estimated_cost' => fn (Blueprint $table) => $table->decimal('estimated_cost', 15, 2)->default(0)->after('is_chargeable'),
            'actual_cost' => fn (Blueprint $table) => $table->decimal('actual_cost', 15, 2)->default(0)->after('estimated_cost'),
            'internal_note' => fn (Blueprint $table) => $table->text('internal_note')->nullable()->after('actual_cost'),
            'deleted_at' => fn (Blueprint $table) => $table->softDeletes(),
        ];

        foreach ($definitions as $column => $definition) {
            if (! Schema::hasColumn('crm_serial_warranty_claims', $column)) {
                if (Schema::hasTable('crm_serial_warranty_claims')) {
                    Schema::table('crm_serial_warranty_claims', $definition);
                }
            }
        }

        // Bảng cũ bắt buộc serial_unit_id. Phiếu sự cố hệ thống có thể không gắn serial,
        // vì vậy chuẩn hóa cột này thành nullable mà không làm mất dữ liệu hiện hữu.
        if (Schema::hasColumn('crm_serial_warranty_claims', 'serial_unit_id')) {
            if (Schema::hasTable('crm_serial_warranty_claims')) {
                Schema::table('crm_serial_warranty_claims', function (Blueprint $table) {
                    if (! Schema::hasColumn('crm_serial_warranty_claims', 'serial_unit_id')) {
                        $table->unsignedBigInteger('serial_unit_id')->nullable()->change();
                    }
                });
            }
        }
    }

    private function createStockMovements(): void
    {
        if (Schema::hasTable('solar_warranty_stock_movements')) {
            return;
        }

        Schema::create('solar_warranty_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_code', 40)->nullable()->unique();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('warranty_claim_id')->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('movement_type', 40)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('serial_unit_id')->nullable()->index();
            $table->string('serial_code', 190)->nullable()->index();
            $table->unsignedBigInteger('related_serial_unit_id')->nullable()->index();
            $table->string('related_serial_code', 190)->nullable()->index();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->unsignedBigInteger('completed_by')->nullable()->index();
            $table->timestamp('requested_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
