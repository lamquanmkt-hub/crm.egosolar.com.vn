<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MaterialAftercareRequest extends Model
{
    protected $table = 'project_test_material_aftercare_requests';

    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'warehouse_reviewed_at' => 'datetime',
        'manager_reviewed_at' => 'datetime',
        'return_processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function linkedMaterialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'linked_material_request_id');
    }

    public function items()
    {
        return $this->hasMany(MaterialAftercareItem::class, 'aftercare_request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function warehouseReviewer()
    {
        return $this->belongsTo(User::class, 'warehouse_reviewed_by');
    }

    public function managerReviewer()
    {
        return $this->belongsTo(User::class, 'manager_reviewed_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
