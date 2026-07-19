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
	    Schema::dropIfExists('media_relations');
        Schema::create('media_relations', function (Blueprint $table) {
            $table->id();
	        $table->foreignId('media_id')->constrained('media_files')->cascadeOnDelete();
	        $table->morphs('model'); // model_id + model_type
	        $table->string('usage_type', 50)->nullable(); // main_image, gallery, avatar, attachment
	        $table->integer('sort_order')->default(0);
	        $table->foreignId('attached_by')->nullable()->constrained('users')->nullOnDelete();
	        $table->timestamps();
	        $table->index(['usage_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_relations');
    }
};
