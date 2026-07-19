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
        if (!Schema::hasColumn('content_calendars', 'link')) {
            $table->string('link', 255)->nullable()->after('description');
        }
    });
}

public function down(): void
{
    Schema::table('content_calendars', function (Blueprint $table) {
        if (Schema::hasColumn('content_calendars', 'link')) {
            $table->dropColumn('link');
        }
    });
}
};
