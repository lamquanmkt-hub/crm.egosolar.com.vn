<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Gifts;

use App\Http\Controllers\Controller;
use App\Models\Hr\Gift;
use App\Support\EgoCompanyLock;
use App\Support\GiftAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GiftReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeReport($request);
        [$from, $to] = $this->dates($request);

        return view('hr.gifts.reports.index', [
            'rows' => $this->rows($from, $to, $request),
            'from' => $from,
            'to' => $to,
            'canSeeCost' => GiftAccess::canSeeCost($request->user()),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReport($request);
        [$from, $to] = $this->dates($request);
        $rows = $this->rows($from, $to, $request);
        $filename = 'bao-cao-ton-qua-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['SKU', 'Tên quà', 'Loại quà', 'ĐVT', 'Tồn đầu', 'Nhập', 'Xuất', 'Tồn cuối', 'Tồn tối thiểu', 'Cảnh báo']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['gift']->sku,
                    $row['gift']->name,
                    $row['gift']->gift_type,
                    $row['gift']->unit,
                    $row['opening'],
                    $row['incoming'],
                    $row['outgoing'],
                    $row['closing'],
                    $row['gift']->minimum_stock,
                    $row['is_low'] ? 'Dưới định mức' : 'Bình thường',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function rows(CarbonImmutable $from, CarbonImmutable $to, Request $request): Collection
    {
        $companyId = EgoCompanyLock::id();
        $start = $from->startOfDay();
        $end = $to->endOfDay();

        $movementRows = DB::table('hr_gift_stock_movements')
            ->select('gift_id')
            ->selectRaw('SUM(CASE WHEN occurred_at < ? THEN quantity ELSE 0 END) AS opening_qty', [$start])
            ->selectRaw('SUM(CASE WHEN occurred_at BETWEEN ? AND ? AND quantity > 0 THEN quantity ELSE 0 END) AS incoming_qty', [$start, $end])
            ->selectRaw('SUM(CASE WHEN occurred_at BETWEEN ? AND ? AND quantity < 0 THEN ABS(quantity) ELSE 0 END) AS outgoing_qty', [$start, $end])
            ->where('company_id', $companyId)
            ->groupBy('gift_id')
            ->get()
            ->keyBy('gift_id');

        $gifts = Gift::query()
            ->where('company_id', $companyId)
            ->when($search = trim((string) $request->query('q')), function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('gift_type', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->get();

        $rows = $gifts->map(function (Gift $gift) use ($movementRows): array {
            $movement = $movementRows->get($gift->id);
            $opening = round((float) ($movement->opening_qty ?? 0), 3);
            $incoming = round((float) ($movement->incoming_qty ?? 0), 3);
            $outgoing = round((float) ($movement->outgoing_qty ?? 0), 3);
            $closing = round($opening + $incoming - $outgoing, 3);

            return [
                'gift' => $gift,
                'opening' => $opening,
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'closing' => $closing,
                'is_low' => $closing < (float) $gift->minimum_stock,
            ];
        });

        return $request->query('low_stock') === '1'
            ? $rows->filter(fn (array $row): bool => $row['is_low'])->values()
            : $rows;
    }

    private function dates(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : CarbonImmutable::now()->startOfMonth();
        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : CarbonImmutable::now();

        return [$from, $to];
    }

    private function authorizeReport(Request $request): void
    {
        abort_unless(GiftAccess::canHandleStock($request->user()), 403);
    }
}
