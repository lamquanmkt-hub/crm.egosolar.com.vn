<?php

namespace App\Models\CRM\Leads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStatus extends Model
{
    protected $table = 'crm_lead_statuses';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'color_code', // Ví dụ: #FF0000, #00FF00
    ];

    /**
     * Quan hệ 1-nhiều với bảng Lead
     * Một trạng thái có thể áp dụng cho nhiều Lead
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'status_id');
    }
}
