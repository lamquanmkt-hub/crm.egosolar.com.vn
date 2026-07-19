<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_asset_categories')) {
            Schema::create('finance_asset_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->nullable()->unique();
                $table->string('name', 150);
                $table->unsignedInteger('useful_life_months')->default(36);
                $table->string('color', 30)->default('#0ea5e9');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('finance_assets')) {
            Schema::create('finance_assets', function (Blueprint $table) {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 255);
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->string('department', 120)->nullable();
                $table->string('serial_no', 120)->nullable()->index();
                $table->date('purchase_date')->nullable()->index();
                $table->date('start_use_date')->nullable()->index();
                $table->date('warranty_until')->nullable()->index();
                $table->date('next_maintenance_date')->nullable()->index();
                $table->string('vendor', 255)->nullable();
                $table->string('invoice_no', 120)->nullable();
                $table->decimal('original_cost', 18, 2)->default(0);
                $table->decimal('salvage_value', 18, 2)->default(0);
                $table->unsignedInteger('useful_life_months')->default(36);
                $table->string('depreciation_method', 50)->default('straight_line');
                $table->string('location', 255)->nullable()->index();
                $table->string('status', 50)->default('active')->index();
                $table->string('condition', 50)->default('good')->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('finance_asset_events')) {
            Schema::create('finance_asset_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id')->index();
                $table->string('type', 50)->default('note')->index();
                $table->date('event_date')->nullable()->index();
                $table->decimal('amount', 18, 2)->nullable();
                $table->string('from_location', 255)->nullable();
                $table->string('to_location', 255)->nullable();
                $table->unsignedBigInteger('from_user_id')->nullable()->index();
                $table->unsignedBigInteger('to_user_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('finance_asset_files')) {
            Schema::create('finance_asset_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id')->index();
                $table->string('original_name', 255)->nullable();
                $table->string('path', 500);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('finance_asset_categories') && !DB::table('finance_asset_categories')->exists()) {
            $now = now();
            DB::table('finance_asset_categories')->insert([
                ['code' => 'MAY_MOC', 'name' => 'Máy móc / Thiết bị', 'useful_life_months' => 60, 'color' => '#2563eb', 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'VAN_PHONG', 'name' => 'Thiết bị văn phòng', 'useful_life_months' => 36, 'color' => '#0ea5e9', 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'CONG_CU', 'name' => 'Công cụ dụng cụ', 'useful_life_months' => 24, 'color' => '#10b981', 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'XE_CO', 'name' => 'Xe / Phương tiện', 'useful_life_months' => 72, 'color' => '#f59e0b', 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'KHAC', 'name' => 'Tài sản khác', 'useful_life_months' => 36, 'color' => '#64748b', 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_asset_files');
        Schema::dropIfExists('finance_asset_events');
        Schema::dropIfExists('finance_assets');
        Schema::dropIfExists('finance_asset_categories');
    }
};
