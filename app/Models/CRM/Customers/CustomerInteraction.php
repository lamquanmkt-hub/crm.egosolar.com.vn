<?php

namespace App\Models\CRM\Customers;

use App\Models\CRM\Leads\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerInteraction extends Model
{
	use HasFactory;

	protected $table = 'crm_customer_interactions';

	protected $fillable = [
		'customer_id',
		'lead_id',
		'interaction_type',
		'subject',
		'content',
		'interaction_date',
		'duration_minutes',
		'outcome',
		'next_followup_date',
		'created_by',
	];

	protected $casts = [
		'interaction_date' => 'datetime',
		'next_followup_date' => 'datetime',
		'duration_minutes' => 'integer',
	];

	// Khách hàng
	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class);
	}

	// Lead
	public function lead(): BelongsTo
	{
		return $this->belongsTo(Lead::class);
	}

	// Người tạo
	public function creator(): BelongsTo
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	// Attachments
	public function attachments(): HasMany
	{
		return $this->hasMany(InteractionAttachment::class, 'interaction_id');
	}
}
