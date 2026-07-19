<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermissionAudit extends Model
{
    protected $fillable = [
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_name',
        'before_data',
        'after_data',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
