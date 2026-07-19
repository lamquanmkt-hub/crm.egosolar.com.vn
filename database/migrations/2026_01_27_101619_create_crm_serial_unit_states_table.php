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
        Schema::create('crm_serial_unit_states', function (Blueprint $table) {
            /**
             * Đây là bảng CACHE trạng thái hiện tại (current state) của serial unit.
             * Nguồn sự thật vẫn là crm_inventory_events + crm_serial_event_lines (5NF).
             */
            // PK = serial_unit_id (1-1)
            $table->foreignId('serial_unit_id')
                ->primary()
                ->constrained('crm_serial_units')
                ->cascadeOnDelete();
            // Kho hiện tại (NULL nghĩa là không còn trong kho: sold/ra ngoài/unknown tuỳ state)
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('crm_warehouses')
                ->nullOnDelete();
            /**
             * Trạng thái hiện tại: in_stock/reserved/sold/returned/damaged/scrap/unknown...
             * MariaDB index tốt hơn với VARCHAR ngắn.
             */
            $table->string('state', 20)->default('unknown');
            // Event cuối cùng đã cập nhật state (để trace/debug)
            $table->foreignId('last_event_id')
                ->nullable()
                ->constrained('crm_inventory_events')
                ->nullOnDelete();
            // để audit nhanh
            $table->timestamp('synced_at')->useCurrent();
            // Index tối ưu màn danh sách
            $table->index(['warehouse_id', 'state', 'serial_unit_id'], 'crm_sus_wh_state_unit_idx');
            $table->index(['state', 'synced_at'], 'crm_sus_state_synced_idx');
            $table->index(['last_event_id'], 'crm_sus_last_event_idx');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_serial_unit_states');
    }
};
