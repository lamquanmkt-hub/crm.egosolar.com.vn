<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_calendars', function (Blueprint $table) {
            $table->unsignedBigInteger('assignee_user_id')->nullable()->after('id');
            $table->index('assignee_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('content_calendars', function (Blueprint $table) {
            $table->dropColumn('assignee_user_id');
        });
    }
};