<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Media\MediaRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'department_id',
        'position_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function leadsAssigned(): HasMany
    {
        return $this->hasMany(CRM\Leads\Lead::class, 'assigned_to');
    }

    public function leadsCreated(): HasMany
    {
        return $this->hasMany(CRM\Leads\Lead::class, 'created_by');
    }

    public function ordersCreated(): HasMany
    {
        return $this->hasMany(CRM\Orders\Order::class, 'created_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CRM\Orders\OrderApproval::class, 'approved_by');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(MediaRelation::class, 'model')
            ->where('usage_type', 'avatar');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(System\Conversation::class)
            ->withTimestamps()
            ->withPivot('last_read_at');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Position::class, 'position_id');
    }
}