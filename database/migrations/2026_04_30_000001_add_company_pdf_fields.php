<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('tax_code')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('logo')->nullable();
                $table->string('bank_account')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_holder')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('companies', function (Blueprint $table) {
                if (!Schema::hasColumn('companies', 'tax_code')) {
                    $table->string('tax_code')->nullable()->after('code');
                }

                if (!Schema::hasColumn('companies', 'phone')) {
                    $table->string('phone')->nullable()->after('tax_code');
                }

                if (!Schema::hasColumn('companies', 'email')) {
                    $table->string('email')->nullable()->after('phone');
                }

                if (!Schema::hasColumn('companies', 'address')) {
                    $table->text('address')->nullable()->after('email');
                }

                if (!Schema::hasColumn('companies', 'logo')) {
                    $table->string('logo')->nullable()->after('address');
                }

                if (!Schema::hasColumn('companies', 'bank_account')) {
                    $table->string('bank_account')->nullable()->after('logo');
                }

                if (!Schema::hasColumn('companies', 'bank_name')) {
                    $table->string('bank_name')->nullable()->after('bank_account');
                }

                if (!Schema::hasColumn('companies', 'bank_holder')) {
                    $table->string('bank_holder')->nullable()->after('bank_name');
                }

                if (!Schema::hasColumn('companies', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('bank_holder');
                }
            });
        }

        if (Schema::hasTable('crm_orders') && !Schema::hasColumn('crm_orders', 'company_id')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('warehouse_id');
            });
        }

        if (Schema::hasTable('crm_warehouses') && !Schema::hasColumn('crm_warehouses', 'company_id')) {
            Schema::table('crm_warehouses', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            });
        }

        if (Schema::hasTable('companies')) {
            DB::table('companies')->updateOrInsert(
                ['code' => 'EGO_INT'],
                [
                    'name' => 'CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO',
                    'tax_code' => '0316898005',
                    'email' => 'ketoan@egosolar.vn',
                    'address' => 'Số 26, đường 24 B, Khu Phố 5, Phường Bình Trưng, Thành Phố Hồ Chí Minh, Việt Nam.',
                    'bank_account' => '1025726821',
                    'bank_name' => 'Ngân hàng TMCP Sài Gòn - Hà Nội chi nhánh Long An (SHB)',
                    'bank_holder' => 'CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO',
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('companies')->updateOrInsert(
                ['code' => 'EGO_VN'],
                [
                    'name' => 'CÔNG TY TNHH EGO VIỆT NAM',
                    'tax_code' => null,
                    'email' => null,
                    'address' => null,
                    'bank_account' => null,
                    'bank_name' => null,
                    'bank_holder' => 'CÔNG TY TNHH EGO VIỆT NAM',
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (
            Schema::hasTable('crm_warehouses') &&
            Schema::hasColumn('crm_warehouses', 'company_id') &&
            Schema::hasTable('companies')
        ) {
            $egoIntId = DB::table('companies')->where('code', 'EGO_INT')->value('id');
            $egoVnId = DB::table('companies')->where('code', 'EGO_VN')->value('id');

            if ($egoIntId) {
                DB::table('crm_warehouses')
                    ->where(function ($q) {
                        $q->where('name', 'like', '%QT%')
                          ->orWhere('name', 'like', '%Quốc Tế%')
                          ->orWhere('name', 'like', '%Quoc Te%')
                          ->orWhere('name', 'like', '%EGO_QT%');
                    })
                    ->update(['company_id' => $egoIntId]);
            }

            if ($egoVnId) {
                DB::table('crm_warehouses')
                    ->where(function ($q) {
                        $q->where('name', 'like', '%VN%')
                          ->orWhere('name', 'like', '%Việt Nam%')
                          ->orWhere('name', 'like', '%Viet Nam%')
                          ->orWhere('name', 'like', '%EGO_VN%');
                    })
                    ->update(['company_id' => $egoVnId]);
            }
        }

        if (
            Schema::hasTable('crm_orders') &&
            Schema::hasTable('crm_warehouses') &&
            Schema::hasColumn('crm_orders', 'company_id') &&
            Schema::hasColumn('crm_orders', 'warehouse_id') &&
            Schema::hasColumn('crm_warehouses', 'company_id')
        ) {
            DB::statement("
                UPDATE crm_orders o
                JOIN crm_warehouses w ON w.id = o.warehouse_id
                SET o.company_id = w.company_id
                WHERE o.company_id IS NULL
                  AND w.company_id IS NOT NULL
            ");
        }

        if (
            Schema::hasTable('crm_orders') &&
            Schema::hasTable('crm_order_items') &&
            Schema::hasTable('crm_warehouses') &&
            Schema::hasColumn('crm_orders', 'company_id') &&
            Schema::hasColumn('crm_order_items', 'order_id') &&
            Schema::hasColumn('crm_order_items', 'warehouse_id') &&
            Schema::hasColumn('crm_warehouses', 'company_id')
        ) {
            DB::statement("
                UPDATE crm_orders o
                JOIN crm_order_items oi ON oi.order_id = o.id
                JOIN crm_warehouses w ON w.id = oi.warehouse_id
                SET o.company_id = w.company_id
                WHERE o.company_id IS NULL
                  AND w.company_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                foreach ([
                    'tax_code',
                    'phone',
                    'email',
                    'address',
                    'logo',
                    'bank_account',
                    'bank_name',
                    'bank_holder',
                ] as $column) {
                    if (Schema::hasColumn('companies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
