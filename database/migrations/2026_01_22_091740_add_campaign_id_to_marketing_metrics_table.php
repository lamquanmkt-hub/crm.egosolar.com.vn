<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketing_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->nullable()->after('platform');
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_metrics', function (Blueprint $table) {
            $table->dropIndex(['campaign_id']);
            $table->dropColumn('campaign_id');
        });
    }
};
