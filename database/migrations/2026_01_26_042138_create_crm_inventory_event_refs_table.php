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
        Schema::create('crm_inventory_event_refs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')
                ->constrained('crm_inventory_events')
                ->cascadeOnDelete();
            $table->string('ref_type', 50);
            $table->unsignedBigInteger('ref_id');
            $table->timestamps();
            $table->index(['ref_type', 'ref_id'], 'crm_inv_event_refs_ref_idx');
            $table->unique(['event_id', 'ref_type', 'ref_id'], 'crm_inv_event_refs_event_ref_uk');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_inventory_event_refs');
    }
};
