<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');

            $table->string('type')->nullable();       // inverter/battery/...
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial')->nullable();
            $table->decimal('power_kw', 10, 2)->nullable();
            $table->decimal('capacity_kwh', 10, 2)->nullable();
            $table->integer('qty')->default(1);
            $table->date('warranty_to')->nullable();

            $table->timestamps();

            $table->foreign('site_id')
                ->references('id')->on('sites')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_devices');
    }
};
