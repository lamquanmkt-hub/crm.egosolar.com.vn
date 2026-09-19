<?php
namespace App\Repositories;
use App\Models\Payments\Payment;

class PaymentRepository
{
	public function all()
	{
		return Payment::with(['order', 'method', 'recordedBy'])->latest()->get();
	}
	public function sumPayments()
	{
		return Payment::sum('amount');
	}
}
