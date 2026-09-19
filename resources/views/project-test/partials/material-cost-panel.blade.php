{{-- EGO_PROJECT_WAREHOUSE_360_V1_1_SAFE_COST_PANEL --}}
@php
    $pw360CostUser = auth()->user();
    $pw360CanViewCost = $pw360CostUser
        && $pw360CostUser->hasAnyRole(['admin', 'management', 'manager', 'warehouse', 'kho']);

    $pw360CostRows = collect();

    if ($pw360CanViewCost) {
        $pw360CostRows = $project->materialRequests
            ->flatMap(fn ($request) => $request->items ?? collect())
            ->flatMap(fn ($item) => $item->allocations ?? collect())
            ->filter()
            ->map(function ($allocation) {
                $quantity = (float) (
                    $allocation->issued_quantity
                    ?: $allocation->reserved_quantity
                    ?: $allocation->allocated_quantity
                    ?: 0
                );

                $unitCost = $allocation->unit_cost !== null
                    ? (float) $allocation->unit_cost
                    : null;

                return [
                    'name' => $allocation->product?->name
                        ?: $allocation->item?->item_name
                        ?: 'Sản phẩm chưa xác định',
                    'sku' => $allocation->product?->sku ?: '—',
                    'warehouse' => $allocation->warehouse?->name ?: '—',
                    'qty' => $quantity,
                    'unit' => $allocation->product?->unit
                        ?: $allocation->item?->unit
                        ?: 'cái',
                    'unit_cost' => $unitCost,
                    'total' => $unitCost === null ? null : $quantity * $unitCost,
                    'issued' => (float) ($allocation->issued_quantity ?: 0),
                ];
            })
            ->values();
    }

    $pw360KnownCosts = $pw360CostRows->whereNotNull('total');
    $pw360PlannedCost = (float) $pw360KnownCosts->sum('total');
    $pw360IssuedCost = (float) $pw360CostRows->sum(function ($row) {
        return $row['unit_cost'] === null
            ? 0
            : $row['issued'] * $row['unit_cost'];
    });
    $pw360MissingCost = $pw360CostRows->whereNull('unit_cost')->count();
@endphp

@if($pw360CanViewCost)
    <section class="ego-material-cost-panel" data-pw360-cost-panel>
        <div class="ego-material-cost-panel__head">
            <div>
                <h3><i class="bi bi-cash-stack"></i> Giá vốn vật tư công trình</h3>
                <p>Thông tin nội bộ chỉ dành cho Kho và Admin/Giám đốc.</p>
            </div>
            <span class="ego-material-cost-panel__badge">CHỈ KHO / ADMIN</span>
        </div>

        <div class="ego-material-cost-grid">
            <div>
                <small>Giá vốn tạm tính</small>
                <strong>{{ $pw360KnownCosts->isEmpty() ? 'Chưa có dữ liệu' : number_format($pw360PlannedCost, 0, ',', '.').' đ' }}</strong>
            </div>
            <div>
                <small>Giá vốn đã xuất</small>
                <strong>{{ $pw360IssuedCost > 0 ? number_format($pw360IssuedCost, 0, ',', '.').' đ' : 'Chưa xuất kho' }}</strong>
            </div>
            <div>
                <small>Dòng đã có giá vốn</small>
                <strong>{{ $pw360KnownCosts->count() }}/{{ $pw360CostRows->count() }}</strong>
            </div>
            <div>
                <small>Dòng thiếu giá vốn</small>
                <strong>{{ $pw360MissingCost > 0 ? $pw360MissingCost.' dòng' : 'Đã đủ dữ liệu' }}</strong>
            </div>
        </div>

        @if($pw360CostRows->isNotEmpty())
            <div class="ego-material-cost-table-wrap">
                <table class="ego-material-cost-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm thực tế</th>
                            <th>Kho</th>
                            <th>SL</th>
                            <th>Giá vốn/ĐVT</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pw360CostRows as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['name'] }}</strong><br>
                                    <small>{{ $row['sku'] }}</small>
                                </td>
                                <td>{{ $row['warehouse'] }}</td>
                                <td>{{ rtrim(rtrim(number_format($row['qty'], 3, '.', ''), '0'), '.') }} {{ $row['unit'] }}</td>
                                <td class="is-money">
                                    {{ $row['unit_cost'] === null ? 'Chưa có giá vốn' : number_format($row['unit_cost'], 0, ',', '.').' đ' }}
                                </td>
                                <td class="is-money">
                                    {{ $row['total'] === null ? '—' : number_format($row['total'], 0, ',', '.').' đ' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="pt-alert pt-alert--info" style="margin-top:12px">
                Kho chưa ghép sản phẩm thực tế nên chưa có dữ liệu giá vốn.
            </div>
        @endif
    </section>
@endif
