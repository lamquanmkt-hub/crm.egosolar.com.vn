<?php

namespace App\Models\CRM\Sales;

use App\Models\CRM;
use Illuminate\Database\Eloquent\Model;

class SalesCommission extends Model
{
    protected $table = 'sales_commissions';

    protected $fillable = [
        'order_id',
        'customer_id',
        'sales_user_id',
        'base_amount',
        'rate',
        'commission_amount',
        'status',
    ];

    // 🔹 SALES
    public function salesUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'sales_user_id');
    }

    // 🔹 ĐƠN HÀNG
    public function order()
    {
        return $this->belongsTo(CRM\Orders\Order::class, 'order_id');
    }

    // 🔹 KHÁCH HÀNG
    public function customer()
    {
        return $this->belongsTo(CRM\Customers\Customer::class, 'customer_id');
    }
}
