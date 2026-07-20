<?php
namespace App\Services;
use App\Repositories\LeadRepository;
/**
 * Service xử lý nghiệp vụ lead (khách hàng tiềm năng).
 */
class LeadService
{
	protected $repo;
	/**
	 * Khởi tạo service với repository lead.
	 */
	public function __construct(protected LeadRepository $leads) {
		$this->repo = $leads;
	}
	/**
	 * Đếm tổng số lead.
	 */
	public function count()
	{
		return $this->repo->count();
	}
	/**
	 * Lấy danh sách lead mới nhất.
	 */
	public function getRecentLeads($limit = 5)
	{
		return $this->repo->getRecent($limit);
	}
	/**
	 * Gán lead cho một user phụ trách.
	 */
	public function assignLead($leadId, $userId)
	{
		$lead = $this->leads->find($leadId);
		$lead->assigned_to = $userId;
		$lead->save();
		return $lead;
	}
	/**
	 * Đánh dấu lead đã được liên hệ.
	 */
	public function markAsContacted($leadId)
	{
		$lead = $this->leads->find($leadId);
		$lead->status_id = 2; // giả sử 2 = contacted
		$lead->save();
		return $lead;
	}
}
