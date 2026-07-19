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
        Schema::create('crm_inventory_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_type', 30);
            $table->dateTime('occurred_at');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['event_type', 'occurred_at'], 'crm_inv_events_type_time_idx');
            $table->index(['occurred_at', 'id'], 'crm_inv_events_time_id_idx');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_inventory_events');
    }
};
