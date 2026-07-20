<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\LeadRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Repository Eloquent thống kê dữ liệu lead.
 */
class LeadRepository implements LeadRepositoryInterface
{
    /**
     * Đếm tổng số lead.
     */
    public function count()
    {
        return DB::table('crm_leads')->count();
    }

    /**
     * Lấy danh sách lead mới nhất.
     */
    public function getRecent($limit = 5)
    {
        return DB::table('crm_leads')
            ->orderBy('contact_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Đếm số lead theo từng nguồn.
     */
    public function leadSourceCounts()
    {
        return DB::table('crm_leads')
            ->leftJoin('crm_sources', 'crm_sources.id', '=', 'crm_leads.source_id')
            ->select(DB::raw('COALESCE(crm_sources.name, "Unknown") as source_name'), DB::raw('COUNT(*) as total'))
            ->groupBy('source_name')
            ->orderByDesc('total')
            ->get();
    }
}
