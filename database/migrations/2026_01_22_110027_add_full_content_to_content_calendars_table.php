<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('content_calendars', function (Blueprint $table) {
    $table->longText('full_content')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->unsignedBigInteger('approved_by')->nullable();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_calendars', function (Blueprint $table) {
            //
        });
    }
};
