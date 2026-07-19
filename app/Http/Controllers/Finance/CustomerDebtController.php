<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CRM\Orders\Order;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomerDebtController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query()
            ->with(['lead.customer'])
            ->latest();

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->where(function ($q) {
                    $q->where('payment_recorded', 1)
                      ->orWhere('total_amount', '<=', 0);
                });
            }

            if ($request->payment_status === 'unpaid') {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('payment_recorded')
                            ->orWhere('payment_recorded', 0);
                    })->where('total_amount', '>', 0);
                });
            }
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('order_code', 'like', '%' . $keyword . '%')
                  ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                  ->orWhereHas('lead.customer', function ($sub) use ($keyword) {
                      $sub->where('name', 'like', '%' . $keyword . '%');
                  });
            });
        }

        $orders = $query->get();

        $grouped = $orders
            ->groupBy(function ($order) {
                $customer = optional(optional($order->lead)->customer);

                if (!empty($customer->id)) {
                    return 'customer_' . $customer->id;
                }

                return 'name_' . md5($this->resolveCustomerName($order));
            })
            ->map(function (Collection $items, $groupKey) {
                $first = $items->first();

                $total = 0;
                $paid = 0;
                $debt = 0;

                $details = $items->map(function ($order) use (&$total, &$paid, &$debt) {
                    $money = $this->mapMoney($order);

                    $total += $money['total'];
                    $paid += $money['paid'];
                    $debt += $money['debt'];

                    return (object) [
                        'id' => $order->id,
                        'order_code' => $order->order_code ?? ('#' . $order->id),
                        'total_amount' => $money['total'],
                        'paid_amount' => $money['paid'],
                        'debt_amount' => $money['debt'],
                        'payment_recorded' => (int) ($order->payment_recorded ?? 0),
                        'created_at' => $order->created_at,
                    ];
                })->values();

                return (object) [
                    'group_key' => $groupKey,
                    'customer_name' => $this->resolveCustomerName($first),
                    'total_orders' => $items->count(),
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'debt_amount' => $debt,
                    'orders' => $details,
                ];
            })
            ->sortByDesc('debt_amount')
            ->values();

        $fullSummary = [
            'total_customers' => $grouped->count(),
            'total_amount' => (float) $grouped->sum('total_amount'),
            'paid_amount' => (float) $grouped->sum('paid_amount'),
            'debt_amount' => (float) $grouped->sum('debt_amount'),
        ];

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $grouped->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $customers = new LengthAwarePaginator(
            $currentItems,
            $grouped->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('finance.debt-customers', compact('customers', 'fullSummary'));
    }

    public function byCustomer(Request $request)
    {
        $query = Order::query()
            ->with(['lead.customer'])
            ->latest();

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->where(function ($q) {
                    $q->where('payment_recorded', 1)
                      ->orWhere('total_amount', '<=', 0);
                });
            }

            if ($request->payment_status === 'unpaid') {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('payment_recorded')
                            ->orWhere('payment_recorded', 0);
                    })->where('total_amount', '>', 0);
                });
            }
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('order_code', 'like', '%' . $keyword . '%')
                  ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                  ->orWhereHas('lead.customer', function ($sub) use ($keyword) {
                      $sub->where('name', 'like', '%' . $keyword . '%');
                  });
            });
        }

        $orders = $query->get();

        $grouped = $orders
            ->groupBy(function ($order) {
                $customer = optional(optional($order->lead)->customer);

                if (!empty($customer->id)) {
                    return 'customer_' . $customer->id;
                }

                return 'name_' . md5($this->resolveCustomerName($order));
            })
            ->map(function (Collection $items) {
                $first = $items->first();

                $total = 0;
                $paid = 0;
                $debt = 0;

                foreach ($items as $order) {
                    $money = $this->mapMoney($order);
                    $total += $money['total'];
                    $paid += $money['paid'];
                    $debt += $money['debt'];
                }

                return (object) [
                    'customer_name' => $this->resolveCustomerName($first),
                    'total_orders'  => $items->count(),
                    'total_amount'  => $total,
                    'paid_amount'   => $paid,
                    'debt_amount'   => $debt,
                ];
            })
            ->sortByDesc('debt_amount')
            ->values();

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $grouped->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $debts = new LengthAwarePaginator(
            $currentItems,
            $grouped->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('finance.debt-customers-by-user', compact('debts'));
    }

    public function paymentHistory(Request $request)
    {
        $query = Order::query()
            ->with(['lead.customer'])
            ->latest();

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->where(function ($q) {
                    $q->where('payment_recorded', 1)
                      ->orWhere('total_amount', '<=', 0);
                });
            }

            if ($request->payment_status === 'unpaid') {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('payment_recorded')
                            ->orWhere('payment_recorded', 0);
                    })->where('total_amount', '>', 0);
                });
            }
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('order_code', 'like', '%' . $keyword . '%')
                  ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                  ->orWhereHas('lead.customer', function ($sub) use ($keyword) {
                      $sub->where('name', 'like', '%' . $keyword . '%');
                  });
            });
        }

        $orders = $query->paginate(20)->appends($request->query());

        $orders->getCollection()->transform(function ($order) {
            $money = $this->mapMoney($order);

            $order->finance_customer_name = $this->resolveCustomerName($order);
            $order->finance_total_amount = $money['total'];
            $order->finance_paid_amount = $money['paid'];
            $order->finance_debt_amount = $money['debt'];
            $order->finance_payment_status = $money['debt'] > 0 ? 'Công nợ' : 'Đã hoàn thành';

            return $order;
        });

        return view('finance.payment-history', compact('orders'));
    }

    private function resolveCustomerName($order): string
    {
        $customer = optional(optional($order->lead)->customer);

        if (!empty($customer->name)) {
            return $customer->name;
        }

        if (!empty($order->receiver_name)) {
            return $order->receiver_name;
        }

        return 'Khách lẻ / Chưa xác định';
    }

        private function mapMoney($order): array
    {
        $total = (float) ($order->total_amount ?? 0);

        if ($total <= 0) {
            return [
                'total' => 0.0,
                'paid' => 0.0,
                'debt' => 0.0,
            ];
        }

        $orderId = (int) ($order->id ?? 0);

        $paidFromPayments = 0.0;
        $paidFromDebtTable = 0.0;

        try {
            if ($orderId > 0 && \Illuminate\Support\Facades\Schema::hasTable('crm_payments')) {
                $paidFromPayments = (float) \Illuminate\Support\Facades\DB::table('crm_payments')
                    ->where('order_id', $orderId)
                    ->sum('amount');
            }
        } catch (\Throwable $e) {
            $paidFromPayments = 0.0;
        }

        try {
            if ($orderId > 0 && \Illuminate\Support\Facades\Schema::hasTable('crm_customer_debts')) {
                $paidFromDebtTable = (float) \Illuminate\Support\Facades\DB::table('crm_customer_debts')
                    ->where('order_id', $orderId)
                    ->max('paid_amount');
            }
        } catch (\Throwable $e) {
            $paidFromDebtTable = 0.0;
        }

        $paid = max($paidFromPayments, $paidFromDebtTable);

        if ($paid <= 0 && (int) ($order->payment_recorded ?? 0) === 1) {
            $paid = $total;
        }

        $paid = min(max($paid, 0.0), $total);
        $debt = max($total - $paid, 0.0);

        return [
            'total' => $total,
            'paid' => $paid,
            'debt' => $debt,
        ];
    }
}