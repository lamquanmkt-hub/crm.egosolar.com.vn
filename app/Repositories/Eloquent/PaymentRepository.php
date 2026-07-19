<?php
namespace App\Repositories\Eloquent;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;
class PaymentRepository implements PaymentRepositoryInterface
{
	public function sumPayments()
	{
		return DB::table('crm_payments')->sum('amount');
	}
}
