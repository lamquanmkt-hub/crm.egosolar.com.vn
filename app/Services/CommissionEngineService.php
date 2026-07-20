<?php

namespace App\Services;

use App\Contracts\Services\CommissionEngineServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service tính hoa hồng: quản lý chính sách, quy tắc và tính hoa hồng theo đơn hàng.
 */
class CommissionEngineService implements CommissionEngineServiceInterface
{
    /**
     * Tạo các bảng chính sách/quy tắc/dòng hoa hồng nếu chưa tồn tại.
     */
    public function ensureSchema(): void
    {
        if (! Schema::hasTable('crm_commission_policies')) {
            Schema::create('crm_commission_policies', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('period_month', 7)->index();
                $table->string('status')->default('active');
                $table->decimal('project_rate_percent', 8, 4)->default(4);
                $table->decimal('trade_rate_percent', 8, 4)->default(1);
                $table->decimal('panel_fixed_amount', 15, 2)->default(15000);
                $table->boolean('only_paid')->default(true);
                $table->boolean('only_shipped')->default(false);
                $table->boolean('only_completed')->default(false);
                $table->boolean('hold_if_debt')->default(true);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_commission_rules')) {
            Schema::create('crm_commission_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('policy_id')->index();
                $table->string('period_month', 7)->index();
                $table->string('commission_type')->index(); // project, trade_product, solar_panel
                $table->string('target_type')->default('all'); // all, product, category, brand, keyword
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('target_text')->nullable();
                $table->string('base_type')->default('revenue_before_vat');
                $table->string('calculation_type')->default('percent'); // percent, fixed_per_item, fixed_per_kwp, fixed_per_order
                $table->decimal('rate_percent', 8, 4)->default(0);
                $table->decimal('fixed_amount', 15, 2)->default(0);
                $table->decimal('amount_per_unit', 15, 2)->default(0);
                $table->decimal('amount_per_kwp', 15, 2)->default(0);
                $table->decimal('from_amount', 15, 2)->nullable();
                $table->decimal('to_amount', 15, 2)->nullable();
                $table->decimal('from_qty', 15, 2)->nullable();
                $table->decimal('to_qty', 15, 2)->nullable();
                $table->integer('priority')->default(10);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_commission_lines')) {
            Schema::create('crm_commission_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('policy_id')->nullable()->index();
                $table->unsignedBigInteger('rule_id')->nullable()->index();
                $table->string('period_month', 7)->index();
                $table->string('source_type')->default('order');
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->unsignedBigInteger('sales_id')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name')->nullable();
                $table->decimal('quantity', 15, 4)->default(0);
                $table->decimal('kwp', 15, 4)->default(0);
                $table->decimal('revenue_before_vat', 15, 2)->default(0);
                $table->decimal('revenue_after_vat', 15, 2)->default(0);
                $table->decimal('fifo_cost', 15, 2)->default(0);
                $table->decimal('gross_profit', 15, 2)->default(0);
                $table->string('commission_type')->nullable();
                $table->string('calculation_type')->nullable();
                $table->decimal('commission_base', 15, 2)->default(0);
                $table->decimal('commission_rate', 8, 4)->default(0);
                $table->decimal('commission_amount', 15, 2)->default(0);
                $table->string('status')->default('draft');
                $table->text('reason')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Tạo bảng lương cứng và bậc KPI của sales nếu chưa tồn tại.
     */
    private function ensureSalesCompensationSchema(): void
    {
        $this->ensureSchema();

        if (! Schema::hasTable('crm_sales_salary_settings')) {
            Schema::create('crm_sales_salary_settings', function (Blueprint $table) {
                $table->id();
                $table->string('period_month', 7)->index();
                $table->unsignedBigInteger('sales_id')->index();
                $table->decimal('base_salary', 15, 2)->default(0);
                $table->decimal('target_revenue', 15, 2)->default(0);
                $table->decimal('target_commission', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['period_month', 'sales_id'], 'salary_period_sales_unique');
            });
        }

        if (! Schema::hasTable('crm_sales_kpi_tiers')) {
            Schema::create('crm_sales_kpi_tiers', function (Blueprint $table) {
                $table->id();
                $table->string('period_month', 7)->index();
                $table->unsignedBigInteger('sales_id')->nullable()->index();
                $table->string('tier_name')->nullable();
                $table->decimal('from_revenue', 15, 2)->default(0);
                $table->decimal('to_revenue', 15, 2)->nullable();
                $table->string('bonus_type')->default('fixed'); // fixed, percent_revenue, percent_commission, salary_percent
                $table->decimal('bonus_amount', 15, 2)->default(0);
                $table->integer('priority')->default(10);
                $table->boolean('is_active')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Lấy danh sách user thuộc nhóm sales, hỗ trợ nhiều dạng schema role khác nhau.
     */
    private function salesUserRows(): Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        $cols = Schema::getColumnListing('users');

        $select = ['u.id'];

        if (in_array('name', $cols, true)) {
            $select[] = 'u.name';
        } else {
            $select[] = DB::raw("'' as name");
        }

        if (in_array('email', $cols, true)) {
            $select[] = 'u.email';
        } else {
            $select[] = DB::raw("'' as email");
        }

        $q = DB::table('users as u')
            ->select($select)
            ->distinct();

        if (in_array('is_active', $cols, true)) {
            $q->where('u.is_active', 1);
        }

        if (in_array('deleted_at', $cols, true)) {
            $q->whereNull('u.deleted_at');
        }

        $hasRoleFilter = false;

        $q->where(function ($roleQ) use ($cols, &$hasRoleFilter) {
            $saleLike = function ($query, string $column) {
                $query->orWhereRaw("LOWER($column) LIKE ?", ['%sale%'])
                    ->orWhereRaw("LOWER($column) LIKE ?", ['%sales%'])
                    ->orWhereRaw("LOWER($column) LIKE ?", ['%kinh%'])
                    ->orWhereRaw("LOWER($column) LIKE ?", ['%business%']);
            };

            // Case 1: role nằm trực tiếp trong bảng users: users.role / users.type / users.position...
            foreach (['role', 'roles', 'user_role', 'type', 'position', 'department'] as $col) {
                if (in_array($col, $cols, true)) {
                    $hasRoleFilter = true;
                    $saleLike($roleQ, "u.`$col`");
                }
            }

            // Case 2: users.role_id -> roles.id
            if (in_array('role_id', $cols, true) && Schema::hasTable('roles')) {
                $hasRoleFilter = true;

                $roleQ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('roles as r')
                        ->whereColumn('r.id', 'u.role_id')
                        ->where(function ($r) {
                            $r->whereRaw('LOWER(r.name) LIKE ?', ['%sale%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%sales%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%kinh%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%business%']);

                            if (Schema::hasColumn('roles', 'code')) {
                                $r->orWhereRaw('LOWER(r.code) LIKE ?', ['%sale%'])
                                    ->orWhereRaw('LOWER(r.code) LIKE ?', ['%sales%']);
                            }
                        });
                });
            }

            // Case 3: Spatie Permission: model_has_roles + roles
            if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
                $hasRoleFilter = true;

                $roleQ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('model_has_roles as mhr')
                        ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                        ->whereColumn('mhr.model_id', 'u.id')
                        ->where(function ($m) {
                            $m->whereNull('mhr.model_type')
                                ->orWhereRaw('mhr.model_type LIKE ?', ['%User%']);
                        })
                        ->where(function ($r) {
                            $r->whereRaw('LOWER(r.name) LIKE ?', ['%sale%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%sales%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%kinh%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%business%']);

                            if (Schema::hasColumn('roles', 'code')) {
                                $r->orWhereRaw('LOWER(r.code) LIKE ?', ['%sale%'])
                                    ->orWhereRaw('LOWER(r.code) LIKE ?', ['%sales%']);
                            }
                        });
                });
            }

            // Case 4: pivot role_user
            if (Schema::hasTable('role_user') && Schema::hasTable('roles')) {
                $hasRoleFilter = true;

                $roleQ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('role_user as ru')
                        ->join('roles as r', 'r.id', '=', 'ru.role_id')
                        ->whereColumn('ru.user_id', 'u.id')
                        ->where(function ($r) {
                            $r->whereRaw('LOWER(r.name) LIKE ?', ['%sale%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%sales%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%kinh%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%business%']);
                        });
                });
            }

            // Case 5: pivot user_roles
            if (Schema::hasTable('user_roles') && Schema::hasTable('roles')) {
                $hasRoleFilter = true;

                $roleQ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('user_roles as ur')
                        ->join('roles as r', 'r.id', '=', 'ur.role_id')
                        ->whereColumn('ur.user_id', 'u.id')
                        ->where(function ($r) {
                            $r->whereRaw('LOWER(r.name) LIKE ?', ['%sale%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%sales%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%kinh%'])
                                ->orWhereRaw('LOWER(r.name) LIKE ?', ['%business%']);
                        });
                });
            }
        });

        // Nếu hệ thống không có schema role rõ ràng thì không trả toàn bộ user để tránh sai.
        if (! $hasRoleFilter) {
            return collect();
        }

        return $q->orderBy('u.name')->limit(300)->get();
    }

    /**
     * Lấy thiết lập lương theo tháng cho từng sales, tự sinh giá trị mặc định nếu chưa có.
     */
    private function salarySettingsForMonth(string $month, Collection $salesUsers): Collection
    {
        $this->ensureSalesCompensationSchema();

        $existing = DB::table('crm_sales_salary_settings')
            ->where('period_month', $month)
            ->get()
            ->keyBy('sales_id');

        return $salesUsers->mapWithKeys(function ($u) use ($existing, $month) {
            $row = $existing->get($u->id);

            if ($row) {
                return [(int) $u->id => $row];
            }

            return [(int) $u->id => (object) [
                'period_month' => $month,
                'sales_id' => (int) $u->id,
                'base_salary' => 7000000,
                'target_revenue' => 500000000,
                'target_commission' => 0,
                'is_active' => 1,
                'note' => '',
            ]];
        });
    }

    /**
     * Lưu thiết lập lương và các bậc KPI của sales cho tháng chỉ định.
     */
    private function saveCompensationSettings(Request $request, string $month): void
    {
        $this->ensureSalesCompensationSchema();

        $salaryRows = (array) $request->input('salary_settings', []);

        foreach ($salaryRows as $salesId => $row) {
            if (! is_array($row)) {
                continue;
            }

            $salesId = (int) ($row['sales_id'] ?? $salesId);
            if ($salesId <= 0) {
                continue;
            }

            DB::table('crm_sales_salary_settings')->updateOrInsert(
                [
                    'period_month' => $month,
                    'sales_id' => $salesId,
                ],
                [
                    'base_salary' => (float) ($row['base_salary'] ?? 0),
                    'target_revenue' => (float) ($row['target_revenue'] ?? 0),
                    'target_commission' => (float) ($row['target_commission'] ?? 0),
                    'is_active' => ! empty($row['is_active']) ? 1 : 0,
                    'note' => $row['note'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('crm_sales_kpi_tiers')
            ->where('period_month', $month)
            ->delete();

        $tiers = (array) $request->input('kpi_tiers', []);

        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $name = trim((string) ($tier['tier_name'] ?? ''));
            $fromRevenue = (float) ($tier['from_revenue'] ?? 0);
            $bonusAmount = (float) ($tier['bonus_amount'] ?? 0);

            if ($name === '' && $fromRevenue <= 0 && $bonusAmount <= 0) {
                continue;
            }

            DB::table('crm_sales_kpi_tiers')->insert([
                'period_month' => $month,
                'sales_id' => ! empty($tier['sales_id']) ? (int) $tier['sales_id'] : null,
                'tier_name' => $name ?: 'Bậc KPI',
                'from_revenue' => $fromRevenue,
                'to_revenue' => ($tier['to_revenue'] ?? '') !== '' ? (float) $tier['to_revenue'] : null,
                'bonus_type' => $tier['bonus_type'] ?? 'fixed',
                'bonus_amount' => $bonusAmount,
                'priority' => (int) ($tier['priority'] ?? 10),
                'is_active' => ! empty($tier['is_active']) ? 1 : 0,
                'note' => $tier['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Lấy chính sách hoa hồng của tháng, tự tạo chính sách mặc định nếu chưa có.
     */
    public function currentPolicy(string $month): object
    {
        $this->ensureSchema();

        $policy = DB::table('crm_commission_policies')
            ->where('period_month', $month)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();

        if ($policy) {
            $this->ensureCustomerStatusDefaultRules($policy);

            return $policy;
        }

        $id = DB::table('crm_commission_policies')->insertGetId([
            'name' => 'Chính sách hoa hồng '.$month,
            'period_month' => $month,
            'status' => 'active',
            'project_rate_percent' => 4,
            'trade_rate_percent' => 1,
            'panel_fixed_amount' => 15000,
            'only_paid' => 1,
            'only_shipped' => 0,
            'only_completed' => 0,
            'hold_if_debt' => 1,
            'is_active' => 1,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policy = DB::table('crm_commission_policies')->where('id', $id)->first();
        $this->seedDefaultRules($policy);
        $this->ensureCustomerStatusDefaultRules($policy);

        return DB::table('crm_commission_policies')->where('id', $id)->first();
    }

    /**
     * Sao chép chính sách và quy tắc hoa hồng từ tháng trước sang tháng chỉ định.
     */
    public function copyPreviousMonth(string $month): void
    {
        $this->ensureSchema();

        $target = $this->currentPolicy($month);
        $prevMonth = Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m');

        $prev = DB::table('crm_commission_policies')
            ->where('period_month', $prevMonth)
            ->orderByDesc('id')
            ->first();

        if (! $prev) {
            return;
        }

        DB::table('crm_commission_policies')
            ->where('id', $target->id)
            ->update([
                'project_rate_percent' => $prev->project_rate_percent,
                'trade_rate_percent' => $prev->trade_rate_percent,
                'panel_fixed_amount' => $prev->panel_fixed_amount,
                'only_paid' => $prev->only_paid,
                'only_shipped' => $prev->only_shipped,
                'only_completed' => $prev->only_completed,
                'hold_if_debt' => $prev->hold_if_debt,
                'note' => 'Đã sao chép từ tháng '.$prevMonth,
                'updated_at' => now(),
            ]);

        DB::table('crm_commission_rules')->where('policy_id', $target->id)->delete();

        $rules = DB::table('crm_commission_rules')
            ->where('policy_id', $prev->id)
            ->get();

        foreach ($rules as $rule) {
            $row = (array) $rule;
            unset($row['id']);
            $row['policy_id'] = $target->id;
            $row['period_month'] = $month;
            $row['created_at'] = now();
            $row['updated_at'] = now();
            DB::table('crm_commission_rules')->insert($row);
        }
    }

    /**
     * Bổ sung quy tắc mặc định cho khách lead/ADS nếu chính sách chưa có.
     */
    private function ensureCustomerStatusDefaultRules(object $policy): void
    {
        if (! Schema::hasTable('crm_commission_rules')) {
            return;
        }

        $exists = DB::table('crm_commission_rules')
            ->where('policy_id', (int) $policy->id)
            ->where('target_type', 'customer_status')
            ->where('target_text', 'lead')
            ->exists();

        if ($exists) {
            return;
        }

        $rows = [
            [
                'policy_id' => (int) $policy->id,
                'period_month' => $policy->period_month,
                'commission_type' => 'trade_product',
                'target_type' => 'customer_status',
                'target_id' => null,
                'target_text' => 'lead',
                'base_type' => 'revenue_before_vat',
                'calculation_type' => 'percent',
                'rate_percent' => 0.5,
                'fixed_amount' => 0,
                'amount_per_unit' => 0,
                'amount_per_kwp' => 0,
                'priority' => 90,
                'is_active' => 1,
                'note' => 'Lead / Khách ADS: hoa hồng sản phẩm 0.5%',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'policy_id' => (int) $policy->id,
                'period_month' => $policy->period_month,
                'commission_type' => 'project',
                'target_type' => 'customer_status',
                'target_id' => null,
                'target_text' => 'lead',
                'base_type' => 'revenue_before_vat',
                'calculation_type' => 'percent',
                'rate_percent' => 3,
                'fixed_amount' => 0,
                'amount_per_unit' => 0,
                'amount_per_kwp' => 0,
                'priority' => 95,
                'is_active' => 1,
                'note' => 'Lead / Khách ADS: hoa hồng công trình 3%',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('crm_commission_rules')->insert($rows);
    }

    /**
     * Chuẩn hóa giá trị trạng thái khách hàng về mã thống nhất (lead/member/retail...).
     */
    private function normalizeCustomerStatusValue($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $ascii = mb_strtolower(\Illuminate\Support\Str::ascii($value));

        if (str_contains($ascii, 'lead') || str_contains($ascii, 'ads')) {
            return 'lead';
        }

        if (str_contains($ascii, 'member') || str_contains($ascii, 'tu phat trien')) {
            return 'member';
        }

        if (str_contains($ascii, 'khach le') || str_contains($ascii, 'retail')) {
            return 'retail';
        }

        return $ascii;
    }

    /**
     * Xác định ngữ cảnh tính hoa hồng của đơn: trạng thái khách và có phải đơn công trình.
     */
    private function orderCommissionContext(int $orderId): array
    {
        $ctx = [
            'customer_status' => '',
            'is_project' => false,
        ];

        if ($orderId <= 0 || ! Schema::hasTable('crm_orders')) {
            return $ctx;
        }

        try {
            $order = DB::table('crm_orders')->where('id', $orderId)->first();

            if (! $order) {
                return $ctx;
            }

            $orderCols = Schema::getColumnListing('crm_orders');

            foreach (['customer_status', 'customer_type', 'customer_group', 'lead_type'] as $col) {
                if (in_array($col, $orderCols, true) && ! empty($order->{$col})) {
                    $ctx['customer_status'] = $this->normalizeCustomerStatusValue($order->{$col});
                    break;
                }
            }

            foreach (['site_id', 'project_id', 'construction_id', 'site_project_id'] as $col) {
                if (in_array($col, $orderCols, true) && ! empty($order->{$col})) {
                    $ctx['is_project'] = true;
                    break;
                }
            }

            foreach (['order_type', 'type', 'source_type'] as $col) {
                if (in_array($col, $orderCols, true) && ! empty($order->{$col})) {
                    $type = mb_strtolower(\Illuminate\Support\Str::ascii((string) $order->{$col}));
                    if (str_contains($type, 'project') || str_contains($type, 'cong trinh') || str_contains($type, 'site')) {
                        $ctx['is_project'] = true;
                    }
                }
            }

            if ($ctx['customer_status'] === '' && in_array('lead_id', $orderCols, true) && ! empty($order->lead_id) && Schema::hasTable('crm_leads')) {
                $lead = DB::table('crm_leads')->where('id', (int) $order->lead_id)->first();

                if ($lead) {
                    $leadCols = Schema::getColumnListing('crm_leads');

                    foreach (['customer_status', 'customer_type', 'status', 'type', 'source_type'] as $col) {
                        if (in_array($col, $leadCols, true) && ! empty($lead->{$col})) {
                            $ctx['customer_status'] = $this->normalizeCustomerStatusValue($lead->{$col});
                            break;
                        }
                    }

                    if ($ctx['customer_status'] === '' && in_array('customer_id', $leadCols, true) && ! empty($lead->customer_id) && Schema::hasTable('crm_customers')) {
                        $customer = DB::table('crm_customers')->where('id', (int) $lead->customer_id)->first();

                        if ($customer) {
                            $customerCols = Schema::getColumnListing('crm_customers');

                            foreach (['customer_status', 'customer_type', 'status', 'type', 'group_name'] as $col) {
                                if (in_array($col, $customerCols, true) && ! empty($customer->{$col})) {
                                    $ctx['customer_status'] = $this->normalizeCustomerStatusValue($customer->{$col});
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            return $ctx;
        }

        return $ctx;
    }

    /**
     * Lấy danh sách quy tắc của chính sách, tự seed quy tắc mặc định nếu rỗng.
     */
    public function rulesForPolicy(int $policyId): Collection
    {
        $this->ensureSchema();

        $rules = DB::table('crm_commission_rules')
            ->where('policy_id', $policyId)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            $policy = DB::table('crm_commission_policies')->where('id', $policyId)->first();
            if ($policy) {
                $this->seedDefaultRules($policy);
            }

            $rules = DB::table('crm_commission_rules')
                ->where('policy_id', $policyId)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();
        }

        return $rules;
    }

    /**
     * Tạo bộ quy tắc hoa hồng mặc định (công trình, thương mại, tấm pin) cho chính sách.
     */
    private function seedDefaultRules(object $policy): void
    {
        $rows = [
            [
                'policy_id' => $policy->id,
                'period_month' => $policy->period_month,
                'commission_type' => 'project',
                'target_type' => 'all',
                'base_type' => 'revenue_before_vat',
                'calculation_type' => 'percent',
                'rate_percent' => $policy->project_rate_percent ?: 4,
                'priority' => 50,
                'is_active' => 1,
                'note' => 'Hoa hồng công trình mặc định 3-5%',
            ],
            [
                'policy_id' => $policy->id,
                'period_month' => $policy->period_month,
                'commission_type' => 'trade_product',
                'target_type' => 'all',
                'base_type' => 'revenue_before_vat',
                'calculation_type' => 'percent',
                'rate_percent' => $policy->trade_rate_percent ?: 1,
                'priority' => 20,
                'is_active' => 1,
                'note' => 'Hoa hồng thương mại sản phẩm 0.3-2%',
            ],
            [
                'policy_id' => $policy->id,
                'period_month' => $policy->period_month,
                'commission_type' => 'solar_panel',
                'target_type' => 'all',
                'base_type' => 'quantity',
                'calculation_type' => 'fixed_per_item',
                'fixed_amount' => $policy->panel_fixed_amount ?: 15000,
                'amount_per_unit' => $policy->panel_fixed_amount ?: 15000,
                'priority' => 100,
                'is_active' => 1,
                'note' => 'Tấm pin: set theo sản phẩm / tháng',
            ],
        ];

        foreach ($rows as $row) {
            $row['created_at'] = now();
            $row['updated_at'] = now();
            DB::table('crm_commission_rules')->insert($row);
        }
    }

    /**
     * Chuẩn bị dữ liệu cho trang cấu hình hoa hồng (chính sách, quy tắc, lương, KPI...).
     */
    public function settingsViewData(string $month): array
    {
        $policy = $this->currentPolicy($month);
        $rules = $this->rulesForPolicy((int) $policy->id);

        $products = Schema::hasTable('crm_product_catalog')
            ? DB::table('crm_product_catalog')->select('id', 'name', 'sku', 'brand_id', 'category_id')->orderBy('name')->limit(700)->get()
            : collect();

        $categories = Schema::hasTable('crm_product_categories')
            ? DB::table('crm_product_categories')->select('id', 'name')->orderBy('name')->get()
            : collect();

        $brands = Schema::hasTable('crm_brands')
            ? DB::table('crm_brands')->select('id', 'name')->orderBy('name')->get()
            : collect();

        $this->ensureSalesCompensationSchema();

        $salesUsers = $this->salesUserRows();
        $salarySettings = $this->salarySettingsForMonth($month, $salesUsers);

        $kpiTiers = DB::table('crm_sales_kpi_tiers')
            ->where('period_month', $month)
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($kpiTiers->isEmpty()) {
            DB::table('crm_sales_kpi_tiers')->insert([
                [
                    'period_month' => $month,
                    'sales_id' => null,
                    'tier_name' => 'Bậc 1 - Đạt 500 triệu',
                    'from_revenue' => 500000000,
                    'to_revenue' => null,
                    'bonus_type' => 'fixed',
                    'bonus_amount' => 1000000,
                    'priority' => 50,
                    'is_active' => 1,
                    'note' => 'Thưởng KPI khi doanh số đạt 500 triệu',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $kpiTiers = DB::table('crm_sales_kpi_tiers')
                ->where('period_month', $month)
                ->orderByDesc('priority')
                ->get();
        }

        return compact(
            'policy',
            'rules',
            'products',
            'categories',
            'brands',
            'month',
            'salesUsers',
            'salarySettings',
            'kpiTiers'
        );
    }

    /**
     * Lưu toàn bộ cấu hình hoa hồng của tháng từ request và trả về tháng đã lưu.
     */
    public function saveSettings(Request $request): string
    {
        $this->ensureSchema();

        $month = $request->input('period_month', now()->format('Y-m'));
        $policy = $this->currentPolicy($month);

        DB::table('crm_commission_policies')
            ->where('id', $policy->id)
            ->update([
                'name' => $request->input('name') ?: ('Chính sách hoa hồng '.$month),
                'status' => $request->input('status', 'active'),
                'project_rate_percent' => (float) $request->input('project_rate_percent', 4),
                'trade_rate_percent' => (float) $request->input('trade_rate_percent', 1),
                'panel_fixed_amount' => (float) $request->input('panel_fixed_amount', 15000),
                'only_paid' => $request->boolean('only_paid') ? 1 : 0,
                'only_shipped' => $request->boolean('only_shipped') ? 1 : 0,
                'only_completed' => $request->boolean('only_completed') ? 1 : 0,
                'hold_if_debt' => $request->boolean('hold_if_debt') ? 1 : 0,
                'is_active' => $request->boolean('is_active') ? 1 : 0,
                'note' => $request->input('note'),
                'updated_at' => now(),
            ]);

        DB::table('crm_commission_rules')->where('policy_id', $policy->id)->delete();

        $rules = (array) $request->input('rules', []);

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $commissionType = trim((string) ($rule['commission_type'] ?? ''));
            $targetType = trim((string) ($rule['target_type'] ?? 'all'));
            $calculationType = trim((string) ($rule['calculation_type'] ?? 'percent'));

            $hasAnyValue =
                $commissionType !== '' ||
                (float) ($rule['rate_percent'] ?? 0) > 0 ||
                (float) ($rule['fixed_amount'] ?? 0) > 0 ||
                (float) ($rule['amount_per_unit'] ?? 0) > 0 ||
                (float) ($rule['amount_per_kwp'] ?? 0) > 0 ||
                trim((string) ($rule['target_text'] ?? '')) !== '';

            if (! $hasAnyValue) {
                continue;
            }

            DB::table('crm_commission_rules')->insert([
                'policy_id' => $policy->id,
                'period_month' => $month,
                'commission_type' => $commissionType ?: 'trade_product',
                'target_type' => $targetType ?: 'all',
                'target_id' => ! empty($rule['target_id']) ? (int) $rule['target_id'] : null,
                'target_text' => $rule['target_text'] ?? null,
                'base_type' => $rule['base_type'] ?? 'revenue_before_vat',
                'calculation_type' => $calculationType ?: 'percent',
                'rate_percent' => (float) ($rule['rate_percent'] ?? 0),
                'fixed_amount' => (float) ($rule['fixed_amount'] ?? 0),
                'amount_per_unit' => (float) ($rule['amount_per_unit'] ?? 0),
                'amount_per_kwp' => (float) ($rule['amount_per_kwp'] ?? 0),
                'from_amount' => ($rule['from_amount'] ?? '') !== '' ? (float) $rule['from_amount'] : null,
                'to_amount' => ($rule['to_amount'] ?? '') !== '' ? (float) $rule['to_amount'] : null,
                'from_qty' => ($rule['from_qty'] ?? '') !== '' ? (float) $rule['from_qty'] : null,
                'to_qty' => ($rule['to_qty'] ?? '') !== '' ? (float) $rule['to_qty'] : null,
                'priority' => (int) ($rule['priority'] ?? 10),
                'is_active' => ! empty($rule['is_active']) ? 1 : 0,
                'note' => $rule['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->saveCompensationSettings($request, $month);

        Cache()->flush();

        return $month;
    }

    /**
     * Tính lại hoa hồng cho dữ liệu dashboard, lọc theo mức tối thiểu và xếp hạng sales.
     */
    public function applyDashboard(array $data, object $policy, Collection $rules, Request $request): array
    {
        $rows = $this->recalculateRows(collect($data['rows'] ?? []), $policy, $rules);

        if ($request->filled('min_commission')) {
            $min = (float) $request->get('min_commission');
            $rows = $rows->filter(fn ($row) => (float) ($row->commission_calc ?? 0) >= $min)->values();
        }

        $data['rows'] = $rows;
        $data['totalRevenue'] = (float) $rows->sum('total_amount');
        $data['totalCommission'] = (float) $rows->sum('commission_calc');
        $data['totalOrders'] = (int) $rows->count();
        $data['uniqueSales'] = (int) $rows->pluck('sales_user_id')->unique()->count();

        $data['ranking'] = $rows->groupBy('sales_user_id')->map(function ($g) {
            return [
                'sales_user_id' => $g->first()->sales_user_id,
                'sales_name' => $g->first()->sales_name,
                'orders' => $g->count(),
                'revenue' => (float) $g->sum('total_amount'),
                'commission' => (float) $g->sum('commission_calc'),
            ];
        })->sortByDesc('commission')->values()->take(10);

        return $data;
    }

    /**
     * Tính tổng tiền trước VAT của đơn hàng từ bảng đơn hoặc từ các dòng item.
     */
    private function orderBeforeVatTotalFromOrder(int $orderId): ?float
    {
        if ($orderId <= 0 || ! Schema::hasTable('crm_orders')) {
            return null;
        }

        $order = DB::table('crm_orders')->where('id', $orderId)->first();

        if (! $order) {
            return null;
        }

        $total = (float) ($order->total_amount ?? 0);
        $tax = 0.0;

        if (isset($order->tax_amount) && (float) $order->tax_amount > 0) {
            $tax = (float) $order->tax_amount;
        } elseif (isset($order->vat_amount) && (float) $order->vat_amount > 0) {
            $tax = (float) $order->vat_amount;
        }

        if ($total > 0 && $tax > 0 && $total >= $tax) {
            return round($total - $tax, 2);
        }

        if (Schema::hasTable('crm_order_items')) {
            try {
                $items = DB::table('crm_order_items')->where('order_id', $orderId)->get();
                $sumBeforeVat = 0.0;

                foreach ($items as $rawItem) {
                    $qty = $this->moneyNumber($rawItem->quantity ?? ($rawItem->qty ?? 1));
                    if ($qty <= 0) {
                        $qty = 1;
                    }

                    $lineAfter = $this->moneyNumber($rawItem->line_total ?? ($rawItem->total_amount ?? ($rawItem->total ?? 0)));

                    if ($lineAfter <= 0) {
                        $unitAfter = $this->moneyNumber($rawItem->unit_price ?? ($rawItem->price ?? 0));
                        $discount = $this->moneyNumber($rawItem->discount_amount ?? 0);
                        $lineAfter = max(0, ($unitAfter * $qty) - $discount);
                    }

                    $sumBeforeVat += $this->lineBeforeVatForCommissionItem((object) [
                        'id' => $rawItem->id ?? null,
                    ], $qty, $lineAfter, $this->moneyNumber($rawItem->vat_percent ?? 0), $orderId);
                }

                if ($sumBeforeVat > 0) {
                    return round($sumBeforeVat, 2);
                }
            } catch (\Throwable $e) {
                //
            }
        }

        return null;
    }

    /**
     * Tính lại doanh thu, giá vốn và hoa hồng cho từng dòng dữ liệu.
     */
    public function recalculateRows($rows, object $policy, Collection $rules): Collection
    {
        return collect($rows)->map(function ($row) use ($policy, $rules) {
            $calc = $this->calculateOrderCommission((int) ($row->order_id ?? 0), $policy, $rules);

            if (($calc['item_count'] ?? 0) <= 0) {
                return $row;
            }

            $row->total_amount = $calc['revenue_before_vat'];
            $row->revenue_after_vat = $calc['revenue_after_vat'];
            $row->fifo_cost = $calc['fifo_cost'];
            $row->gross_profit = $calc['gross_profit'];
            $row->commission_calc = $calc['commission_amount'];
            $row->rate_percent = $calc['display_rate'];
            $row->commission_source = $calc['source_label'];
            $row->commission_breakdown = $calc['breakdown_label'];

            return $row;
        })->values();
    }

    /**
     * Chuyển giá trị bất kỳ về số float tiền tệ an toàn.
     */
    private function moneyNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^\d\.\-]/', '', (string) $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Trả về cột đầu tiên tồn tại trong bảng theo danh sách ứng viên.
     */
    private function firstExistingColumn(string $table, array $columns): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $tableColumns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            if (in_array($column, $tableColumns, true)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Lấy giá trước VAT của sản phẩm từ catalog theo thứ tự ưu tiên cột giá.
     */
    private function productCatalogBeforeVatPrice(int $productId, ?int $priceTierId = null): float
    {
        if ($productId <= 0 || ! Schema::hasTable('crm_product_catalog')) {
            return 0.0;
        }

        try {
            $product = DB::table('crm_product_catalog')->where('id', $productId)->first();

            if (! $product) {
                return 0.0;
            }

            /*
            |--------------------------------------------------------------------------
            | Ưu tiên giá trước VAT
            |--------------------------------------------------------------------------
            | DB EGO đang có các cột dạng:
            | price_agent / price_retail = trước VAT
            | price_agent_vat / price_retail_vat = sau VAT
            */
            foreach ([
                'price_before_vat',
                'unit_price_before_vat',
                'price_ex_vat',
                'price_without_vat',
                'price_agent',
                'price_retail',
                'price',
            ] as $column) {
                if (isset($product->{$column})) {
                    $value = $this->moneyNumber($product->{$column});

                    if ($value > 0) {
                        return $value;
                    }
                }
            }
        } catch (\Throwable $e) {
            return 0.0;
        }

        return 0.0;
    }

    /**
     * Lấy giá trước VAT theo bảng giá (price tier) cho một dòng item.
     */
    private function productPriceBeforeVatForItem(object $rawItem, int $orderId): float
    {
        if (! Schema::hasTable('crm_product_prices')) {
            return 0.0;
        }

        $productId = (int) ($rawItem->product_id ?? 0);
        if ($productId <= 0) {
            return 0.0;
        }

        $priceTierId = (int) ($rawItem->price_tier_id ?? 0);

        if ($priceTierId <= 0 && Schema::hasTable('crm_orders')) {
            try {
                $priceTierId = (int) DB::table('crm_orders')->where('id', $orderId)->value('price_tier_id');
            } catch (\Throwable $e) {
                $priceTierId = 0;
            }
        }

        try {
            $q = DB::table('crm_product_prices')->where('product_id', $productId);

            if ($priceTierId > 0 && Schema::hasColumn('crm_product_prices', 'price_tier_id')) {
                $q->where('price_tier_id', $priceTierId);
            }

            $row = $q->orderByDesc('id')->first();

            if ($row && isset($row->price)) {
                return $this->moneyNumber($row->price);
            }
        } catch (\Throwable $e) {
            return 0.0;
        }

        return 0.0;
    }

    /**
     * Tính thành tiền trước VAT của một dòng item theo nhiều lớp fallback.
     */
    private function lineBeforeVatForCommissionItem(object $item, float $qty, float $lineAfter, float $vat, int $orderId): float
    {
        $qty = $qty > 0 ? $qty : 1;
        $rawItem = null;

        if (Schema::hasTable('crm_order_items') && ! empty($item->id)) {
            try {
                $rawItem = DB::table('crm_order_items')->where('id', (int) $item->id)->first();
            } catch (\Throwable $e) {
                $rawItem = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Ưu tiên cột line/tổng trước VAT ngay trên order_items
        |--------------------------------------------------------------------------
        */
        if ($rawItem) {
            foreach ([
                'line_total_before_vat',
                'total_before_vat',
                'subtotal_before_vat',
                'before_vat_total',
                'amount_before_vat',
                'net_total',
                'total_ex_vat',
                'total_excluding_vat',
                'total_without_vat',
            ] as $column) {
                if (isset($rawItem->{$column})) {
                    $value = $this->moneyNumber($rawItem->{$column});

                    if ($value > 0) {
                        return round($value, 2);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Nếu item có đơn giá trước VAT thì nhân số lượng
            |--------------------------------------------------------------------------
            */
            foreach ([
                'unit_price_before_vat',
                'price_before_vat',
                'price_ex_vat',
                'unit_price_ex_vat',
                'price_without_vat',
                'net_unit_price',
                'base_price',
            ] as $column) {
                if (isset($rawItem->{$column})) {
                    $value = $this->moneyNumber($rawItem->{$column});

                    if ($value > 0) {
                        return round($value * $qty, 2);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Bảng giá theo tier: crm_product_prices.price là trước VAT
            |--------------------------------------------------------------------------
            */
            $priceBeforeVat = $this->productPriceBeforeVatForItem($rawItem, $orderId);

            if ($priceBeforeVat > 0) {
                return round($priceBeforeVat * $qty, 2);
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Fallback product catalog: price_agent / price_retail là trước VAT
            |--------------------------------------------------------------------------
            */
            $catalogPrice = $this->productCatalogBeforeVatPrice((int) ($rawItem->product_id ?? 0));

            if ($catalogPrice > 0) {
                return round($catalogPrice * $qty, 2);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Nếu có VAT % thật thì quy đổi sau VAT về trước VAT
        |--------------------------------------------------------------------------
        */
        if ($vat > 0) {
            return round($lineAfter / (1 + $vat / 100), 2);
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Fallback theo tỷ lệ tổng đơn trước/sau VAT
        |--------------------------------------------------------------------------
        */
        $orderBeforeTotal = $this->orderBeforeVatTotalFromOrder($orderId);
        $orderAfterTotal = 0.0;

        if (Schema::hasTable('crm_orders')) {
            try {
                $orderAfterTotal = (float) DB::table('crm_orders')->where('id', $orderId)->value('total_amount');
            } catch (\Throwable $e) {
                $orderAfterTotal = 0.0;
            }
        }

        if ($orderBeforeTotal !== null && $orderBeforeTotal > 0 && $orderAfterTotal > 0) {
            return round($lineAfter * ($orderBeforeTotal / $orderAfterTotal), 2);
        }

        return round($lineAfter, 2);
    }

    /**
     * Tính toàn bộ hoa hồng của một đơn hàng.
     *
     * @return array Doanh thu trước/sau VAT, giá vốn FIFO, lợi nhuận, hoa hồng và nhãn diễn giải.
     */
    private function calculateOrderCommission(int $orderId, object $policy, Collection $rules): array
    {
        if ($orderId <= 0) {
            return ['item_count' => 0];
        }

        $items = $this->orderItemRows($orderId);
        $orderContext = $this->orderCommissionContext($orderId);
        $customerStatus = $orderContext['customer_status'] ?? '';
        $isProjectOrder = (bool) ($orderContext['is_project'] ?? false);

        $revenueBefore = 0;
        $revenueAfter = 0;
        $fifoCost = 0;
        $commission = 0;
        $counts = [
            'project' => 0,
            'trade_product' => 0,
            'solar_panel' => 0,
        ];
        $labels = [];

        foreach ($items as $item) {
            $qty = max(0, (float) ($item->quantity ?? 0));
            if ($qty <= 0) {
                $qty = 1;
            }

            $vat = max(0, (float) ($item->vat_percent ?? 0));
            $lineAfter = (float) ($item->line_total ?? 0);

            if ($lineAfter <= 0) {
                $unitPrice = (float) ($item->unit_price ?? 0);
                $discountAmount = (float) ($item->discount_amount ?? 0);
                $lineAfter = max(0, ($unitPrice * $qty) - $discountAmount);
            }

            $lineBefore = $this->lineBeforeVatForCommissionItem(
                $item,
                $qty,
                $lineAfter,
                $vat,
                $orderId
            );

            $lineCost = $this->fifoCostForOrderItem((int) ($item->id ?? 0));

            $revenueBefore += $lineBefore;
            $revenueAfter += $lineAfter;
            $fifoCost += $lineCost;

            $type = $isProjectOrder ? 'project' : ($this->isSolarPanel($item) ? 'solar_panel' : 'trade_product');
            $rule = $this->bestRule($rules, $type, $item, $lineBefore, $qty, $customerStatus);

            if (! $rule && $type === 'solar_panel') {
                $rule = $this->bestRule($rules, 'trade_product', $item, $lineBefore, $qty, $customerStatus);
                $type = 'trade_product';
            }

            if (! $rule) {
                continue;
            }

            $amount = $this->commissionAmountFromRule($rule, $item, $qty, $lineBefore, $lineAfter, $lineCost);
            $commission += $amount;
            $counts[$type]++;

            $labels[] = $this->ruleMiniLabel($rule, $amount);
        }

        $grossProfit = $revenueBefore - $fifoCost;
        $displayRate = $revenueBefore > 0 ? ($commission / $revenueBefore * 100) : 0;

        $parts = [];
        if ($counts['solar_panel'] > 0) {
            $parts[] = $counts['solar_panel'].' tấm pin';
        }
        if ($counts['trade_product'] > 0) {
            $parts[] = $counts['trade_product'].' thương mại';
        }
        if ($counts['project'] > 0) {
            $parts[] = $counts['project'].' công trình';
        }

        return [
            'item_count' => $items->count(),
            'revenue_before_vat' => round($revenueBefore, 2),
            'revenue_after_vat' => round($revenueAfter, 2),
            'fifo_cost' => round($fifoCost, 2),
            'gross_profit' => round($grossProfit, 2),
            'commission_amount' => round($commission, 2),
            'display_rate' => round($displayRate, 4),
            'source_label' => $parts ? implode(' + ', $parts) : 'Theo chính sách',
            'breakdown_label' => collect($labels)->unique()->take(3)->implode(' | '),
        ];
    }

    /**
     * Lấy các dòng item của đơn kèm thông tin sản phẩm, danh mục, thương hiệu.
     */
    private function orderItemRows(int $orderId): Collection
    {
        if (! Schema::hasTable('crm_order_items') || ! Schema::hasTable('crm_product_catalog')) {
            return collect();
        }

        $hasCategory = Schema::hasTable('crm_product_categories');
        $hasBrand = Schema::hasTable('crm_brands');

        $q = DB::table('crm_order_items as oi')
            ->leftJoin('crm_product_catalog as pc', 'pc.id', '=', 'oi.product_id');

        if ($hasCategory) {
            $q->leftJoin('crm_product_categories as cat', 'cat.id', '=', 'pc.category_id');
        }

        if ($hasBrand) {
            $q->leftJoin('crm_brands as br', 'br.id', '=', 'pc.brand_id');
        }

        return $q->where('oi.order_id', $orderId)
            ->select([
                'oi.id',
                'oi.order_id',
                'oi.product_id',
                DB::raw('COALESCE(oi.quantity, 1) as quantity'),
                DB::raw('COALESCE(oi.unit_price, 0) as unit_price'),
                DB::raw('COALESCE(oi.discount_amount, 0) as discount_amount'),
                DB::raw('COALESCE(oi.line_total, 0) as line_total'),
                DB::raw('COALESCE(pc.name, "") as product_name'),
                DB::raw('COALESCE(pc.sku, "") as sku'),
                DB::raw('COALESCE(pc.barcode, "") as barcode'),
                DB::raw('COALESCE(pc.category_id, 0) as category_id'),
                DB::raw('COALESCE(pc.brand_id, 0) as brand_id'),
                DB::raw('COALESCE(pc.vat_percent, 0) as vat_percent'),
                DB::raw($hasCategory ? 'COALESCE(cat.name, "") as category_name' : '"" as category_name'),
                DB::raw($hasBrand ? 'COALESCE(br.name, "") as brand_name' : '"" as brand_name'),
            ])
            ->get();
    }

    /**
     * Chọn quy tắc hoa hồng phù hợp nhất theo loại, mục tiêu, khoảng áp dụng và độ ưu tiên.
     */
    private function bestRule(Collection $rules, string $type, object $item, float $lineBefore, float $qty, string $customerStatus = ''): ?object
    {
        return $rules
            ->filter(fn ($rule) => (int) ($rule->is_active ?? 0) === 1)
            ->filter(fn ($rule) => ($rule->commission_type ?? '') === $type)
            ->filter(fn ($rule) => $this->targetMatches($rule, $item, $customerStatus))
            ->filter(fn ($rule) => $this->rangeMatches($rule, $lineBefore, $qty))
            ->sortByDesc(function ($rule) {
                $specific = match ($rule->target_type ?? 'all') {
                    'product' => 50,
                    'brand' => 40,
                    'category' => 30,
                    'keyword' => 20,
                    default => 10,
                };

                return ((int) ($rule->priority ?? 0) * 100) + $specific;
            })
            ->first();
    }

    /**
     * Kiểm tra item có khớp mục tiêu của quy tắc (sản phẩm/danh mục/thương hiệu/từ khóa/trạng thái khách).
     */
    private function targetMatches(object $rule, object $item, string $customerStatus = ''): bool
    {
        $targetType = $rule->target_type ?? 'all';
        $targetId = (int) ($rule->target_id ?? 0);

        if ($targetType === 'all') {
            return true;
        }

        if ($targetType === 'product') {
            return $targetId > 0 && $targetId === (int) ($item->product_id ?? 0);
        }

        if ($targetType === 'category') {
            return $targetId > 0 && $targetId === (int) ($item->category_id ?? 0);
        }

        if ($targetType === 'brand') {
            return $targetId > 0 && $targetId === (int) ($item->brand_id ?? 0);
        }

        if ($targetType === 'keyword') {
            $kw = mb_strtolower(trim((string) ($rule->target_text ?? '')));
            if ($kw === '') {
                return false;
            }

            $hay = mb_strtolower(implode(' ', [
                $item->product_name ?? '',
                $item->sku ?? '',
                $item->barcode ?? '',
                $item->category_name ?? '',
                $item->brand_name ?? '',
            ]));

            return str_contains($hay, $kw);
        }

        if ($targetType === 'customer_status') {
            $ruleStatus = $this->normalizeCustomerStatusValue($rule->target_text ?? '');

            return $ruleStatus !== '' && $customerStatus !== '' && $ruleStatus === $customerStatus;
        }

        return false;
    }

    /**
     * Kiểm tra giá trị và số lượng có nằm trong khoảng áp dụng của quy tắc.
     */
    private function rangeMatches(object $rule, float $amount, float $qty): bool
    {
        $fromAmount = $rule->from_amount;
        $toAmount = $rule->to_amount;
        $fromQty = $rule->from_qty;
        $toQty = $rule->to_qty;

        if ($fromAmount !== null && $fromAmount !== '' && $amount < (float) $fromAmount) {
            return false;
        }
        if ($toAmount !== null && $toAmount !== '' && $amount > (float) $toAmount) {
            return false;
        }
        if ($fromQty !== null && $fromQty !== '' && $qty < (float) $fromQty) {
            return false;
        }
        if ($toQty !== null && $toQty !== '' && $qty > (float) $toQty) {
            return false;
        }

        return true;
    }

    /**
     * Tính số tiền hoa hồng cho dòng item theo cách tính của quy tắc.
     */
    private function commissionAmountFromRule(object $rule, object $item, float $qty, float $lineBefore, float $lineAfter, float $lineCost): float
    {
        $calcType = $rule->calculation_type ?? 'percent';
        $baseType = $rule->base_type ?? 'revenue_before_vat';
        $grossProfit = $lineBefore - $lineCost;

        $base = match ($baseType) {
            // EGO rule: hoa hồng % luôn lấy base trước VAT, kể cả rule cũ đang lưu revenue_after_vat.
            'revenue_after_vat', 'revenue_before_vat' => $lineBefore,
            'gross_profit' => $grossProfit,
            'quantity' => $qty,
            'kwp' => $this->parseKwp($item, $qty),
            default => $lineBefore,
        };

        return match ($calcType) {
            'fixed_per_item' => $qty * max((float) ($rule->fixed_amount ?? 0), (float) ($rule->amount_per_unit ?? 0)),
            'fixed_per_kwp' => $this->parseKwp($item, $qty) * max((float) ($rule->fixed_amount ?? 0), (float) ($rule->amount_per_kwp ?? 0)),
            'fixed_per_order' => (float) ($rule->fixed_amount ?? 0),
            default => max(0, $base) * (float) ($rule->rate_percent ?? 0) / 100,
        };
    }

    /**
     * Sinh nhãn ngắn mô tả quy tắc và số tiền hoa hồng.
     */
    private function ruleMiniLabel(object $rule, float $amount): string
    {
        $type = match ($rule->commission_type ?? '') {
            'project' => 'Công trình',
            'solar_panel' => 'Tấm pin',
            default => 'Thương mại',
        };

        $calc = match ($rule->calculation_type ?? '') {
            'fixed_per_item' => 'đ/tấm',
            'fixed_per_kwp' => 'đ/kWp',
            'fixed_per_order' => 'cố định',
            default => '%',
        };

        return $type.' '.$calc.': '.number_format($amount, 0, ',', '.').'đ';
    }

    /**
     * Nhận diện item có phải tấm pin mặt trời dựa trên tên/sku/thương hiệu.
     */
    private function isSolarPanel(object $item): bool
    {
        $hay = mb_strtolower(implode(' ', [
            $item->product_name ?? '',
            $item->sku ?? '',
            $item->barcode ?? '',
            $item->category_name ?? '',
            $item->brand_name ?? '',
        ]));

        foreach (['tấm pin', 'tam pin', 'pin năng lượng', 'solar panel', 'panel', 'ja solar', 'longi', 'trina', 'jinko', 'canadian solar', 'ae solar'] as $kw) {
            if (str_contains($hay, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ước tính công suất kWp từ số watt trong tên sản phẩm nhân với số lượng.
     */
    private function parseKwp(object $item, float $qty): float
    {
        $text = (string) (($item->product_name ?? '').' '.($item->sku ?? ''));

        if (preg_match('/([3-9][0-9]{2,3})\s*w/i', $text, $m)) {
            return ($qty * (float) $m[1]) / 1000;
        }

        return 0;
    }

    /**
     * Tính giá vốn FIFO của một dòng đơn hàng từ các lô đã phân bổ.
     */
    private function fifoCostForOrderItem(int $orderItemId): float
    {
        if ($orderItemId <= 0 || ! Schema::hasTable('crm_product_stock_lots')) {
            return 0;
        }

        foreach (['crm_order_item_stock_allocations', 'order_item_stock_allocations'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $cols = Schema::getColumnListing($table);
            if (! in_array('order_item_id', $cols, true)) {
                continue;
            }

            $qtyCol = collect(['qty', 'quantity', 'allocated_qty', 'qty_allocated'])
                ->first(fn ($c) => in_array($c, $cols, true));

            $lotCol = collect(['lot_id', 'stock_lot_id', 'product_stock_lot_id'])
                ->first(fn ($c) => in_array($c, $cols, true));

            if (! $qtyCol || ! $lotCol) {
                continue;
            }

            return (float) DB::table($table.' as a')
                ->leftJoin('crm_product_stock_lots as l', 'l.id', '=', 'a.'.$lotCol)
                ->where('a.order_item_id', $orderItemId)
                ->selectRaw("
                    COALESCE(SUM(
                        COALESCE(a.{$qtyCol}, 0)
                        *
                        COALESCE(NULLIF(l.actual_cost_after_vat, 0), NULLIF(l.cost_after_vat, 0), 0)
                    ), 0) as cost
                ")
                ->value('cost');
        }

        return 0;
    }

    /**
     * Thống kê số quy tắc đang hoạt động theo từng loại hoa hồng.
     */
    public function ruleStats(Collection $rules): array
    {
        return [
            'project' => $rules->where('commission_type', 'project')->where('is_active', 1)->count(),
            'trade_product' => $rules->where('commission_type', 'trade_product')->where('is_active', 1)->count(),
            'solar_panel' => $rules->where('commission_type', 'solar_panel')->where('is_active', 1)->count(),
            'total' => $rules->where('is_active', 1)->count(),
        ];
    }
}
