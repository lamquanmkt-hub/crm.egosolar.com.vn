<?php
namespace App\Models\CRM\Leads;
use App\Models\CRM\Customers\Customer;
use App\Models\CRM\Customers\CustomerInteraction;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Tasks\SalesTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
	use HasFactory;
	protected $table = 'crm_leads';
	protected $fillable = [
		'customer_id',
		'source_id',
		'assigned_to',
		'contact_date',
		'status_id',
		'note',
		'marketing_note',
		'created_by',
	];
    protected $casts = [
        'contact_date' => 'date',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
	public function customer(): BelongsTo {
		return $this->belongsTo(Customer::class);
	}
	public function source(): BelongsTo {
		return $this->belongsTo(Source::class);
	}
	public function status(): BelongsTo {
		return $this->belongsTo(LeadStatus::class, 'status_id');
	}
	public function user(): BelongsTo {
		return $this->belongsTo(User::class, 'assigned_to');
	}
	public function assignedUser(): BelongsTo
	{
		return $this->belongsTo(User::class, 'assigned_to');
	}
	public function creator(): BelongsTo {
		return $this->belongsTo(User::class, 'created_by');
	}
	public function orders(): HasMany {
		return $this->hasMany(Order::class);
	}
	public function ratings(): HasMany {
		return $this->hasMany(MarketingRating::class);
	}
	public function latestOrder()
	{
		return $this->hasOne(Order::class)->latestOfMany('order_date');
	}
	// Customer Interactions
	public function interactions(): HasMany {
		return $this->hasMany(CustomerInteraction::class);
	}
	// Sales Tasks
	public function tasks(): HasMany {
		return $this->hasMany(SalesTask::class);
	}
}
