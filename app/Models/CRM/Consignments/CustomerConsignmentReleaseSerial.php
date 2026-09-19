<?php

declare(strict_types=1);

namespace App\Models\CRM\Consignments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConsignmentReleaseSerial extends Model
{
    protected $table = 'customer_consignment_release_serials';

    protected $fillable = ['release_item_id', 'consignment_serial_id'];

    public function releaseItem(): BelongsTo
    {
        return $this->belongsTo(CustomerConsignmentReleaseItem::class, 'release_item_id');
    }

    public function consignmentSerial(): BelongsTo
    {
        return $this->belongsTo(CustomerConsignmentSerial::class, 'consignment_serial_id');
    }
}
