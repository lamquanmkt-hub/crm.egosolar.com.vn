@php
    $paymentMobile = (bool) ($mobile ?? false);

    $paymentTotal = max(
        0,
        (float) ($order->total_amount ?? 0)
    );

    $paymentPaid = max(
        0,
        (float) (
            $paid
            ?? collect(
                $order->payments ?? []
            )->sum('amount')
        )
    );

    $paymentRemain = max(
        0,
        (float) (
            $remain
            ?? ($paymentTotal - $paymentPaid)
        )
    );

    $paymentOver = max(
        0,
        $paymentPaid - $paymentTotal
    );

    $paymentPercent = $paymentTotal > 0
        ? min(
            100,
            max(
                0,
                ($paymentPaid / $paymentTotal) * 100
            )
        )
        : 0;

    $paymentRows = collect(
        $order->payments ?? []
    )->sortByDesc(function ($payment) {
        return (string) (
            $payment->payment_date
            ?? $payment->created_at
            ?? ''
        );
    });

    $paymentCount = $paymentRows->count();
    $latestPayment = $paymentRows->first();

    $latestPaymentDate = null;
    $latestPaymentAmount = 0;

    if ($latestPayment) {
        $latestPaymentAmount = max(
            0,
            (float) (
                $latestPayment->amount ?? 0
            )
        );

        $rawLatestPaymentDate =
            $latestPayment->payment_date
            ?? $latestPayment->created_at
            ?? null;

        if ($rawLatestPaymentDate) {
            try {
                $latestPaymentDate =
                    \Carbon\Carbon::parse(
                        $rawLatestPaymentDate
                    )->format('d/m/Y');
            } catch (\Throwable $e) {
                $latestPaymentDate = null;
            }
        }
    }

    if ($paymentTotal <= 0) {
        $paymentStateText =
            'Chưa có giá trị';

        $paymentStateClass =
            'is-unpaid';
    } elseif ($paymentPaid <= 0) {
        $paymentStateText =
            'Chưa thanh toán';

        $paymentStateClass =
            'is-unpaid';
    } elseif ($paymentRemain <= 0) {
        $paymentStateText =
            $paymentOver > 0.5
                ? 'Thanh toán vượt'
                : 'Đã thanh toán đủ';

        $paymentStateClass =
            'is-paid';
    } else {
        $paymentStateText =
            'Thanh toán một phần';

        $paymentStateClass =
            'is-partial';
    }
@endphp

<span class="ego-debt-hover-wrap ego-payment-hover-wrap">
    <button
        type="button"
        class="
            ego-debt-hover-trigger
            ego-payment-hover-trigger
            {{ $paymentMobile ? 'is-mobile' : '' }}
        "
        aria-expanded="false"
        aria-label="
            Xem chi tiết thanh toán đơn
            {{ $order->order_code }}
        "
        title="
            Rê chuột hoặc bấm để xem chi tiết thanh toán
        "
    >
        <span
            class="
                {{ $paymentMobile
                    ? 'm-amount'
                    : 'amount'
                }}
            "
        >
            {{ number_format(
                $paymentTotal,
                0,
                ',',
                '.'
            ) }} đ
        </span>

        <span
            class="ego-payment-info-icon"
            aria-hidden="true"
        >
            <i class="bi bi-info"></i>
        </span>
    </button>

    <template class="ego-debt-hover-template">
        <div
            class="
                ego-debt-card
                ego-payment-card
                {{ $paymentStateClass }}
            "
        >
            <div class="ego-debt-header">
                <div>
                    <div class="ego-debt-title">
                        CHI TIẾT THANH TOÁN
                    </div>

                    <div class="ego-debt-code">
                        {{ $order->order_code }}
                    </div>
                </div>

                <span class="ego-payment-state-badge">
                    {{ $paymentStateText }}
                </span>
            </div>

            <div class="ego-debt-body">
                <div class="ego-debt-row">
                    <span>Tổng giá trị đơn</span>

                    <b>
                        {{ number_format(
                            $paymentTotal,
                            0,
                            ',',
                            '.'
                        ) }} đ
                    </b>
                </div>

                <div
                    class="
                        ego-debt-row
                        ego-debt-paid
                    "
                >
                    <span>Đã thanh toán</span>

                    <b>
                        {{ number_format(
                            $paymentPaid,
                            0,
                            ',',
                            '.'
                        ) }} đ
                    </b>
                </div>

                <div
                    class="
                        ego-debt-row
                        ego-debt-remain
                    "
                >
                    <span>Còn công nợ</span>

                    <b>
                        {{ number_format(
                            $paymentRemain,
                            0,
                            ',',
                            '.'
                        ) }} đ
                    </b>
                </div>

                @if($paymentOver > 0.5)
                    <div
                        class="
                            ego-debt-row
                            ego-payment-overpaid
                        "
                    >
                        <span>Thanh toán vượt</span>

                        <b>
                            {{ number_format(
                                $paymentOver,
                                0,
                                ',',
                                '.'
                            ) }} đ
                        </b>
                    </div>
                @endif

                <div class="ego-debt-progress-head">
                    <span>Tiến độ thanh toán</span>

                    <span>
                        {{ rtrim(
                            rtrim(
                                number_format(
                                    $paymentPercent,
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
                        style="
                            width:
                            {{ $paymentPercent }}%;
                        "
                    ></div>
                </div>

                <div class="ego-debt-meta">
                    <div>
                        <span>Số lần thanh toán</span>

                        <b>
                            {{ $paymentCount }}
                        </b>
                    </div>

                    @if($latestPayment)
                        <div>
                            <span>Lần thu gần nhất</span>

                            <b>
                                {{ number_format(
                                    $latestPaymentAmount,
                                    0,
                                    ',',
                                    '.'
                                ) }} đ

                                @if($latestPaymentDate)
                                    · {{ $latestPaymentDate }}
                                @endif
                            </b>
                        </div>
                    @else
                        <div>
                            <span>Tình trạng</span>

                            <b>
                                Chưa ghi nhận thanh toán
                            </b>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </template>
</span>
