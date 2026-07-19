<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

                $table->foreign('serial_unit_id')
                    ->references('id')
                    ->on('crm_serial_units')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_serial_warranties');
    }
};
