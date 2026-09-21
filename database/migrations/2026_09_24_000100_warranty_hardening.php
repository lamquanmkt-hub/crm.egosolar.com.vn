<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening (additive):
 *  - open_serial_norm UNIQUE: chống 2 phiếu MỞ cùng serial (kể cả serial ngoài CRM) — chuẩn hóa trim + UPPER.
 *    Phiếu "trùng có override" để NULL và lưu lý do (duplicate_override_*).
 *  - warranty_repair_parts.unit_price, warranty_repair_quotations: is_change / change_reason / base_quotation_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_serial_warranty_claims')) {
            Schema::table('crm_serial_warranty_claims', function (Blueprint $table): void {
                if (! Schema::hasColumn('crm_serial_warranty_claims', 'open_serial_norm')) {
                    $table->string('open_serial_norm', 190)->nullable()->unique('uq_wclaim_open_serial_norm');
                }
                if (! Schema::hasColumn('crm_serial_warranty_claims', 'duplicate_override_reason')) {
                    $table->text('duplicate_override_reason')->nullable();
                }
                if (! Schema::hasColumn('crm_serial_warranty_claims', 'duplicate_override_by')) {
                    $table->unsignedBigInteger('duplicate_override_by')->nullable();
                }
            });

            // Backfill an toàn: chỉ serial có ĐÚNG 1 phiếu mở (theo chuẩn hóa) mới được gán khóa.
            DB::statement("
                UPDATE crm_serial_warranty_claims c
                JOIN (
                    SELECT MIN(id) AS id
                    FROM crm_serial_warranty_claims
                    WHERE serial_code IS NOT NULL AND TRIM(serial_code) <> ''
                      AND deleted_at IS NULL
                      AND claim_type IN ('replacement','paid_repair')
                      AND status NOT IN ('completed','cancelled','rejected')
                    GROUP BY UPPER(TRIM(serial_code))
                    HAVING COUNT(*) = 1
                ) one ON one.id = c.id
                SET c.open_serial_norm = UPPER(TRIM(c.serial_code))
                WHERE c.open_serial_norm IS NULL
            ");
        }

        if (Schema::hasTable('warranty_repair_parts') && ! Schema::hasColumn('warranty_repair_parts', 'unit_price')) {
            Schema::table('warranty_repair_parts', function (Blueprint $table): void {
                $table->decimal('unit_price', 15, 2)->nullable();
            });
        }

        if (Schema::hasTable('warranty_repair_quotations')) {
            Schema::table('warranty_repair_quotations', function (Blueprint $table): void {
                if (! Schema::hasColumn('warranty_repair_quotations', 'is_change')) {
                    $table->boolean('is_change')->default(false);
                }
                if (! Schema::hasColumn('warranty_repair_quotations', 'change_reason')) {
                    $table->text('change_reason')->nullable();
                }
                if (! Schema::hasColumn('warranty_repair_quotations', 'base_quotation_id')) {
                    $table->unsignedBigInteger('base_quotation_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // additive — không xóa dữ liệu.
    }
};
