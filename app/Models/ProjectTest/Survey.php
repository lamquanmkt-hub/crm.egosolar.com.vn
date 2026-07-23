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
