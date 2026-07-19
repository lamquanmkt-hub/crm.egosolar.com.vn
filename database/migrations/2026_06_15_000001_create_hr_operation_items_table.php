<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_operation_items')) {
            Schema::create('hr_operation_items', function (Blueprint $table) {
                $table->id();
                $table->string('group_key', 80)->index();
                $table->string('title');
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('status', 50)->default('new')->index();
                $table->string('owner')->nullable();
                $table->date('due_date')->nullable()->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_operation_items');
    }
};
