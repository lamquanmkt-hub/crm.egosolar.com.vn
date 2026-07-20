<?php

namespace App\Models\CRM\Leads;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingRating extends Model
{
    use HasFactory;

    protected $table = 'crm_marketing_ratings';

    protected $fillable = [
        'lead_id',
        'rating',
        'note',
        'created_by',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
