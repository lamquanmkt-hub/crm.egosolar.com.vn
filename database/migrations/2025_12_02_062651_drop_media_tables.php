<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // media_relations có FK trỏ vào media_files — phải tắt kiểm tra FK
        // để migration replay được trên DB dựng mới từ đầu.
        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('media_metadata');
            Schema::dropIfExists('media_files');
            Schema::dropIfExists('media_relations');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
