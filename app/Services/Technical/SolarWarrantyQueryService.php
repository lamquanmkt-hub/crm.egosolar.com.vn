<?php

namespace App\Services\Technical;

use App\Models\Core\Warehouse;
use App\Models\Site;
use App\Models\SolarWarrantyClaim;
use App\Models\SolarWarrantyStockMovement;
use App\Models\User;
use App\Support\Synced\EgoCompanyScope;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SolarWarrantyQueryService
{
    public function summary(User $user): array
    {
        if (! $this->claimsReady()) {
            return ['total' => 0, 'open' => 0, 'pending_approval' => 0, 'waiting_stock' => 0, 'completed' => 0, 'completed_this_month' => 0, 'urgent_open' => 0, 'stock_pending' => 0];
        }

        $query = SolarWarrantyClaim::query();
        $this->scopeClaims($query, $user);
        $row = $query->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled','rejected') THEN 1 ELSE 0 END) AS open")
            ->selectRaw("SUM(CASE WHEN status = 'pending_approval' OR approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_approval")
            ->selectRaw("SUM(CASE WHEN status = 'waiting_stock' THEN 1 ELSE 0 END) AS waiting_stock")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN status = 'completed' AND YEAR(COALESCE(resolved_at, DATE(closed_at), DATE(updated_at))) = YEAR(CURDATE()) AND MONTH(COALESCE(resolved_at, DATE(closed_at), DATE(updated_at))) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS completed_this_month")
            ->selectRaw("SUM(CASE WHEN priority = 'urgent' AND status NOT IN ('completed','cancelled','rejected') THEN 1 ELSE 0 END) AS urgent_open")
            ->first();

        $stockPending = 0;
        if (SchemaCache::hasTable('solar_warranty_stock_movements')) {
            $stock = SolarWarrantyStockMovement::query()->where('status', 'pending');
            $this->scopeStock($stock, $user);
            $stockPending = $stock->count();
        }

        return [
            'total' => (int) ($row->total ?? 0),
            'open' => (int) ($row->open ?? 0),
            'pending_approval' => (int) ($row->pending_approval ?? 0),
            'waiting_stock' => (int) ($row->waiting_stock ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'completed_this_month' => (int) ($row->completed_this_month ?? 0),
            'urgent_open' => (int) ($row->urgent_open ?? 0),
            'stock_pending' => $stockPending,
        ];
    }

    public function claims(Request $request, User $user)
    {
        if (! $this->claimsReady()) {
            return collect();
        }

        $query = SolarWarrantyClaim::query()
            ->with(['site:id,name,contact_name,contact_phone,address,company_id', 'assignee:id,name', 'stockMovements:id,warranty_claim_id,status,movement_type']);
        $this->scopeClaims($query, $user);

        $search = trim((string) $request->input('claim_q', ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('claim_code', 'like', $like)
                    ->orWhere('serial_code', 'like', $like)
                    ->orWhere('issue_description', 'like', $like)
                    ->orWhereHas('site', fn (Builder $site) => $site->where('name', 'like', $like)->orWhere('contact_name', 'like', $like));
            });
        }
        if ($request->filled('claim_status')) {
            $query->where('status', $request->input('claim_status'));
        }
        if ($request->filled('claim_type')) {
            $query->where('claim_type', $request->input('claim_type'));
        }

        return $query->orderByRaw("CASE WHEN priority='urgent' THEN 0 WHEN priority='high' THEN 1 ELSE 2 END")
            ->orderByDesc('received_at')->orderByDesc('id')
            ->paginate(20, ['*'], 'claims_page')->withQueryString();
    }

    public function stockMovements(Request $request, User $user)
    {
        if (! SchemaCache::hasTable('solar_warranty_stock_movements')) {
            return collect();
        }

        $query = SolarWarrantyStockMovement::query()
            ->with(['claim:id,claim_code,status,site_id', 'site:id,name,company_id', 'warehouse:id,name', 'requester:id,name']);
        $this->scopeStock($query, $user);

        if ($request->filled('stock_status')) {
            $query->where('status', $request->input('stock_status'));
        }
        if ($request->filled('stock_type')) {
            $query->where('movement_type', $request->input('stock_type'));
        }

        return $query->orderByRaw("CASE WHEN status='pending' THEN 0 WHEN status='approved' THEN 1 ELSE 2 END")
            ->orderByDesc('id')->paginate(20, ['*'], 'stock_page')->withQueryString();
    }

    public function openClaimOptions(User $user)
    {
        if (! $this->claimsReady()) {
            return collect();
        }
        $query = SolarWarrantyClaim::query()
            ->with('site:id,name,company_id')
            ->whereIn('status', ['approved', 'waiting_stock', 'replacing', 'waiting_customer']);
        $this->scopeClaims($query, $user);

        return $query->latest('id')->limit(100)->get();
    }

    public function warehouses(User $user): mixed
    {
        if (! SchemaCache::hasTable('crm_warehouses')) {
            return collect();
        }

        $companyId = EgoCompanyScope::currentId();

        return Warehouse::query()
            ->select('crm_warehouses.id', 'crm_warehouses.name', 'crm_warehouses.location', 'crm_warehouses.company_id')
            ->when($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user), function (Builder $query) use ($companyId) {
                $query->where(function (Builder $warehouse) use ($companyId) {
                    $warehouse->where('crm_warehouses.company_id', $companyId)
                        ->orWhere(function (Builder $legacy) use ($companyId) {
                            $legacy->whereNull('crm_warehouses.company_id');
                            if (SchemaCache::hasTable('company_warehouse')) {
                                $legacy->where(function (Builder $ownership) use ($companyId) {
                                    $ownership->whereExists(function ($pivot) use ($companyId) {
                                        $pivot->selectRaw('1')->from('company_warehouse')
                                            ->whereColumn('company_warehouse.warehouse_id', 'crm_warehouses.id')
                                            ->where('company_warehouse.company_id', $companyId);
                                    })->orWhereNotExists(function ($pivot) {
                                        $pivot->selectRaw('1')->from('company_warehouse')
                                            ->whereColumn('company_warehouse.warehouse_id', 'crm_warehouses.id');
                                    });
                                });
                            }
                        });
                });
            })
            ->orderBy('crm_warehouses.name')
            ->get();
    }

    public function documentSites(User $user)
    {
        $companyId = EgoCompanyScope::currentId();
        $query = Site::withoutGlobalScopes()
            ->select(['sites.id', 'sites.name', 'sites.contact_name', 'sites.address', 'sites.company_id']);

        if (SchemaCache::hasTable('solar_site_documents')) {
            $query->selectSub(function ($q) {
                $q->from('solar_site_documents')
                    ->whereColumn('solar_site_documents.site_id', 'sites.id')
                    ->when(SchemaCache::hasColumn('solar_site_documents', 'deleted_at'), fn ($documents) => $documents->whereNull('solar_site_documents.deleted_at'))
                    ->selectRaw('COUNT(*)');
            }, 'documents_count');
        } else {
            $query->selectRaw('0 AS documents_count');
        }

        if (SchemaCache::hasTable('solar_maintenance_schedules')) {
            $query->selectSub(function ($q) {
                $q->from('solar_maintenance_schedules')
                    ->whereColumn('solar_maintenance_schedules.site_id', 'sites.id')
                    ->when(SchemaCache::hasColumn('solar_maintenance_schedules', 'deleted_at'), fn ($schedules) => $schedules->whereNull('solar_maintenance_schedules.deleted_at'))
                    ->selectRaw('COUNT(*)');
            }, 'maintenance_count');
        } else {
            $query->selectRaw('0 AS maintenance_count');
        }

        return $query
            ->when($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user), fn ($q) => $q->where('sites.company_id', $companyId))
            ->orderByDesc('maintenance_count')
            ->orderByDesc('documents_count')
            ->orderByDesc('id')
            ->limit(80)
            ->get();
    }

    public function recentClaims(User $user, int $limit = 5)
    {
        if (! $this->claimsReady()) {
            return collect();
        }
        $query = SolarWarrantyClaim::query()->with(['site:id,name,company_id', 'assignee:id,name']);
        $this->scopeClaims($query, $user);

        return $query->latest('id')->limit($limit)->get();
    }

    public function recentStock(User $user, int $limit = 5)
    {
        if (! SchemaCache::hasTable('solar_warranty_stock_movements')) {
            return collect();
        }
        $query = SolarWarrantyStockMovement::query()->with(['claim:id,claim_code', 'warehouse:id,name']);
        $this->scopeStock($query, $user);

        return $query->latest('id')->limit($limit)->get();
    }

    private function claimsReady(): bool
    {
        return SchemaCache::hasTable('crm_serial_warranty_claims')
            && SchemaCache::hasColumn('crm_serial_warranty_claims', 'claim_code')
            && SchemaCache::hasColumn('crm_serial_warranty_claims', 'company_id')
            && SchemaCache::hasColumn('crm_serial_warranty_claims', 'claim_type')
            && SchemaCache::hasColumn('crm_serial_warranty_claims', 'deleted_at');
    }

    private function scopeClaims(Builder $query, User $user): void
    {
        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user)) {
            $query->where(function (Builder $q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhere(function (Builder $fallback) use ($companyId) {
                        $fallback->whereNull('company_id')->whereHas('site', fn (Builder $site) => $site->where('company_id', $companyId));
                    });
            });
        }
        if (SolarMaintenanceAccess::isTechnicianOnly($user)) {
            $query->where('assigned_to', $user->id);
        }
    }

    private function scopeStock(Builder $query, User $user): void
    {
        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user)) {
            $query->where('company_id', $companyId);
        }
        if (SolarMaintenanceAccess::isTechnicianOnly($user) && ! SolarMaintenanceAccess::isWarehouse($user)) {
            $query->whereHas('claim', fn (Builder $claim) => $claim->where('assigned_to', $user->id));
        }
    }
}
