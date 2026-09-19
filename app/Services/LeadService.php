<?php
namespace App\Services;
use App\Repositories\LeadRepository;
class LeadService
{
	protected $repo;
	public function __construct(protected LeadRepository $leads) {
		$this->repo = $leads;
	}
	public function count()
	{
		return $this->repo->count();
	}
	public function getRecentLeads($limit = 5)
	{
		return $this->repo->getRecent($limit);
	}
	public function assignLead($leadId, $userId)
	{
		$lead = $this->leads->find($leadId);
		$lead->assigned_to = $userId;
		$lead->save();
		return $lead;
	}
	public function markAsContacted($leadId)
	{
		$lead = $this->leads->find($leadId);
		$lead->status_id = 2; // giả sử 2 = contacted
		$lead->save();
		return $lead;
	}
}
