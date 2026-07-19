<?php
namespace App\Repositories\Eloquent;
use App\Repositories\Interfaces\LeadRepositoryInterface;
use Illuminate\Support\Facades\DB;
class LeadRepository implements LeadRepositoryInterface
{
	public function count()
	{
		return DB::table('crm_leads')->count();
	}
	public function getRecent($limit = 5)
	{
		return DB::table('crm_leads')
		         ->orderBy('contact_date', 'desc')
		         ->limit($limit)
		         ->get();
	}
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
