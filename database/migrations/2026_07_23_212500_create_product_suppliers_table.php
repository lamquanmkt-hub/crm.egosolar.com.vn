<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('product_suppliers')) {
            Schema::create('product_suppliers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 191);
                $table->string('phone', 80)->nullable();
                $table->string('tax_code', 80)->nullable();
                $table->string('address', 500)->nullable();
                $table->text('note')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'is_active']);
                $table->index(['company_id', 'name']);
            });
        }

        if (Schema::hasTable('product_goods_receipts') && ! Schema::hasColumn('product_goods_receipts', 'supplier_id')) {
            Schema::table('product_goods_receipts', function (Blueprint $table) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('warehouse_id');
                $table->index('supplier_id');
            });
        }

        // Chuyển các tên NCC cũ thành danh mục để người dùng có thể chọn lại ngay.
        if (Schema::hasTable('product_suppliers') && Schema::hasTable('product_goods_receipts')) {
            $rows = DB::table('product_goods_receipts')
                ->whereNotNull('supplier_name')
                ->where('supplier_name', '<>', '')
                ->orderBy('id')
                ->get([
                    'company_id',
                    'supplier_name',
                    'supplier_phone',
                    'supplier_tax_code',
                    'supplier_address',
                ]);

            foreach ($rows as $row) {
                $name = trim((string) $row->supplier_name);
                if ($name === '') {
                    continue;
                }

                $supplier = DB::table('product_suppliers')
                    ->where('company_id', (int) $row->company_id)
                    ->where('name', $name)
                    ->first();

                if ($supplier) {
                    $supplierId = (int) $supplier->id;
                } else {
                    $supplierId = (int) DB::table('product_suppliers')->insertGetId([
                        'company_id' => (int) $row->company_id,
                        'name' => mb_substr($name, 0, 191),
                        'phone' => $row->supplier_phone ?: null,
                        'tax_code' => $row->supplier_tax_code ?: null,
                        'address' => $row->supplier_address ?: null,
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (Schema::hasColumn('product_goods_receipts', 'supplier_id')) {
                    DB::table('product_goods_receipts')
                        ->where('company_id', (int) $row->company_id)
                        ->where('supplier_name', $name)
                        ->whereNull('supplier_id')
                        ->update(['supplier_id' => $supplierId]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_goods_receipts') && Schema::hasColumn('product_goods_receipts', 'supplier_id')) {
            Schema::table('product_goods_receipts', function (Blueprint $table) {
                $table->dropIndex(['supplier_id']);
                $table->dropColumn('supplier_id');
            });
        }

        Schema::dropIfExists('product_suppliers');
    }
};
