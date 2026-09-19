<?php
namespace App\Repositories;
use App\Models\CRM\Leads\Lead;

class LeadRepository extends BaseRepository
{
	public function __construct(Lead $lead)
	{
		$this->model = $lead;
	}
	public function getByStatus($statusId)
	{
		return $this->model->where('status_id', $statusId)->get();
	}
	public function all()
	{
		return Lead::with(['customer', 'source', 'status'])->latest()->get();
	}

	public function count()
	{
		return Lead::count();
	}
	public function getRecent($limit = 5): \Illuminate\Database\Eloquent\Collection {
		return Lead::with(['customer', 'source', 'status'])
		           ->orderByDesc('created_at')
		           ->take($limit)
		           ->get();
	}
}
