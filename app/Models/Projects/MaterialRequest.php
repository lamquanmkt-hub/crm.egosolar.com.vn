<?php

namespace App\Models\Projects;

use App\Models\Core\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequest extends Model
{
    protected $fillable = [
        'site_id',
        'warehouse_id',
        'created_by',
        'status',
        'note',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class, 'material_request_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function creator(): BelongsTo
    {
        // nếu dự án bạn không có App\Models\User thì đổi sang model user đúng
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return (string) $this->status === 'DRAFT';
    }
}
