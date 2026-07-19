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
       Schema::table('tasks', function (Blueprint $table) {
    if (!Schema::hasColumn('tasks', 'title')) {
        $table->string('title')->nullable()->after('id');
    }
    if (!Schema::hasColumn('tasks', 'description')) {
        $table->text('description')->nullable();
    }
    if (!Schema::hasColumn('tasks', 'requester_id')) {
        $table->unsignedBigInteger('requester_id')->nullable();
    }
    if (!Schema::hasColumn('tasks', 'assignee_id')) {
        $table->unsignedBigInteger('assignee_id')->nullable();
    }
    if (!Schema::hasColumn('tasks', 'priority')) {
        $table->string('priority')->default('medium');
    }
    if (!Schema::hasColumn('tasks', 'status')) {
        $table->string('status')->default('new');
    }
    if (!Schema::hasColumn('tasks', 'due_at')) {
        $table->dateTime('due_at')->nullable();
    }
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
