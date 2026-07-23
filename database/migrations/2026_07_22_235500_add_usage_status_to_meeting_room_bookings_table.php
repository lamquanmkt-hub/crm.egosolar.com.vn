<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_room_bookings')) {
            return;
        }

        if (! Schema::hasColumn('meeting_room_bookings', 'usage_status')) {
            Schema::table('meeting_room_bookings', function (Blueprint $table): void {
                $table->string('usage_status', 24)
                    ->default('unused')
                    ->after('status');

                $table->index(
                    ['usage_status', 'start_at'],
                    'mrb_usage_time_idx'
                );
            });
        }

        DB::table('meeting_room_bookings')
            ->whereNull('usage_status')
            ->orWhere('usage_status', '')
            ->update(['usage_status' => 'unused']);
    }

    public function down(): void
    {
        if (
            Schema::hasTable('meeting_room_bookings')
            && Schema::hasColumn('meeting_room_bookings', 'usage_status')
        ) {
            Schema::table('meeting_room_bookings', function (Blueprint $table): void {
                try {
                    $table->dropIndex('mrb_usage_time_idx');
                } catch (\Throwable) {
                    // Index có thể đã được đổi hoặc xóa thủ công.
                }

                $table->dropColumn('usage_status');
            });
        }
    }
};
