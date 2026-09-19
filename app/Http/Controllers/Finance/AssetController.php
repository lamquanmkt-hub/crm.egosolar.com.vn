<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Support\EgoCompanyLock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quản lý tài sản cố định: danh sách, khấu hao, lịch sử và tệp đính kèm.
 */
class AssetController extends Controller
{
    private function assetQuery()
    {
        return DB::table('finance_assets')->where('company_id', EgoCompanyLock::id());
    }

    private array $statuses = [
        'active' => 'Đang sử dụng',
        'idle' => 'Nhàn rỗi',
        'repair' => 'Đang sửa chữa',
        'maintenance' => 'Bảo trì',
        'liquidated' => 'Đã thanh lý',
        'lost' => 'Mất/hỏng',
    ];

    private array $conditions = [
        'new' => 'Mới',
        'good' => 'Tốt',
        'normal' => 'Bình thường',
        'worn' => 'Hao mòn',
        'broken' => 'Hỏng',
    ];

    private array $eventTypes = [
        'purchase' => 'Ghi nhận mua mới',
        'assign' => 'Bàn giao / cấp phát',
        'transfer' => 'Điều chuyển',
        'maintenance' => 'Bảo trì',
        'repair' => 'Sửa chữa',
        'depreciation' => 'Khấu hao',
        'liquidation' => 'Thanh lý',
        'lost' => 'Mất / hỏng',
        'note' => 'Ghi chú',
    ];

    /**
     * Danh sách tài sản kèm bộ lọc, chỉ số khấu hao, lịch sử và tệp đính kèm.
     */
    public function index(Request $request)
    {
        $this->ensureDefaultCategories();

        $filters = [
            'keyword' => trim((string) $request->input('keyword', '')),
            'status' => trim((string) $request->input('status', '')),
            'category_id' => (int) $request->input('category_id', 0),
            'company_id' => (int) $request->input('company_id', 0),
            'condition' => trim((string) $request->input('condition', '')),
        ];

        $query = DB::table('finance_assets as a')
            ->where('a.company_id', EgoCompanyLock::id())
            ->leftJoin('finance_asset_categories as c', 'c.id', '=', 'a.category_id')
            ->select('a.*', 'c.name as category_name', 'c.color as category_color')
            ->whereNull('a.deleted_at');

        if (Schema::hasTable('companies')) {
            $query->leftJoin('companies as co', 'co.id', '=', 'a.company_id')
                ->addSelect('co.name as company_name');
        } else {
            $query->addSelect(DB::raw('NULL as company_name'));
        }

        if (Schema::hasTable('users')) {
            $query->leftJoin('users as u', 'u.id', '=', 'a.assigned_to')
                ->addSelect('u.name as assigned_name');
        } else {
            $query->addSelect(DB::raw('NULL as assigned_name'));
        }

        if ($filters['keyword'] !== '') {
            $kw = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['keyword']).'%';
            $query->where(function ($q) use ($kw) {
                $q->where('a.code', 'like', $kw)
                    ->orWhere('a.name', 'like', $kw)
                    ->orWhere('a.serial_no', 'like', $kw)
                    ->orWhere('a.vendor', 'like', $kw)
                    ->orWhere('a.invoice_no', 'like', $kw)
                    ->orWhere('a.location', 'like', $kw)
                    ->orWhere('a.note', 'like', $kw);
            });
        }

        if ($filters['status'] !== '' && isset($this->statuses[$filters['status']])) {
            $query->where('a.status', $filters['status']);
        }

        if ($filters['condition'] !== '' && isset($this->conditions[$filters['condition']])) {
            $query->where('a.condition', $filters['condition']);
        }

        if ($filters['category_id'] > 0) {
            $query->where('a.category_id', $filters['category_id']);
        }

        if ($filters['company_id'] > 0) {
            $query->where('a.company_id', $filters['company_id']);
        }

        $assets = $query->orderByRaw("FIELD(a.status, 'active', 'maintenance', 'repair', 'idle', 'liquidated', 'lost')")
            ->orderByDesc('a.id')
            ->paginate(30)
            ->withQueryString();

        $assetIds = collect($assets->items())->pluck('id')->all();
        $events = collect();
        $files = collect();

        if (count($assetIds)) {
            $events = DB::table('finance_asset_events as e')
                ->leftJoin('users as u', 'u.id', '=', 'e.created_by')
                ->whereIn('e.asset_id', $assetIds)
                ->select('e.*', 'u.name as creator_name')
                ->orderByDesc('e.event_date')
                ->orderByDesc('e.id')
                ->get()
                ->groupBy('asset_id');

            $files = DB::table('finance_asset_files')
                ->whereIn('asset_id', $assetIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy('asset_id');
        }

        foreach ($assets as $asset) {
            $metric = $this->assetMetric((object) $asset);
            $asset->monthly_depreciation = $metric['monthly_depreciation'];
            $asset->accumulated_depreciation = $metric['accumulated_depreciation'];
            $asset->book_value = $metric['book_value'];
            $asset->used_months = $metric['used_months'];
            $asset->remaining_months = $metric['remaining_months'];
            $asset->progress_percent = $metric['progress_percent'];
            $asset->events = $events->get($asset->id, collect());
            $asset->files = $files->get($asset->id, collect());
        }

        $allAssets = $this->assetQuery()->whereNull('deleted_at')->get();
        $summary = $this->summary($allAssets);

        $categories = DB::table('finance_asset_categories')
            ->orderBy('name')
            ->get();

        $companies = Schema::hasTable('companies')
            ? DB::table('companies')->where('id', EgoCompanyLock::id())->orderBy('name')->get(['id', 'name'])
            : collect();

        $users = Schema::hasTable('users')
            ? DB::table('users')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('finance.assets.index', [
            'assets' => $assets,
            'summary' => $summary,
            'filters' => $filters,
            'categories' => $categories,
            'companies' => $companies,
            'users' => $users,
            'statuses' => $this->statuses,
            'conditions' => $this->conditions,
            'eventTypes' => $this->eventTypes,
        ]);
    }

    /**
     * Tạo tài sản mới, ghi sự kiện mua và lưu tệp đính kèm.
     */
    public function store(Request $request)
    {
        $data = $this->validatedAsset($request);
        $data['company_id'] = EgoCompanyLock::id();
        $data['code'] = $data['code'] ?: $this->nextAssetCode();
        $data['status'] = $data['status'] ?: 'active';
        $data['condition'] = $data['condition'] ?: 'good';
        $data['created_by'] = auth()->id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $assetId = null;

        DB::transaction(function () use (&$assetId, $data, $request) {
            $assetId = $this->assetQuery()->insertGetId($data);

            $this->recordEvent($assetId, [
                'type' => 'purchase',
                'event_date' => $data['purchase_date'] ?: now()->toDateString(),
                'amount' => $data['original_cost'] ?? 0,
                'note' => 'Tạo mới tài sản',
                'to_user_id' => $data['assigned_to'] ?? null,
                'to_location' => $data['location'] ?? null,
            ]);

            $this->storeFiles($request, $assetId);
        });

        return redirect()
            ->route('finance.assets.index')
            ->with('success', 'Đã thêm tài sản '.$data['code'].'.');
    }

    /**
     * Cập nhật tài sản, ghi sự kiện điều chuyển khi đổi người giữ hoặc trạng thái.
     */
    public function update(Request $request, $id)
    {
        $asset = $this->assetQuery()->whereNull('deleted_at')->where('id', (int) $id)->first();
        abort_unless($asset, 404);

        $data = $this->validatedAsset($request, (int) $id);
        $data['company_id'] = EgoCompanyLock::id();
        $data['updated_at'] = now();

        DB::transaction(function () use ($asset, $data, $request) {
            $this->assetQuery()->where('id', $asset->id)->update($data);

            if ((string) $asset->status !== (string) $data['status'] || (string) $asset->assigned_to !== (string) ($data['assigned_to'] ?? '')) {
                $this->recordEvent($asset->id, [
                    'type' => (string) $asset->assigned_to !== (string) ($data['assigned_to'] ?? '') ? 'transfer' : 'note',
                    'event_date' => now()->toDateString(),
                    'amount' => null,
                    'note' => 'Cập nhật thông tin tài sản',
                    'from_user_id' => $asset->assigned_to,
                    'to_user_id' => $data['assigned_to'] ?? null,
                    'from_location' => $asset->location,
                    'to_location' => $data['location'] ?? null,
                ]);
            }

            $this->storeFiles($request, $asset->id);
        });

        return back()->with('success', 'Đã cập nhật tài sản.');
    }

    /**
     * Xóa mềm tài sản khỏi danh sách.
     */
    public function destroy($id)
    {
        $asset = $this->assetQuery()->whereNull('deleted_at')->where('id', (int) $id)->first();
        abort_unless($asset, 404);

        $this->assetQuery()->where('id', $asset->id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã xóa tài sản khỏi danh sách.');
    }

    /**
     * Tạo hoặc cập nhật nhóm tài sản theo mã.
     */
    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:30'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'color' => ['nullable', 'string', 'max:30'],
        ]);

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $code = $code ?: strtoupper(Str::slug($data['name'], '_'));

        DB::table('finance_asset_categories')->updateOrInsert(
            ['code' => $code],
            [
                'name' => $data['name'],
                'useful_life_months' => $data['useful_life_months'] ?: 36,
                'color' => $data['color'] ?: '#0ea5e9',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Đã lưu nhóm tài sản.');
    }

    /**
     * Ghi nhận sự kiện lịch sử tài sản và đồng bộ trạng thái, vị trí, người giữ.
     */
    public function storeEvent(Request $request, $assetId)
    {
        $asset = $this->assetQuery()->whereNull('deleted_at')->where('id', (int) $assetId)->first();
        abort_unless($asset, 404);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'event_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'string', 'max:50'],
            'from_location' => ['nullable', 'string', 'max:255'],
            'to_location' => ['nullable', 'string', 'max:255'],
            'from_user_id' => ['nullable', 'integer'],
            'to_user_id' => ['nullable', 'integer'],
            'condition' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'next_maintenance_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $data['amount'] = $this->money($data['amount'] ?? null);
        $data['event_date'] = $data['event_date'] ?: now()->toDateString();

        DB::transaction(function () use ($asset, $data) {
            $this->recordEvent($asset->id, $data);

            $update = ['updated_at' => now()];

            if (! empty($data['to_user_id'])) {
                $update['assigned_to'] = (int) $data['to_user_id'];
            }

            if (! empty($data['to_location'])) {
                $update['location'] = $data['to_location'];
            }

            if (! empty($data['condition']) && isset($this->conditions[$data['condition']])) {
                $update['condition'] = $data['condition'];
            }

            if (! empty($data['status']) && isset($this->statuses[$data['status']])) {
                $update['status'] = $data['status'];
            } elseif (in_array($data['type'], ['maintenance', 'repair', 'liquidation', 'lost'], true)) {
                $update['status'] = match ($data['type']) {
                    'maintenance' => 'maintenance',
                    'repair' => 'repair',
                    'liquidation' => 'liquidated',
                    'lost' => 'lost',
                    default => $asset->status,
                };
            }

            if (! empty($data['next_maintenance_date'])) {
                $update['next_maintenance_date'] = $data['next_maintenance_date'];
            }

            $this->assetQuery()->where('id', $asset->id)->update($update);
        });

        return back()->with('success', 'Đã ghi nhận lịch sử tài sản.');
    }

    /**
     * Xóa một dòng lịch sử tài sản.
     */
    public function destroyEvent($eventId)
    {
        DB::table('finance_asset_events')->where('id', (int) $eventId)->delete();

        return back()->with('success', 'Đã xóa dòng lịch sử.');
    }

    /**
     * Tải xuống tệp đính kèm của tài sản.
     */
    public function downloadFile($fileId)
    {
        $file = DB::table('finance_asset_files')->where('id', (int) $fileId)->first();
        abort_unless($file, 404);

        if (! Storage::disk('public')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk('public')->download($file->path, $file->original_name ?: basename($file->path));
    }

    /**
     * Xuất danh sách tài sản kèm khấu hao ra file CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = DB::table('finance_assets as a')
            ->where('a.company_id', EgoCompanyLock::id())
            ->leftJoin('finance_asset_categories as c', 'c.id', '=', 'a.category_id')
            ->whereNull('a.deleted_at')
            ->select('a.*', 'c.name as category_name')
            ->orderBy('a.code')
            ->get();

        $filename = 'tai-san-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'Ma tai san', 'Ten tai san', 'Nhom', 'Trang thai', 'Tinh trang', 'Ngay mua',
                'Nguyen gia', 'Khau hao luy ke', 'Gia tri con lai', 'Vi tri', 'Nha cung cap', 'Ghi chu',
            ]);

            foreach ($rows as $asset) {
                $metric = $this->assetMetric((object) $asset);

                fputcsv($out, [
                    $asset->code,
                    $asset->name,
                    $asset->category_name,
                    $this->statuses[$asset->status] ?? $asset->status,
                    $this->conditions[$asset->condition] ?? $asset->condition,
                    $asset->purchase_date,
                    $asset->original_cost,
                    $metric['accumulated_depreciation'],
                    $metric['book_value'],
                    $asset->location,
                    $asset->vendor,
                    $asset->note,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Validate và chuẩn hóa dữ liệu tài sản từ request (kiểm tra trùng mã).
     */
    private function validatedAsset(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'department' => ['nullable', 'string', 'max:120'],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'start_use_date' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date'],
            'next_maintenance_date' => ['nullable', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'invoice_no' => ['nullable', 'string', 'max:120'],
            'original_cost' => ['nullable', 'string', 'max:50'],
            'salvage_value' => ['nullable', 'string', 'max:50'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'depreciation_method' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'condition' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);

        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if ($code !== '') {
            $exists = $this->assetQuery()
                ->whereNull('deleted_at')
                ->where('code', $code)
                ->when($id, fn ($q) => $q->where('id', '!=', $id))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['code' => 'Mã tài sản đã tồn tại.']);
            }
        }

        return [
            'code' => $code,
            'name' => trim((string) $data['name']),
            'category_id' => ! empty($data['category_id']) ? (int) $data['category_id'] : null,
            'company_id' => ! empty($data['company_id']) ? (int) $data['company_id'] : null,
            'assigned_to' => ! empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'department' => trim((string) ($data['department'] ?? '')) ?: null,
            'serial_no' => trim((string) ($data['serial_no'] ?? '')) ?: null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'start_use_date' => $data['start_use_date'] ?? ($data['purchase_date'] ?? null),
            'warranty_until' => $data['warranty_until'] ?? null,
            'next_maintenance_date' => $data['next_maintenance_date'] ?? null,
            'vendor' => trim((string) ($data['vendor'] ?? '')) ?: null,
            'invoice_no' => trim((string) ($data['invoice_no'] ?? '')) ?: null,
            'original_cost' => $this->money($data['original_cost'] ?? 0),
            'salvage_value' => $this->money($data['salvage_value'] ?? 0),
            'useful_life_months' => (int) ($data['useful_life_months'] ?? 36) ?: 36,
            'depreciation_method' => $data['depreciation_method'] ?? 'straight_line',
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'status' => isset($this->statuses[$data['status'] ?? '']) ? $data['status'] : 'active',
            'condition' => isset($this->conditions[$data['condition'] ?? '']) ? $data['condition'] : 'good',
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
        ];
    }

    /**
     * Chuyển chuỗi tiền tệ nhập tay (dấu chấm / phẩy) về số float.
     */
    private function money($value): float
    {
        $value = trim((string) ($value ?? '0'));
        $value = str_replace([' ', 'đ', 'Đ'], '', $value);

        if ($value === '') {
            return 0;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.\-]/', '', $value);

        return round((float) ($value ?: 0), 2);
    }

    /**
     * Sinh mã tài sản kế tiếp dạng TS-YYYY-XXXX.
     */
    private function nextAssetCode(): string
    {
        $prefix = 'TS-'.now()->format('Y').'-';
        $last = $this->assetQuery()
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('code');

        $next = 1;

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Tính chỉ số khấu hao của tài sản: hàng tháng, lũy kế, giá trị còn lại, tiến độ.
     */
    private function assetMetric(object $asset): array
    {
        $cost = (float) ($asset->original_cost ?? 0);
        $salvage = max(0, (float) ($asset->salvage_value ?? 0));
        $life = max(1, (int) ($asset->useful_life_months ?? 36));
        $startDate = $asset->start_use_date ?: $asset->purchase_date;
        $status = (string) ($asset->status ?? 'active');

        $monthly = max(0, ($cost - $salvage) / $life);
        $usedMonths = 0;

        if ($startDate) {
            try {
                $start = Carbon::parse($startDate)->startOfMonth();
                $now = now()->startOfMonth();
                $usedMonths = max(0, $start->diffInMonths($now) + 1);
            } catch (\Throwable $e) {
                $usedMonths = 0;
            }
        }

        if (in_array($status, ['liquidated', 'lost'], true)) {
            $usedMonths = $life;
        }

        $usedMonths = min($life, $usedMonths);
        $accumulated = min(max(0, $cost - $salvage), round($monthly * $usedMonths, 2));
        $book = max($salvage, round($cost - $accumulated, 2));

        if ($cost <= 0) {
            $book = 0;
            $accumulated = 0;
        }

        return [
            'monthly_depreciation' => round($monthly, 2),
            'accumulated_depreciation' => round($accumulated, 2),
            'book_value' => round($book, 2),
            'used_months' => $usedMonths,
            'remaining_months' => max(0, $life - $usedMonths),
            'progress_percent' => $life > 0 ? min(100, round($usedMonths * 100 / $life, 1)) : 0,
        ];
    }

    /**
     * Tổng hợp số liệu toàn bộ tài sản: nguyên giá, khấu hao, cảnh báo bảo trì.
     */
    private function summary($assets): array
    {
        $totalCost = 0;
        $totalBook = 0;
        $monthly = 0;
        $warning = 0;

        foreach ($assets as $asset) {
            $metric = $this->assetMetric((object) $asset);
            $totalCost += (float) ($asset->original_cost ?? 0);
            $totalBook += $metric['book_value'];
            $monthly += $metric['monthly_depreciation'];

            if (! empty($asset->next_maintenance_date)) {
                try {
                    if (Carbon::parse($asset->next_maintenance_date)->lte(now()->addDays(30))) {
                        $warning++;
                    }
                } catch (\Throwable $e) {
                    // Ignore invalid legacy dates.
                }
            }
        }

        return [
            'count' => $assets->count(),
            'active' => $assets->where('status', 'active')->count(),
            'repair' => $assets->whereIn('status', ['repair', 'maintenance'])->count(),
            'total_cost' => round($totalCost, 2),
            'book_value' => round($totalBook, 2),
            'accumulated' => round(max(0, $totalCost - $totalBook), 2),
            'monthly_depreciation' => round($monthly, 2),
            'maintenance_warning' => $warning,
        ];
    }

    /**
     * Ghi một dòng sự kiện vào lịch sử tài sản.
     */
    private function recordEvent(int $assetId, array $data): void
    {
        DB::table('finance_asset_events')->insert([
            'asset_id' => $assetId,
            'type' => $data['type'] ?? 'note',
            'event_date' => $data['event_date'] ?? now()->toDateString(),
            'amount' => $data['amount'] ?? null,
            'from_location' => $data['from_location'] ?? null,
            'to_location' => $data['to_location'] ?? null,
            'from_user_id' => ! empty($data['from_user_id']) ? (int) $data['from_user_id'] : null,
            'to_user_id' => ! empty($data['to_user_id']) ? (int) $data['to_user_id'] : null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Lưu các tệp upload đính kèm cho tài sản.
     */
    private function storeFiles(Request $request, int $assetId): void
    {
        if (! $request->hasFile('files')) {
            return;
        }

        foreach ((array) $request->file('files') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('finance/assets/'.$assetId, 'public');

            DB::table('finance_asset_files')->insert([
                'asset_id' => $assetId,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Khởi tạo các nhóm tài sản mặc định nếu bảng còn trống.
     */
    private function ensureDefaultCategories(): void
    {
        if (! Schema::hasTable('finance_asset_categories')) {
            return;
        }

        if (DB::table('finance_asset_categories')->exists()) {
            return;
        }

        $now = now();
        DB::table('finance_asset_categories')->insert([
            ['code' => 'MAY_MOC', 'name' => 'Máy móc / Thiết bị', 'useful_life_months' => 60, 'color' => '#2563eb', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'VAN_PHONG', 'name' => 'Thiết bị văn phòng', 'useful_life_months' => 36, 'color' => '#0ea5e9', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CONG_CU', 'name' => 'Công cụ dụng cụ', 'useful_life_months' => 24, 'color' => '#10b981', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'XE_CO', 'name' => 'Xe / Phương tiện', 'useful_life_months' => 72, 'color' => '#f59e0b', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'KHAC', 'name' => 'Tài sản khác', 'useful_life_months' => 36, 'color' => '#64748b', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
