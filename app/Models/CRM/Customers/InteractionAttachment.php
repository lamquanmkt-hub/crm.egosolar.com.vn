<?php

namespace App\Models\CRM\Customers;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteractionAttachment extends Model
{
    use HasFactory;

    protected $table = 'crm_interaction_attachments';

    protected $fillable = [
        'interaction_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Interaction
    public function interaction(): BelongsTo
    {
        return $this->belongsTo(CustomerInteraction::class, 'interaction_id');
    }
}
