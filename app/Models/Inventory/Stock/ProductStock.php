<?php
namespace App\Models\Inventory\Stock;
use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStock extends Model
{
    protected $table = 'crm_product_stock';
    public $timestamps = false;
    protected $fillable = [
        'product_id',
        'company_id',
        'warehouse_id',
        'qty',
        'serials_json',
        'last_updated',
    ];
    protected $casts = [
        'product_id'   => 'integer',
        'company_id'   => 'integer',
        'warehouse_id' => 'integer',
        'qty'          => 'integer',
    ];
    protected $dates = ['last_updated'];
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
