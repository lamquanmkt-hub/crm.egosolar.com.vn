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
	    Schema::dropIfExists('media_metadata');
	    Schema::dropIfExists('media_files');
	    Schema::dropIfExists('media_relations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
