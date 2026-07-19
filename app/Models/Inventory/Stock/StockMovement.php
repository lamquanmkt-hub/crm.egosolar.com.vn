<?php
namespace App\Models\Inventory\Stock;
use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
	use HasFactory;
	protected $table = 'crm_stock_movements';
	protected $fillable = [
		'product_id',
		'warehouse_id',
		'change_qty',
		'reason',
		'reference_id',
		'company_id',
		'qty_before',
		'qty_after',
		'type',
		'movement_type',
		'note',
		'reference_type',
		'user_id',
		'created_by',
	];
	public function product()
	{
		return $this->belongsTo(Product::class);
	}
	public function warehouse()
	{
		return $this->belongsTo(Warehouse::class);
	}
	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}
}
