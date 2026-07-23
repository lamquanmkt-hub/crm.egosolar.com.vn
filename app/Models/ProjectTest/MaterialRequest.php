<?php

namespace App\Models\ProjectTest;

use App\Models\Core\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    protected $table = 'project_test_material_requests';

    protected $guarded = [];

    protected $casts = [
        'needed_at' => 'date',
        'reviewed_at' => 'datetime',
        'issued_at' => 'datetime',
        'reserved_at' => 'datetime',
        'handed_over_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function items()
    {
        return $this->hasMany(MaterialItem::class, 'material_request_id');
    }

    public function allocations()
    {
        return $this->hasManyThrough(
            MaterialAllocation::class,
            MaterialItem::class,
            'material_request_id',
            'material_item_id',
            'id',
            'id'
        );
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
