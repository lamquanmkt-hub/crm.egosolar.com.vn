<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MarketingPlanController extends Controller
{
    private function planTable(): string
    {
        if (Schema::hasTable('mkt_plans')) {
            return 'mkt_plans';
        }

        return 'marketing_plans';
    }

    private function hasCol(string $table, string $col): bool
    {
        try {
            return Schema::hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function filterCols(string $table, array $data): array
    {
        if (!Schema::hasTable($table)) {
            return $data;
        }

        return collect($data)->only(Schema::getColumnListing($table))->toArray();
    }

    private function money($value): float
    {
        $value = trim((string) $value);
        $value = str_replace([' ', ',', 'đ', 'Đ', 'vnd', 'VND', 'vnđ', 'VNĐ'], '', $value);
        return is_numeric($value) ? (float) $value : 0;
    }

    public function index(Request $request)
    {
        $table = $this->planTable();

        $query = DB::table($table);

        if ($request->filled('q')) {
            $q = trim((string) $request->q);

            $query->where(function ($sub) use ($q, $table) {
                if ($this->hasCol($table, 'name')) {
                    $sub->orWhere('name', 'like', "%{$q}%");
                }
                if ($this->hasCol($table, 'note')) {
                    $sub->orWhere('note', 'like', "%{$q}%");
                }
                if ($this->hasCol($table, 'objective')) {
                    $sub->orWhere('objective', 'like', "%{$q}%");
                }
            });
        }

        if ($request->filled('status') && $this->hasCol($table, 'status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('month')) {
            try {
                $monthDate = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()->toDateString();

                if ($this->hasCol($table, 'month')) {
                    $query->where('month', $monthDate);
                } elseif ($this->hasCol($table, 'start_date')) {
                    $query->whereDate('start_date', '<=', Carbon::parse($monthDate)->endOfMonth()->toDateString())
                          ->whereDate('end_date', '>=', $monthDate);
                }
            } catch (\Throwable $e) {
                //
            }
        }

        if ($this->hasCol($table, 'month')) {
            $query->orderByDesc('month')->orderByDesc('id');
        } elseif ($this->hasCol($table, 'start_date')) {
            $query->orderByDesc('start_date')->orderByDesc('id');
        } else {
            $query->orderByDesc('id');
        }

        $plans = $query->paginate(12)->withQueryString();

        $attachmentCounts = Schema::hasTable('mkt_plan_attachments')
            ? DB::table('mkt_plan_attachments')
                ->selectRaw('plan_id, COUNT(*) as total')
                ->groupBy('plan_id')
                ->pluck('total', 'plan_id')
            : collect();

        return view('marketing.plans.index', compact('plans', 'attachmentCounts'));
    }

    public function create()
    {
        return view('marketing.plans.create');
    }

    public function store(Request $request)
    {
        $table = $this->planTable();

        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'target_leads' => ['nullable', 'integer', 'min:0'],
            'target_roas' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:51200'],
        ]);

        $monthDate = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()->toDateString();

        $data = [
            'name' => $request->name ?: ('Kế hoạch Marketing ' . Carbon::parse($monthDate)->format('m/Y')),
            'month' => $monthDate,
            'start_date' => $monthDate,
            'end_date' => Carbon::parse($monthDate)->endOfMonth()->toDateString(),
            'status' => $request->status ?: 'active',
            'total_budget' => $this->money($request->total_budget),
            'budget_plan_total' => $this->money($request->total_budget),
            'target_leads' => (int) $request->input('target_leads', 0),
            'target_roas' => (float) $request->input('target_roas', 0),
            'target_revenue' => 0,
            'target_cr' => 0,
            'objective' => $request->note,
            'note' => $request->note,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table($table)->insertGetId($this->filterCols($table, $data));

        $this->storeAttachments($request, $id);

        return redirect()
            ->route('marketing.plan.show', $id)
            ->with('success', 'Đã tạo kế hoạch mới.');
    }

    public function show(int $id)
    {
        $table = $this->planTable();

        $plan = DB::table($table)->where('id', $id)->first();
        abort_if(!$plan, 404);

        $attachments = $this->attachments($id);

        return view('marketing.plans.show', compact('plan', 'attachments'));
    }

    public function edit(int $id)
    {
        $table = $this->planTable();

        $plan = DB::table($table)->where('id', $id)->first();
        abort_if(!$plan, 404);

        $attachments = $this->attachments($id);

        return view('marketing.plans.edit', compact('plan', 'attachments'));
    }

    public function update(Request $request, int $id)
    {
        $table = $this->planTable();

        $plan = DB::table($table)->where('id', $id)->first();
        abort_if(!$plan, 404);

        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'target_leads' => ['nullable', 'integer', 'min:0'],
            'target_roas' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:51200'],
            'delete_files' => ['nullable', 'array'],
        ]);

        $monthDate = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()->toDateString();

        $data = [
            'name' => $request->name ?: ('Kế hoạch Marketing ' . Carbon::parse($monthDate)->format('m/Y')),
            'month' => $monthDate,
            'start_date' => $monthDate,
            'end_date' => Carbon::parse($monthDate)->endOfMonth()->toDateString(),
            'status' => $request->status ?: 'active',
            'total_budget' => $this->money($request->total_budget),
            'budget_plan_total' => $this->money($request->total_budget),
            'target_leads' => (int) $request->input('target_leads', 0),
            'target_roas' => (float) $request->input('target_roas', 0),
            'objective' => $request->note,
            'note' => $request->note,
            'updated_at' => now(),
        ];

        DB::table($table)->where('id', $id)->update($this->filterCols($table, $data));

        $this->deleteAttachments($request->input('delete_files', []), $id);
        $this->storeAttachments($request, $id);

        return redirect()
            ->route('marketing.plan.show', $id)
            ->with('success', 'Đã cập nhật kế hoạch.');
    }

    public function destroy(int $id)
    {
        $table = $this->planTable();

        foreach ($this->attachments($id) as $file) {
            Storage::disk('public')->delete($file->file_path);
        }

        if (Schema::hasTable('mkt_plan_attachments')) {
            DB::table('mkt_plan_attachments')->where('plan_id', $id)->delete();
        }

        DB::table($table)->where('id', $id)->delete();

        return redirect()
            ->route('marketing.plan.overview')
            ->with('success', 'Đã xoá kế hoạch.');
    }

    public function approve(int $id)
    {
        $table = $this->planTable();

        if ($this->hasCol($table, 'status')) {
            DB::table($table)->where('id', $id)->update([
                'status' => 'approved',
                'updated_at' => now(),
            ]);
        }

        return redirect()
            ->route('marketing.plan.show', $id)
            ->with('success', 'Đã duyệt kế hoạch.');
    }

    public function file(int $file)
    {
        abort_unless(Schema::hasTable('mkt_plan_attachments'), 404);

        $att = DB::table('mkt_plan_attachments')->where('id', $file)->first();
        abort_if(!$att, 404);

        $path = storage_path('app/public/' . $att->file_path);
        abort_unless(is_file($path), 404);

        $mime = $att->file_mime ?: 'application/octet-stream';
        $name = $att->file_name ?: basename($path);

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
        ]);
    }

    private function attachments(int $planId)
    {
        if (!Schema::hasTable('mkt_plan_attachments')) {
            return collect();
        }

        return DB::table('mkt_plan_attachments')
            ->where('plan_id', $planId)
            ->orderByDesc('id')
            ->get();
    }

    private function storeAttachments(Request $request, int $planId): void
    {
        if (!$request->hasFile('attachments') || !Schema::hasTable('mkt_plan_attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            if (!$file) {
                continue;
            }

            $path = $file->store('marketing_plan_attachments/' . $planId, 'public');

            DB::table('mkt_plan_attachments')->insert([
                'plan_id' => $planId,
                'uploaded_by' => auth()->id(),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_mime' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function deleteAttachments(array $ids, int $planId): void
    {
        if (empty($ids) || !Schema::hasTable('mkt_plan_attachments')) {
            return;
        }

        $files = DB::table('mkt_plan_attachments')
            ->where('plan_id', $planId)
            ->whereIn('id', $ids)
            ->get();

        foreach ($files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }

        DB::table('mkt_plan_attachments')
            ->where('plan_id', $planId)
            ->whereIn('id', $ids)
            ->delete();
    }
}