<?php

declare(strict_types=1);

namespace App\Models\CRM\Consignments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerConsignmentReleaseItem extends Model
{
    protected $table = 'customer_consignment_release_items';

    protected $fillable = ['release_id', 'consignment_item_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function release(): BelongsTo
    {
        return $this->belongsTo(CustomerConsignmentRelease::class, 'release_id');
    }

    public function consignmentItem(): BelongsTo
    {
        return $this->belongsTo(CustomerConsignmentItem::class, 'consignment_item_id');
    }

    public function serialLinks(): HasMany
    {
        return $this->hasMany(CustomerConsignmentReleaseSerial::class, 'release_item_id');
    }
}
