<?php

namespace App\Models\CRM\Customers;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRating extends Model
{
	use HasFactory;

	protected $table = 'crm_customer_ratings';

	protected $fillable = [
		'customer_id',
		'rating_type_id',
		'note',
		'rated_by',
	];

	// Khách hàng
	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class);
	}

	// Loại đánh giá
	public function ratingType(): BelongsTo
	{
		return $this->belongsTo(CustomerRatingType::class, 'rating_type_id');
	}

	// Người đánh giá
	public function rater(): BelongsTo
	{
		return $this->belongsTo(User::class, 'rated_by');
	}
}
