<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cleanup status types cũ và xóa migration record
     */
    public function up(): void
    {
        // Xóa dữ liệu status type cũ
        DB::table('crm_order_status_types')->whereIn('code', [
            'PENDING_KETOAN', 'APPROVED_KETOAN',
            'PENDING_DUYET1', 'APPROVED_DUYET1',
            'PENDING_DUYET2', 'APPROVED_DUYET2'
        ])->delete();

        // Update hoặc Insert status codes mới
        $statuses = [
            ['code'=>'PENDING_APPROVAL', 'name'=>'Chờ duyệt', 'department'=>'sales', 'order_sequence'=>1, 'is_final'=>false],
            ['code'=>'PENDING_SALES_MANAGER', 'name'=>'Chờ Sales Manager duyệt', 'department'=>'sales_manager', 'order_sequence'=>2, 'is_final'=>false],
            ['code'=>'PENDING_ACCOUNTING', 'name'=>'Chờ Kế toán duyệt', 'department'=>'accounting', 'order_sequence'=>3, 'is_final'=>false],
            ['code'=>'PENDING_MANAGEMENT', 'name'=>'Chờ Ban Giám đốc duyệt', 'department'=>'management', 'order_sequence'=>4, 'is_final'=>false],
            ['code'=>'READY_TO_SHIP', 'name'=>'Sẵn sàng xuất kho', 'department'=>'warehouse', 'order_sequence'=>5, 'is_final'=>false],
            ['code'=>'COMPLETED', 'name'=>'Hoàn tất', 'department'=>'completed', 'order_sequence'=>6, 'is_final'=>true],
            ['code'=>'REJECTED', 'name'=>'Bị từ chối', 'department'=>'sales', 'order_sequence'=>7, 'is_final'=>true],
            ['code'=>'CANCELLED', 'name'=>'Đã hủy', 'department'=>'cancelled', 'order_sequence'=>8, 'is_final'=>true],
        ];

        foreach ($statuses as $status) {
            DB::table('crm_order_status_types')->updateOrInsert(
                ['code' => $status['code']],
                $status
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert dữ liệu
        DB::table('crm_order_status_types')->whereIn('code', [
            'PENDING_APPROVAL', 'PENDING_SALES_MANAGER', 'PENDING_ACCOUNTING',
            'PENDING_MANAGEMENT', 'READY_TO_SHIP', 'COMPLETED',
            'REJECTED', 'CANCELLED'
        ])->delete();

        // Xóa migration record
        DB::table('migrations')
            ->whereIn('migration', [
                '2025_01_11_cleanup_status_types'
            ])
            ->delete();
    }
};
