<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    protected $table = 'project_test_surveys';

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_lat' => 'decimal:7',
        'check_in_lng' => 'decimal:7',
        'check_out_lat' => 'decimal:7',
        'check_out_lng' => 'decimal:7',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function surveyor()
    {
        return $this->belongsTo(User::class, 'surveyed_by');
    }
}
