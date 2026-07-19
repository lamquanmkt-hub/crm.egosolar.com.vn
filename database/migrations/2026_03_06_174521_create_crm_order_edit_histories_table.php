<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_order_edit_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_role')->nullable();
            $table->json('changes')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_edit_histories');
    }
};