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
	    $statuses = [
		    ['code'=>'PENDING_APPROVAL', 'name'=>'Chờ duyệt', 'department'=>'sales', 'order_sequence'=>1, 'is_final'=>false],
		    ['code'=>'PENDING_KETOAN', 'name'=>'Chờ kế toán', 'department'=>'ketoan', 'order_sequence'=>2, 'is_final'=>false],
		    ['code'=>'APPROVED_KETOAN', 'name'=>'Kế toán duyệt', 'department'=>'ketoan', 'order_sequence'=>3, 'is_final'=>false],
		    ['code'=>'PENDING_DUYET1', 'name'=>'Chờ duyệt cấp 1', 'department'=>'duyet1', 'order_sequence'=>4, 'is_final'=>false],
		    ['code'=>'APPROVED_DUYET1', 'name'=>'Đã duyệt cấp 1', 'department'=>'duyet1', 'order_sequence'=>5, 'is_final'=>false],
		    ['code'=>'PENDING_DUYET2', 'name'=>'Chờ duyệt cấp 2', 'department'=>'duyet2', 'order_sequence'=>6, 'is_final'=>false],
		    ['code'=>'APPROVED_DUYET2', 'name'=>'Đã duyệt cấp 2', 'department'=>'duyet2', 'order_sequence'=>7, 'is_final'=>false],
		    ['code'=>'COMPLETED', 'name'=>'Hoàn tất', 'department'=>'completed', 'order_sequence'=>8, 'is_final'=>true],
		    ['code'=>'REJECTED', 'name'=>'Bị từ chối', 'department'=>'sales', 'order_sequence'=>9, 'is_final'=>true],
	    ];

	    DB::table('crm_order_status_types')->insert($statuses);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    $codes = [
		    'PENDING_APPROVAL','PENDING_KETOAN','APPROVED_KETOAN',
		    'PENDING_DUYET1','APPROVED_DUYET1','PENDING_DUYET2',
		    'APPROVED_DUYET2','COMPLETED','REJECTED'
	    ];
	    DB::table('crm_order_status_types')->whereIn('code', $codes)->delete();
    }
};
