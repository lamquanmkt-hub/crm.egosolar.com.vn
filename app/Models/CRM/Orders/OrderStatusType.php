<?php
namespace App\Models\CRM\Orders;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusType extends Model
{
	use HasFactory;
	protected $table = 'crm_order_status_types';
	protected $fillable = [
        'code',
        'name',
		'description',
		'department_id',
		'is_active'
	];
}
