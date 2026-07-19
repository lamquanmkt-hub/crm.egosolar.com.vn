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
        Schema::create('crm_serial_event_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')
                ->constrained('crm_inventory_events')
                ->cascadeOnDelete();
            $table->foreignId('serial_unit_id')
                ->constrained('crm_serial_units')
                ->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')
                ->nullable()
                ->constrained('crm_warehouses')
                ->nullOnDelete();
            $table->foreignId('to_warehouse_id')
                ->nullable()
                ->constrained('crm_warehouses')
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'serial_unit_id'], 'crm_sel_event_unit_uk');
            // History 1 serial: lấy theo serial_unit_id
            $table->index(['serial_unit_id', 'event_id'], 'crm_sel_unit_event_idx');
            // Lọc theo kho cho báo cáo
            $table->index(['to_warehouse_id', 'event_id'], 'crm_sel_to_wh_event_idx');
            $table->index(['from_warehouse_id', 'event_id'], 'crm_sel_from_wh_event_idx');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_serial_event_lines');
    }
};
