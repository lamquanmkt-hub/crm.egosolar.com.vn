<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('site_assemblies')) {
            Schema::create('site_assemblies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->unsignedBigInteger('material_warehouse_id');
                $table->unsignedBigInteger('finished_warehouse_id');
                $table->unsignedBigInteger('finished_product_id');
                $table->integer('finished_qty')->default(1);
                $table->date('produced_at')->nullable();
                $table->string('status', 30)->default('draft');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['site_id']);
                $table->index(['finished_product_id']);
            });
        }

        if (!Schema::hasTable('site_assembly_materials')) {
            Schema::create('site_assembly_materials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assembly_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('warehouse_id');
                $table->integer('qty')->default(1);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['assembly_id']);
                $table->index(['product_id', 'warehouse_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_assembly_materials');
        Schema::dropIfExists('site_assemblies');
    }
};
