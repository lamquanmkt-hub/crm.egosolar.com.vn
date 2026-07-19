@php
    $debtTotal = max(
        0,
        (float) ($order->total_amount ?? 0)
    );

    $debtPaid = max(
        0,
        (float) ($paid ?? 0)
    );

    $debtRemain = max(
        0,
        (float) ($remain ?? ($debtTotal - $debtPaid))
    );

    $isDebtStatus =
        trim((string) ($statusText ?? '')) === 'Công nợ'
        && $debtRemain > 0;

    $debtPayments = collect(
        $order->payments ?? []
    )->sortByDesc(function ($payment) {
        return (string) (
            $payment->payment_date
            ?? $payment->created_at
            ?? ''
        );
    });

    $debtPaymentCount = $debtPayments->count();
    $debtLatestPayment = $debtPayments->first();

    $debtLatestDate = null;
    $debtLatestAmount = 0;

    if ($debtLatestPayment) {
        $debtLatestAmount = max(
            0,
            (float) ($debtLatestPayment->amount ?? 0)
        );

        $rawLatestDate =
            $debtLatestPayment->payment_date
            ?? $debtLatestPayment->created_at
            ?? null;

        if ($rawLatestDate) {
            try {
                $debtLatestDate = \Carbon\Carbon::parse(
                    $rawLatestDate
                )->format('d/m/Y');
            } catch (\Throwable $e) {
                $debtLatestDate = null;
            }
        }
    }

    $debtPaidPercent = $debtTotal > 0
        ? min(
            100,
            max(0, ($debtPaid / $debtTotal) * 100)
        )
        : 0;

    $debtRemainPercent = $debtTotal > 0
        ? min(
            100,
            max(0, ($debtRemain / $debtTotal) * 100)
        )
        : 0;
@endphp

@if($isDebtStatus)
    <span class="ego-debt-hover-wrap">
        <span
            class="badge-pill ego-debt-hover-trigger"
            style="
                background: rgba(0,0,0,.04);
                border-color: rgba(0,0,0,.06);
            "
            tabindex="0"
            role="button"
            aria-expanded="false"
            title="Rê chuột để xem số tiền còn nợ"
        >
            <span
                style="
                    width:10px;
                    height:10px;
                    border-radius:999px;
                    display:inline-block;
                    background: {{ $statusColor }};
                "
            ></span>

            {{ $statusText }}
        </span>

        <template class="ego-debt-hover-template">
            <div class="ego-debt-card">
                <div class="ego-debt-header">
                    <div>
                        <div class="ego-debt-title">
                            CÔNG NỢ ĐƠN HÀNG
                        </div>

                        <div class="ego-debt-code">
                            {{ $order->order_code }}
                        </div>
                    </div>

                    <div class="ego-debt-remain-head">
                        <span>Còn phải thu</span>

                        <strong>
                            {{ number_format(
                                $debtRemain,
                                0,
                                ',',
                                '.'
                            ) }} đ
                        </strong>
                    </div>
                </div>

                <div class="ego-debt-body">
                    <div class="ego-debt-row">
                        <span>Tổng giá trị đơn</span>

                        <b>
                            {{ number_format(
                                $debtTotal,
                                0,
                                ',',
                                '.'
                            ) }} đ
                        </b>
                    </div>

                    <div class="ego-debt-row ego-debt-paid">
                        <span>Đã thanh toán</span>

                        <b>
                            {{ number_format(
                                $debtPaid,
                                0,
                                ',',
                                '.'
                            ) }} đ
                        </b>
                    </div>

                    <div class="ego-debt-row ego-debt-remain">
                        <span>Còn công nợ</span>

                        <b>
                            {{ number_format(
                                $debtRemain,
                                0,
                                ',',
                                '.'
                            ) }} đ
                        </b>
                    </div>

                    <div class="ego-debt-progress-head">
                        <span>
                            Đã thu
                            {{ rtrim(
                                rtrim(
                                    number_format(
                                        $debtPaidPercent,
                                        1,
                                        ',',
                                        '.'
                                    ),
                                    '0'
                                ),
                                ','
                            ) }}%
                        </span>

                        <span>
                            Còn
                            {{ rtrim(
                                rtrim(
                                    number_format(
                                        $debtRemainPercent,
                                        1,
                                        ',',
                                        '.'
                                    ),
                                    '0'
                                ),
                                ','
                            ) }}%
                        </span>
                    </div>

                    <div class="ego-debt-progress">
                        <div
                            class="ego-debt-progress-paid"
                            style="width: {{ $debtPaidPercent }}%;"
                        ></div>
                    </div>

                    <div class="ego-debt-meta">
                        <div>
                            <span>Số lần thanh toán</span>

                            <b>{{ $debtPaymentCount }}</b>
                        </div>

                        @if($debtLatestPayment)
                            <div>
                                <span>Lần thu gần nhất</span>

                                <b>
                                    {{ number_format(
                                        $debtLatestAmount,
                                        0,
                                        ',',
                                        '.'
                                    ) }} đ

                                    @if($debtLatestDate)
                                        · {{ $debtLatestDate }}
                                    @endif
                                </b>
                            </div>
                        @else
                            <div>
                                <span>Tình trạng</span>
                                <b>Chưa ghi nhận thanh toán</b>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </template>
    </span>
@else
    <span
        class="badge-pill"
        style="
            background: rgba(0,0,0,.04);
            border-color: rgba(0,0,0,.06);
        "
    >
        <span
            style="
                width:10px;
                height:10px;
                border-radius:999px;
                display:inline-block;
                background: {{ $statusColor }};
            "
        ></span>

        {{ $statusText }}
    </span>
@endif
