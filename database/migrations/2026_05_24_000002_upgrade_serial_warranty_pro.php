<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_serial_warranties')) {
            Schema::create('crm_serial_warranties', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('serial_unit_id')->unique();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->date('sold_at')->nullable()->index();
                $table->unsignedInteger('warranty_months')->default(60);
                $table->date('warranty_start_at')->nullable();
                $table->date('warranty_end_at')->nullable()->index();
                $table->string('status', 30)->default('active')->index();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_serial_warranty_events')) {
            Schema::create('crm_serial_warranty_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('serial_unit_id')->index();
                $table->string('serial_code')->nullable()->index();
                $table->string('event_type', 40)->index();
                $table->string('from_state', 40)->nullable();
                $table->string('to_state', 40)->nullable();
                $table->unsignedBigInteger('from_warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('to_warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crm_serial_warranty_claims')) {
            Schema::create('crm_serial_warranty_claims', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('serial_unit_id')->index();
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

        if (Schema::hasTable('crm_serial_identifiers')) {
            try {
                DB::statement('ALTER TABLE crm_serial_identifiers ADD UNIQUE ego_serial_code_unique (code)');
            } catch (Throwable $e) {}
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_serial_warranty_claims');
        Schema::dropIfExists('crm_serial_warranty_events');
    }
};
