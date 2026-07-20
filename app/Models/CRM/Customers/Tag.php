<?php

namespace App\Models\CRM\Customers;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'crm_tags';

    protected $fillable = [
        'name',
        'color',
        'description',
        'created_by',
    ];

    // Người tạo
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Khách hàng (nhiều-nhiều)
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'crm_customer_tags',
            'tag_id',
            'customer_id'
        )->withPivot('tagged_by', 'tagged_at')->withoutTimestamps();
    }
}
