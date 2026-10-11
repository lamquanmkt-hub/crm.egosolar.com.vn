<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\Kpi\AssetInventories;
use App\Services\Hr\Kpi\HcnsSourceFiles;
use App\Services\Hr\Kpi\HcnsSourceReviews;
use App\Services\Hr\Kpi\Sources\AssetsServiceSource;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Đợt kiểm kê tài sản theo kỳ (KPI HC06, nhóm “kiểm kê”) trong HC & Vận hành → Tài sản công ty.
 * Người phụ trách ghi kết quả từng tài sản + biên bản → gửi kiểm; người kiểm (khác người phụ trách) xác nhận/trả bổ sung.
 */
class AssetInventoryController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(AssetInventories::ready(), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'period_month' => ['required', 'date_format:Y-m'],
            'scope' => ['required', Rule::in(array_keys(AssetInventories::SCOPES))],
            'handler_id' => ['required', 'integer', 'exists:users,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id', 'different:handler_id'],
            'due_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'reviewer_id.different' => 'Người kiểm phải khác người phụ trách.',
            'period_month.date_format' => 'Kỳ kiểm kê dạng tháng (YYYY-MM).',
        ]);
        if ($locked = \App\Services\Hr\Kpi\HcnsKpiRules::lockedMonth([$data['period_month']])) {
            return $this->backToTab()->withInput()->withErrors(['period_month' => AssetInventories::lockMessage($locked)]);
        }
        $assets = app(AssetInventories::class)->assetsFor($data['scope']);
        if ($assets === []) {
            return $this->backToTab()->withInput()->withErrors(['scope' => 'Danh mục '.AssetInventories::SCOPES[$data['scope']].' chưa có tài sản — không lập được đợt kiểm kê.']);
        }

        $id = DB::transaction(function () use ($data, $assets) {
            $id = DB::table('hr_asset_inventories')->insertGetId([
                'company_id' => EgoCompanyLock::id(), 'code' => AssetInventories::nextCode(), 'period_month' => $data['period_month'],
                'title' => trim($data['title']), 'scope' => $data['scope'], 'handler_id' => (int) $data['handler_id'],
                'reviewer_id' => (int) $data['reviewer_id'], 'due_at' => Carbon::parse($data['due_at']), 'status' => 'planned',
                'note' => $data['note'] ?? null, 'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($assets as $asset) {
                DB::table('hr_asset_inventory_items')->insert($asset + ['inventory_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            app(AssetInventories::class)->log($id, 'created', 'Lập đợt kiểm kê '.count($assets).' tài sản · hạn '.Carbon::parse($data['due_at'])->format('H:i d/m/Y'), (int) Auth::id());

            return $id;
        });

        return redirect()->route('hr.asset-inventories.show', $id)->with('success', 'Đã lập đợt kiểm kê. Người phụ trách ghi kết quả từng tài sản, tải biên bản rồi gửi kiểm.');
    }

    public function show($id)
    {
        abort_unless(AssetInventories::ready(), 404);
        $inv = DB::table('hr_asset_inventories')->where('id', (int) $id)->first();
        abort_unless($inv, 404);
        $user = Auth::user();
        $review = $this->review((int) $inv->id);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $row = app(AssetsServiceSource::class)->inventoryRow((object) ((array) $inv + [
            'review_status' => $review->status ?? null, 'result' => $review->result ?? null, 'checks' => $review->checks ?? null,
            'confirmed_by' => $review->confirmed_by ?? null, 'confirmed_at' => $review->confirmed_at ?? null,
            'return_reason' => $review->return_reason ?? null, 'first_submitted_at' => $review->first_submitted_at ?? null,
        ]), $names, now());

        return view('hr.operations.inventory-show', [
            'inv' => $inv,
            'items' => DB::table('hr_asset_inventory_items')->where('inventory_id', $inv->id)->orderBy('id')->get(),
            'review' => $review,
            'events' => $review ? app(HcnsSourceReviews::class)->events((int) $review->id) : collect(),
            'logs' => app(AssetInventories::class)->logs((int) $inv->id),
            'files' => app(HcnsSourceFiles::class)->list(AssetInventories::SOURCE_TYPE, (int) $inv->id),
            'missing' => app(AssetInventories::class)->missing($inv),
            'row' => $row,
            'names' => $names,
            'locked' => AssetInventories::lockedMonth($inv),
            'isHandler' => $user && (int) $inv->handler_id === (int) $user->id,
            'canEdit' => $user && $this->canEdit($inv, $user),
            'canReview' => $user && $this->canReview($inv, $user),
            'users' => DB::table('users')->select('id', 'name', 'email')->orderBy('name')->get(),
            'results' => (array) config('hcns_kpi.inventory_results', []),
            'checks' => (array) config('hcns_kpi.inventory_checks', []),
        ]);
    }

    /** Sửa kế hoạch: tên, người phụ trách/kiểm, hạn. Kỳ kiểm kê cố định sau khi lập. */
    public function update(Request $request, $id)
    {
        [$inv, $user] = $this->actor((int) $id, 'editor');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'handler_id' => ['required', 'integer', 'exists:users,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id', 'different:handler_id'],
            'due_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['reviewer_id.different' => 'Người kiểm phải khác người phụ trách.']);
        $values = ['title' => trim($data['title']), 'handler_id' => (int) $data['handler_id'], 'reviewer_id' => (int) $data['reviewer_id'],
            'due_at' => Carbon::parse($data['due_at'])->format('Y-m-d H:i:s'), 'note' => $data['note'] ?? null];
        $labels = ['title' => 'tên', 'handler_id' => 'người phụ trách', 'reviewer_id' => 'người kiểm', 'due_at' => 'hạn', 'note' => 'ghi chú'];
        $changes = [];
        foreach ($values as $field => $value) {
            if ((string) ($inv->{$field} ?? '') !== (string) ($value ?? '')) {
                $changes[$field] = $labels[$field].': '.((string) ($inv->{$field} ?? '') ?: '—').' → '.((string) $value ?: '—');
            }
        }
        if ($changes === []) {
            return back()->with('success', 'Không có thay đổi.');
        }
        DB::table('hr_asset_inventories')->where('id', $inv->id)->update($values + ['updated_at' => now()]);
        app(AssetInventories::class)->log((int) $inv->id, 'plan_updated', implode('; ', $changes), (int) $user->id);
        $this->invalidate((int) $inv->id, 'Sửa '.implode(', ', array_map(fn ($k) => $labels[$k], array_keys($changes))));

        return back()->with('success', 'Đã lưu kế hoạch. Nếu đợt đã gửi/kiểm tra mà đổi người/hạn, cần gửi lại.');
    }

    /** Người phụ trách ghi kết quả từng tài sản (lưu nháp phần kết quả). */
    public function saveItems(Request $request, $id)
    {
        [$inv, $user] = $this->actor((int) $id, 'handler');
        $data = $request->validate([
            'result' => ['array'],
            'result.*' => ['nullable', Rule::in(array_keys((array) config('hcns_kpi.inventory_results', [])))],
            'item_note' => ['array'],
            'item_note.*' => ['nullable', 'string', 'max:1000'],
        ]);
        $changed = 0;
        $items = DB::table('hr_asset_inventory_items')->where('inventory_id', $inv->id)->get()->keyBy('id');
        DB::transaction(function () use ($data, $items, $user, &$changed) {
            foreach ($items as $itemId => $item) {
                $result = $data['result'][$itemId] ?? $item->result;
                $note = array_key_exists($itemId, $data['item_note'] ?? []) ? (trim((string) $data['item_note'][$itemId]) ?: null) : $item->note;
                if ($result !== $item->result || $note !== $item->note) {
                    if (in_array($result, ['moved', 'damaged', 'missing'], true) && $note === null) {
                        throw ValidationException::withMessages(['item_note' => 'Ghi chú bắt buộc cho tài sản '.$item->asset_name.' (đổi vị trí/hỏng/không tìm thấy).']);
                    }
                    DB::table('hr_asset_inventory_items')->where('id', $itemId)->update([
                        'result' => $result ?: null, 'note' => $note, 'checked_at' => $result ? now() : null,
                        'checked_by' => $result ? $user->id : null, 'updated_at' => now(),
                    ]);
                    $changed++;
                }
            }
        });
        if ($changed === 0) {
            return back()->with('success', 'Không có thay đổi kết quả.');
        }
        DB::table('hr_asset_inventories')->where('id', $inv->id)->update([
            'started_at' => $inv->started_at ?? now(), 'status' => $inv->status === 'planned' ? 'in_progress' : $inv->status, 'updated_at' => now(),
        ]);
        app(AssetInventories::class)->log((int) $inv->id, 'items_saved', 'Ghi/sửa kết quả '.$changed.' tài sản', (int) $user->id);
        $this->invalidate((int) $inv->id, 'Sửa kết quả tài sản');

        return back()->with('success', 'Đã lưu kết quả '.$changed.' tài sản.');
    }

    public function fileUpload(Request $request, $id)
    {
        [$inv, $user] = $this->actor((int) $id, 'editor');
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:'.HcnsSourceFiles::MIMES, 'max:'.HcnsSourceFiles::MAX_KB],
        ], ['files.required' => 'Chọn ít nhất một tệp.']);
        $count = app(HcnsSourceFiles::class)->store(AssetInventories::SOURCE_TYPE, (int) $inv->id, (array) $request->file('files', []), (int) $user->id);
        app(AssetInventories::class)->log((int) $inv->id, 'file_added', 'Thêm '.$count.' tệp minh chứng', (int) $user->id);
        $this->invalidate((int) $inv->id, 'Thêm tệp minh chứng');

        return back()->with('success', 'Đã tải '.$count.' tệp minh chứng.');
    }

    public function file(Request $request, $id, $fileId)
    {
        abort_unless(AssetInventories::ready(), 404);
        abort_unless(DB::table('hr_asset_inventories')->where('id', (int) $id)->exists(), 404);
        $file = app(HcnsSourceFiles::class)->find(AssetInventories::SOURCE_TYPE, (int) $id, (int) $fileId);
        abort_unless($file, 404);

        return app(HcnsSourceFiles::class)->respond($file, $request->boolean('download'));
    }

    public function fileDelete($id, $fileId)
    {
        [$inv, $user] = $this->actor((int) $id, 'editor');
        $file = app(HcnsSourceFiles::class)->find(AssetInventories::SOURCE_TYPE, (int) $inv->id, (int) $fileId);
        abort_unless($file, 404);
        app(HcnsSourceFiles::class)->delete($file);
        app(AssetInventories::class)->log((int) $inv->id, 'file_deleted', 'Xóa tệp '.$file->original_name, (int) $user->id);
        $this->invalidate((int) $inv->id, 'Xóa tệp minh chứng');

        return back()->with('success', 'Đã xóa tệp minh chứng.');
    }

    public function kpiDraft(Request $request, $id)
    {
        [$inv, $user] = $this->actor((int) $id, 'handler');
        $svc = app(AssetInventories::class);
        app(HcnsSourceReviews::class)->saveDraft(AssetInventories::SOURCE_TYPE, (int) $inv->id, AssetsServiceSource::CODE,
            (int) $inv->handler_id, (int) $inv->reviewer_id, $svc->declared($inv), $svc->fingerprint($inv), (int) $user->id, $this->note($request));

        return back()->with('success', 'Đã lưu nháp. Chưa gửi người kiểm, chưa tính KPI.');
    }

    /** Gửi kiểm = báo hoàn tất kiểm kê. Mốc hoàn tất là lần gửi gần nhất (lần được xác nhận mới tính). */
    public function kpiSubmit(Request $request, $id)
    {
        [$inv, $user] = $this->actor((int) $id, 'handler');
        $svc = app(AssetInventories::class);
        if (($missing = $svc->missing($inv)) !== []) {
            return back()->with('error', 'Chưa gửi kiểm được — thiếu: '.implode(', ', $missing).'.');
        }
        DB::transaction(function () use ($inv, $user, $svc, $request) {
            DB::table('hr_asset_inventories')->where('id', $inv->id)->update([
                'status' => 'completed', 'completed_at' => now(), 'started_at' => $inv->started_at ?? now(), 'updated_at' => now(),
            ]);
            $fresh = DB::table('hr_asset_inventories')->where('id', $inv->id)->first();
            app(HcnsSourceReviews::class)->submit(AssetInventories::SOURCE_TYPE, (int) $inv->id, AssetsServiceSource::CODE,
                (int) $inv->handler_id, (int) $inv->reviewer_id, $svc->declared($fresh), $svc->fingerprint($fresh), (int) $user->id, $this->note($request));
            $svc->log((int) $inv->id, 'completed', 'Báo hoàn tất kiểm kê và gửi kiểm tra', (int) $user->id);
        });

        return back()->with('success', 'Đã báo hoàn tất và gửi kiểm tra. Chờ người kiểm xác nhận.');
    }

    public function kpiConfirm(Request $request, $id)
    {
        [$inv, $user] = $this->actor((int) $id, 'reviewer');
        $checkKeys = array_keys((array) config('hcns_kpi.inventory_checks', []));
        $data = $request->validate([
            'checks' => ['required', 'array'],
            'checks.*' => ['required', Rule::in(['pass', 'fail'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['checks.required' => 'Chọn Đạt/Không đạt cho từng mục kiểm tra.']);
        $checks = array_intersect_key((array) $data['checks'], array_flip($checkKeys));
        if (count($checks) !== count($checkKeys)) {
            throw ValidationException::withMessages(['checks' => 'Chọn Đạt/Không đạt cho đủ '.count($checkKeys).' mục kiểm tra.']);
        }
        if (collect($checks)->contains('fail') && trim((string) ($data['note'] ?? '')) === '') {
            throw ValidationException::withMessages(['note' => 'Ghi rõ lỗi khi có mục không đạt.']);
        }
        $review = $this->review((int) $inv->id);
        abort_unless($review, 404);
        app(HcnsSourceReviews::class)->confirm($review, $checks, app(AssetInventories::class)->fingerprint($inv), (int) $user->id, $data['note'] ?? null);

        return back()->with('success', 'Đã xác nhận kết quả kiểm tra.');
    }

    public function kpiReturn(Request $request, $id)
    {
        [$inv, $user] = $this->actor((int) $id, 'reviewer');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']], ['reason.required' => 'Ghi lý do trả bổ sung.']);
        $review = $this->review((int) $inv->id);
        abort_unless($review, 404);
        DB::transaction(function () use ($review, $data, $user, $inv) {
            app(HcnsSourceReviews::class)->returnForFix($review, trim($data['reason']), (int) $user->id);
            DB::table('hr_asset_inventories')->where('id', $inv->id)->update(['status' => 'in_progress', 'updated_at' => now()]);
        });

        return back()->with('success', 'Đã trả bổ sung cho người phụ trách.');
    }

    /** Hủy đợt khi chưa gửi kiểm (có lý do). Hủy sau hạn khi chưa hoàn tất vẫn tính không đạt. */
    public function cancel(Request $request, $id)
    {
        [$inv, $user] = $this->actor((int) $id, 'editor');
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:5', 'max:2000']], ['cancel_reason.required' => 'Ghi lý do hủy.']);
        $review = $this->review((int) $inv->id);
        if ($review && in_array($review->status, ['submitted', 'confirmed'], true)) {
            return back()->with('error', 'Đợt đã gửi kiểm/đã kiểm tra — không hủy được. Người kiểm trả bổ sung nếu cần sửa.');
        }
        DB::table('hr_asset_inventories')->where('id', $inv->id)->update([
            'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancel_reason' => trim($data['cancel_reason']), 'updated_at' => now(),
        ]);
        app(AssetInventories::class)->log((int) $inv->id, 'cancelled', trim($data['cancel_reason']), (int) $user->id);

        return back()->with('success', 'Đã hủy đợt kiểm kê.');
    }

    /** @return array{0: object, 1: \App\Models\User} */
    private function actor(int $id, string $role): array
    {
        abort_unless(AssetInventories::ready(), 404);
        $inv = DB::table('hr_asset_inventories')->where('id', $id)->first();
        abort_unless($inv, 404);
        if ($locked = AssetInventories::lockedMonth($inv)) {
            throw ValidationException::withMessages(['kpi' => AssetInventories::lockMessage($locked)]);
        }
        if ($inv->status === 'cancelled') {
            throw ValidationException::withMessages(['kpi' => 'Đợt kiểm kê đã hủy — chỉ xem.']);
        }
        $user = Auth::user();
        match ($role) {
            'handler' => abort_unless((int) $inv->handler_id === (int) $user->id, 403, 'Chỉ người phụ trách đợt kiểm kê được ghi kết quả/gửi kiểm.'),
            'editor' => abort_unless($this->canEdit($inv, $user), 403, 'Chỉ người phụ trách hoặc Ban Giám đốc/Admin được sửa đợt kiểm kê.'),
            'reviewer' => abort_unless($this->canReview($inv, $user), 403, 'Bạn không phải người kiểm của đợt này.'),
        };

        return [$inv, $user];
    }

    private function canEdit(object $inv, $user): bool
    {
        return (int) $inv->handler_id === (int) $user->id || PayrollAccess::canApprove($user);
    }

    private function canReview(object $inv, $user): bool
    {
        if ((int) $inv->handler_id === (int) $user->id) {
            return false;
        }

        return (int) ($inv->reviewer_id ?? 0) === (int) $user->id || PayrollAccess::canApprove($user);
    }

    private function review(int $id): ?object
    {
        return app(HcnsSourceReviews::class)->find(AssetInventories::SOURCE_TYPE, $id, AssetsServiceSource::CODE);
    }

    private function note(Request $request): ?string
    {
        $note = trim((string) $request->input('note', ''));

        return $note === '' ? null : mb_substr($note, 0, 2000);
    }

    private function invalidate(int $id, string $what): void
    {
        $inv = DB::table('hr_asset_inventories')->where('id', $id)->first();
        $before = $this->review($id);
        $after = app(HcnsSourceReviews::class)->invalidateIfChanged(AssetInventories::SOURCE_TYPE, $id, AssetsServiceSource::CODE,
            app(AssetInventories::class)->fingerprint($inv), (int) Auth::id(), $what);
        if ($before && $after && $before->status !== $after->status) {
            app(AssetInventories::class)->log($id, 'review_invalidated', $what.' sau khi đã gửi/kiểm tra — cần gửi lại.', (int) Auth::id());
            if ($inv->status === 'completed') {
                DB::table('hr_asset_inventories')->where('id', $id)->update(['status' => 'in_progress', 'updated_at' => now()]);
            }
        }
    }

    private function backToTab()
    {
        return redirect()->route('hr.operations.index', ['tab' => 'assets']);
    }
}
