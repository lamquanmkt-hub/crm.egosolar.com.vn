<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ego_system_settings')) {
            return;
        }

        Schema::create('ego_system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ego_system_settings');
    }
};
