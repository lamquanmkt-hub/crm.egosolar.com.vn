<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSalesCommission extends Command
{
    protected $signature = 'commission:backfill';
    protected $description = 'Tạo hoa hồng cho các đơn đã thanh toán đủ';

    public function handle()
    {
        $orders = DB::table('crm_orders')
            ->where('payment_recorded', 1)
            ->get();

        $count = 0;

        foreach ($orders as $order) {

            $exists = DB::table('sales_commissions')
                ->where('order_id', $order->id)
                ->exists();

            if ($exists) {
                continue;
            }

            // lấy customer từ lead
            $customerId = DB::table('crm_leads')
                ->where('id', $order->lead_id)
                ->value('customer_id');

            if (!$customerId) {
                continue;
            }

            // lấy rate từ customer
            $rate = 0; // tạm thời
            $baseAmount = (float) $order->total_amount;
            $commissionAmount = $baseAmount * $rate / 100;

            $salesUserId = $order->assigned_user_id ?? $order->created_by;

            if (!$salesUserId) {
                continue;
            }

            DB::table('sales_commissions')->insert([
                'order_id'          => $order->id,
                'customer_id'       => $customerId,
                'sales_user_id'     => $salesUserId,
                'base_amount'       => $baseAmount,
                'rate'              => $rate,
                'commission_amount' => $commissionAmount,
                'status'            => 'auto',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $count++;
        }

        $this->info("✅ Đã tạo {$count} hoa hồng");
    }
}
