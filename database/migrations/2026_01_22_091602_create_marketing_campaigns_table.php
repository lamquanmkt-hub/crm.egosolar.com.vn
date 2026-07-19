<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // tên chiến dịch
            $table->string('platform', 50);            // Facebook/Google/...
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('region', 100)->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['platform', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
