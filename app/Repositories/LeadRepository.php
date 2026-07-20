<?php

namespace App\Repositories;

use App\Models\CRM\Leads\Lead;

/**
 * Repository thao tác dữ liệu lead.
 */
class LeadRepository extends BaseRepository
{
    /**
     * Khởi tạo repository với model Lead.
     */
    public function __construct(Lead $lead)
    {
        $this->model = $lead;
    }

    /**
     * Lấy danh sách lead theo trạng thái.
     */
    public function getByStatus($statusId)
    {
        return $this->model->where('status_id', $statusId)->get();
    }

    /**
     * Lấy tất cả lead kèm khách hàng, nguồn và trạng thái, mới nhất trước.
     */
    public function all()
    {
        return Lead::with(['customer', 'source', 'status'])->latest()->get();
    }

    /**
     * Đếm tổng số lead.
     */
    public function count()
    {
        return Lead::count();
    }

    /**
     * Lấy danh sách lead mới nhất.
     */
    public function getRecent($limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Lead::with(['customer', 'source', 'status'])
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();
    }
}
