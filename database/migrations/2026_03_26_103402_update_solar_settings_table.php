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
    Schema::table('solar_settings', function (Blueprint $table) {
        $table->string('key')->unique()->after('id');
        $table->string('label')->after('key');
        $table->string('group')->nullable()->after('label');
        $table->string('value')->nullable()->after('group');
        $table->string('type')->default('text')->after('value');
        $table->text('description')->nullable()->after('type');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
