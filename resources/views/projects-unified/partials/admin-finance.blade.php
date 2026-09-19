@php
    $financeData = $finance ?? [];
    $grossMargin = (float) ($financeData['profit_margin'] ?? 0);
    $isProfitable = (float) ($financeData['profit'] ?? 0) >= 0;
    $paymentMethodLabels = ['bank_transfer' => 'Chuyển khoản', 'cash' => 'Tiền mặt', 'card' => 'Thẻ', 'other' => 'Khác'];
    $expenseCategoryLabels = ['supplier' => 'Nhà cung cấp', 'service' => 'Dịch vụ', 'travel' => 'Đi lại', 'rental' => 'Thuê ngoài', 'other' => 'Khác'];
    $financeWritable = (bool) ($canWriteFinance ?? false);
@endphp

<section class="pword-panel epf-panel" data-finance-root>
    <header class="epf-heading"><div><small>TÀI CHÍNH CÔNG TRÌNH</small><h2>Tài chính công trình</h2><p>Tổng hợp hợp đồng, thực thu, công nợ và chi phí công trình.</p></div><span class="epf-private"><i class="bi {{ $financeWritable ? 'bi-shield-check' : 'bi-eye' }}"></i> {{ $financeWritable ? 'Có quyền cập nhật' : 'Chỉ xem' }}</span></header>

    <div class="epf-kpis">
        <article class="epf-kpi"><span>Doanh thu / Hợp đồng</span><strong>{{ $money($financeData['contract'] ?? 0) }}</strong>@if($financeWritable)<button type="button" data-finance-open="finance-edit"><i class="bi bi-pencil"></i> Cập nhật</button>@endif</article>
        <article class="epf-kpi received"><span>Đã thanh toán</span><strong>{{ $money($financeData['received'] ?? 0) }}</strong><small>{{ $paymentRecords->count() }} giao dịch</small></article>
        <article class="epf-kpi debt"><span>Còn phải thu</span><strong>{{ $money($financeData['debt'] ?? 0) }}</strong>@if($financeWritable)<button type="button" data-finance-open="payment-create"><i class="bi bi-plus-circle"></i> Ghi nhận</button>@endif</article>
        <article class="epf-kpi expense"><span>Tổng chi phí</span><strong>{{ $money($financeData['cost'] ?? 0) }}</strong><small>Kho {{ $money($financeData['warehouse_cost'] ?? 0) }}</small></article>
        <article class="epf-kpi {{ $isProfitable ? 'profit' : 'loss' }}"><span>{{ $isProfitable ? 'Lợi nhuận dự kiến' : 'Lỗ dự kiến' }}</span><strong>{{ $money($financeData['profit'] ?? 0) }}</strong><small>Biên {{ number_format($grossMargin, 1, ',', '.') }}%</small></article>
    </div>

    @if(($financeData['received'] ?? 0) > ($financeData['contract'] ?? 0))
        <div class="epf-warning"><i class="bi bi-exclamation-triangle"></i> Thanh toán đang vượt giá trị hợp đồng {{ $money(($financeData['received'] ?? 0) - ($financeData['contract'] ?? 0)) }}. Admin nên kiểm tra lại lịch sử giao dịch.</div>
    @endif

    <div class="epf-compact-grid">
        <section class="epf-card" id="project-payments"><header><div><h3>Thanh toán gần đây</h3><p>Theo dõi thực thu và công nợ.</p></div>@if($financeWritable)<button class="pword-btn primary" type="button" data-finance-open="payment-create"><i class="bi bi-plus-lg"></i> Ghi nhận</button>@endif</header>
            <div class="epf-list">
                @forelse($paymentRecords->take(5) as $payment)
                    @php $paymentSource = ($payment->finance_source ?? '') === 'legacy_receipt' ? 'receipt' : 'record'; @endphp
                    <article><div class="epf-list-date">{{ !empty($payment->paid_at) ? \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d/m/Y') : '—' }}</div><div class="epf-list-copy"><strong>{{ ($payment->payer_name ?? '') ?: 'Khách hàng' }}</strong><small>{{ $paymentMethodLabels[$payment->payment_method ?? ''] ?? ($payment->payment_method ?? '—') }}{{ !empty($payment->transaction_reference) ? ' · '.$payment->transaction_reference : '' }}</small></div><strong class="epf-list-money">{{ $money($payment->amount ?? 0) }}</strong>@if($financeWritable)<button type="button" class="epf-icon-btn" title="Sửa thanh toán" data-finance-edit-payment data-source="{{ $paymentSource }}" data-id="{{ $payment->id }}" data-amount="{{ (float) ($payment->amount ?? 0) }}" data-date="{{ $payment->paid_at ?? '' }}" data-method="{{ $payment->payment_method ?? 'bank_transfer' }}" data-account="{{ $payment->account_id ?? '' }}" data-reference="{{ $payment->transaction_reference ?? '' }}" data-payer="{{ $payment->payer_name ?? '' }}" data-note="{{ $payment->note ?? '' }}"><i class="bi bi-pencil-square"></i></button>@endif</article>
                @empty
                    <div class="epf-mini-empty">Chưa có khoản thanh toán.</div>
                @endforelse
            </div>
            @if($paymentRecords->count() > 5)<details class="epf-more"><summary>Xem tất cả {{ $paymentRecords->count() }} giao dịch</summary><div class="epf-table-wrap"><table><thead><tr><th>Ngày</th><th>Người thanh toán</th><th>Phương thức</th><th>Số tiền</th><th></th></tr></thead><tbody>@foreach($paymentRecords->skip(5) as $payment)@php $paymentSource = ($payment->finance_source ?? '') === 'legacy_receipt' ? 'receipt' : 'record'; @endphp<tr><td>{{ \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d/m/Y') }}</td><td>{{ $payment->payer_name ?: '—' }}</td><td>{{ $paymentMethodLabels[$payment->payment_method] ?? $payment->payment_method }}</td><td>{{ $money($payment->amount) }}</td><td>@if($financeWritable)<button type="button" class="epf-icon-btn" data-finance-edit-payment data-source="{{ $paymentSource }}" data-id="{{ $payment->id }}" data-amount="{{ (float)$payment->amount }}" data-date="{{ $payment->paid_at }}" data-method="{{ $payment->payment_method }}" data-account="{{ $payment->account_id ?? '' }}" data-reference="{{ $payment->transaction_reference ?? '' }}" data-payer="{{ $payment->payer_name ?? '' }}" data-note="{{ $payment->note ?? '' }}"><i class="bi bi-pencil-square"></i></button>@endif</td></tr>@endforeach</tbody></table></div></details>@endif
        </section>

        <section class="epf-card" id="project-expenses"><header><div><h3>Chi phí công trình</h3><p>Các khoản ngoài giá vốn kho.</p></div>@if($financeWritable)<button class="pword-btn light" type="button" data-finance-open="expense-create"><i class="bi bi-plus-lg"></i> Thêm chi phí</button>@endif</header>
            <div class="epf-cost-strip"><span>Nhân công <b>{{ $money($financeData['labor_cost'] ?? 0) }}</b></span><span>Vận chuyển <b>{{ $money($financeData['transport_cost'] ?? 0) }}</b></span><span>Chi phí khác <b>{{ $money($financeData['other_cost'] ?? 0) }}</b></span></div>
            <div class="epf-list">
                @forelse($financeExpenses->take(5) as $expense)
                    <article><div class="epf-list-date">{{ \Illuminate\Support\Carbon::parse($expense->expense_date)->format('d/m/Y') }}</div><div class="epf-list-copy"><strong>{{ $expense->description }}</strong><small>{{ $expenseCategoryLabels[$expense->category] ?? $expense->category }}{{ $expense->payee ? ' · '.$expense->payee : '' }}</small></div><strong class="epf-list-money expense">{{ $money($expense->amount) }}</strong>@if($financeWritable)<button type="button" class="epf-icon-btn" title="Sửa chi phí" data-finance-edit-expense data-id="{{ $expense->id }}" data-date="{{ $expense->expense_date }}" data-category="{{ $expense->category }}" data-amount="{{ (float)$expense->amount }}" data-payee="{{ $expense->payee ?? '' }}" data-description="{{ $expense->description }}" data-note="{{ $expense->note ?? '' }}"><i class="bi bi-pencil-square"></i></button>@endif</article>
                @empty
                    <div class="epf-mini-empty">Chưa có chi phí khác.</div>
                @endforelse
            </div>
            @if($financeExpenses->count() > 5)<details class="epf-more"><summary>Xem tất cả {{ $financeExpenses->count() }} khoản chi</summary><div class="epf-table-wrap"><table><thead><tr><th>Ngày</th><th>Nội dung</th><th>Nhóm</th><th>Số tiền</th><th></th></tr></thead><tbody>@foreach($financeExpenses->skip(5) as $expense)<tr><td>{{ \Illuminate\Support\Carbon::parse($expense->expense_date)->format('d/m/Y') }}</td><td>{{ $expense->description }}</td><td>{{ $expenseCategoryLabels[$expense->category] ?? $expense->category }}</td><td>{{ $money($expense->amount) }}</td><td>@if($financeWritable)<button type="button" class="epf-icon-btn" data-finance-edit-expense data-id="{{ $expense->id }}" data-date="{{ $expense->expense_date }}" data-category="{{ $expense->category }}" data-amount="{{ (float)$expense->amount }}" data-payee="{{ $expense->payee ?? '' }}" data-description="{{ $expense->description }}" data-note="{{ $expense->note ?? '' }}"><i class="bi bi-pencil-square"></i></button>@endif</td></tr>@endforeach</tbody></table></div></details>@endif
        </section>
    </div>

    <details class="epf-dispatch"><summary><span><i class="bi bi-box-seam"></i><b>Giá vốn từ phiếu xuất kho</b><small>{{ $exportedMaterialRequests->count() }} phiếu · {{ $money($financeData['warehouse_cost'] ?? 0) }}</small></span><i class="bi bi-chevron-down"></i></summary><div class="epf-dispatch-body">@if($exportedMaterialRequests->isNotEmpty())<div class="epf-table-wrap"><table><thead><tr><th>Phiếu</th><th>Kho</th><th>Ngày xuất</th><th>Ghi chú</th><th>Giá vốn</th></tr></thead><tbody>@foreach($exportedMaterialRequests as $dispatch)<tr><td>VT-{{ str_pad((string)$dispatch->id,5,'0',STR_PAD_LEFT) }}</td><td>{{ $dispatch->warehouse_name ?? '—' }}</td><td>{{ !empty($dispatch->updated_at) ? \Illuminate\Support\Carbon::parse($dispatch->updated_at)->format('d/m/Y') : '—' }}</td><td>{{ ($dispatch->note ?? '') ?: 'Xuất vật tư công trình' }}</td><td>{{ $money($dispatch->total_cost ?? 0) }}</td></tr>@endforeach</tbody></table></div>@else<div class="epf-mini-empty">Chưa có phiếu xuất kho.</div>@endif</div></details>

    @if($financeWritable)
        @include('projects-unified.partials.admin-finance-modals')
    @endif
</section>
