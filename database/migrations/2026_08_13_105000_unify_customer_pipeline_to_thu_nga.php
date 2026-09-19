<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $customerBackup = 'ego_customer_merge_20260813_customers';
    private string $reportBackup = 'ego_customer_merge_20260813_reports';
    private string $createdMap = 'ego_customer_merge_20260813_created';
    private string $handoverLeadMap = 'ego_customer_merge_20260813_handover_leads';

    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('crm_customers')) {
            return;
        }

        $thuNga = DB::table('users')->where('id', 58)->first();

        if (! $thuNga || mb_strtolower(trim((string) ($thuNga->email ?? ''))) !== 'thunga@egosolar.vn') {
            $thuNga = DB::table('users')
                ->whereRaw('LOWER(TRIM(email)) = ?', ['thunga@egosolar.vn'])
                ->first();
        }

        if (! $thuNga) {
            $thuNga = DB::table('users')
                ->whereRaw('LOWER(TRIM(name)) = ?', ['thu nga'])
                ->first();
        }

        if (! $thuNga) {
            throw new RuntimeException('Không tìm thấy tài khoản Thu Nga để nhận bàn giao khách hàng.');
        }

        $thuNgaId = (int) $thuNga->id;

        $this->createBackupTables();
        $this->backupCurrentAssignments();

        // Bàn giao quyền chăm sóc hiện tại cho Thu Nga.
        // KHÔNG đổi crm_leads.assigned_to để giữ nguyên lịch sử Sales/đơn hàng cũ.
        if (Schema::hasColumn('crm_customers', 'owner_id')) {
            DB::table('crm_customers')->update(['owner_id' => $thuNgaId]);
        }

        // Tạo lead bàn giao mới để ĐƠN HÀNG MỚI của các khách cũ lấy đúng Thu Nga.
        // Lead cũ và crm_orders.lead_id tuyệt đối giữ nguyên.
        $this->createHandoverLeads($thuNgaId);

        if (! Schema::hasTable('sales_work_reports')) {
            return;
        }

        if (Schema::hasColumn('sales_work_reports', 'assigned_to')) {
            DB::table('sales_work_reports')->update(['assigned_to' => $thuNgaId]);
        }

        if (! Schema::hasColumn('sales_work_reports', 'customer_id')) {
            return;
        }

        $customerColumns = Schema::getColumnListing('crm_customers');
        $leadColumns = Schema::hasTable('crm_leads')
            ? Schema::getColumnListing('crm_leads')
            : [];

        $defaultCompanyId = null;
        if (in_array('company_id', $customerColumns, true)) {
            $defaultCompanyId = DB::table('crm_customers')
                ->whereNotNull('company_id')
                ->select('company_id', DB::raw('COUNT(*) as total'))
                ->groupBy('company_id')
                ->orderByDesc('total')
                ->value('company_id');
        }

        $normalizePhone = static function ($phone): string {
            $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';
            if (str_starts_with($digits, '84') && strlen($digits) >= 10) {
                $digits = '0'.substr($digits, 2);
            }
            return $digits;
        };

        [$phoneIndex, $emailIndex] = $this->buildCustomerIndexes($normalizePhone);

        DB::table('sales_work_reports')
            ->whereNull('customer_id')
            ->orderBy('id')
            ->chunkById(100, function ($reports) use (
                $thuNgaId,
                $customerColumns,
                $leadColumns,
                $defaultCompanyId,
                $normalizePhone,
                &$phoneIndex,
                &$emailIndex
            ): void {
                foreach ($reports as $report) {
                    $phoneKey = $normalizePhone($report->customer_phone ?? null);
                    $emailKey = mb_strtolower(trim((string) ($report->customer_email ?? '')));

                    $customerId = null;

                    if ($phoneKey !== '' && isset($phoneIndex[$phoneKey]) && $phoneIndex[$phoneKey] !== false) {
                        $customerId = (int) $phoneIndex[$phoneKey];
                    }

                    if (! $customerId && $emailKey !== '' && isset($emailIndex[$emailKey]) && $emailIndex[$emailKey] !== false) {
                        $customerId = (int) $emailIndex[$emailKey];
                    }

                    $leadId = null;

                    if (! $customerId) {
                        $insert = [];
                        $put = static function (array &$target, array $columns, string $key, mixed $value): void {
                            if (in_array($key, $columns, true)) {
                                $target[$key] = $value;
                            }
                        };

                        $name = trim((string) ($report->customer_name ?? ''));
                        $put($insert, $customerColumns, 'name', $name !== '' ? $name : 'Khách #'.$report->id);
                        $put($insert, $customerColumns, 'phone', $report->customer_phone ?? null);
                        $put($insert, $customerColumns, 'email', $report->customer_email ?? null);
                        $put($insert, $customerColumns, 'address', $report->customer_address ?? ($report->region_text ?? null));
                        $put($insert, $customerColumns, 'customer_status', 'lead');
                        $put($insert, $customerColumns, 'is_potential', ($report->priority ?? null) === 'hot' ? 1 : 0);
                        $put($insert, $customerColumns, 'owner_id', $thuNgaId);
                        $put($insert, $customerColumns, 'company_id', $report->company_id ?? $defaultCompanyId);
                        $put($insert, $customerColumns, 'created_at', $report->created_at ?? now());
                        $put($insert, $customerColumns, 'updated_at', now());

                        $customerId = (int) DB::table('crm_customers')->insertGetId($insert);

                        if ($phoneKey !== '' && ! array_key_exists($phoneKey, $phoneIndex)) {
                            $phoneIndex[$phoneKey] = $customerId;
                        }
                        if ($emailKey !== '' && ! array_key_exists($emailKey, $emailIndex)) {
                            $emailIndex[$emailKey] = $customerId;
                        }

                        // Lead mới chỉ được tạo cho khách vừa sinh từ dữ liệu chăm sóc.
                        // Không đụng bất kỳ lead cũ nào đang gắn với đơn hàng hiện tại.
                        if ($leadColumns && in_array('customer_id', $leadColumns, true)) {
                            $lead = [];
                            $put($lead, $leadColumns, 'customer_id', $customerId);
                            $put($lead, $leadColumns, 'assigned_to', $thuNgaId);
                            $put($lead, $leadColumns, 'contact_date', ! empty($report->data_received_at)
                                ? date('Y-m-d', strtotime((string) $report->data_received_at))
                                : now()->toDateString());
                            $put($lead, $leadColumns, 'status_id', 1);
                            $put($lead, $leadColumns, 'note', 'Đồng bộ từ dữ liệu chăm sóc #'.$report->id);
                            $put($lead, $leadColumns, 'created_by', $report->created_by ?? $thuNgaId);
                            $put($lead, $leadColumns, 'created_at', $report->created_at ?? now());
                            $put($lead, $leadColumns, 'updated_at', now());

                            $leadId = (int) DB::table('crm_leads')->insertGetId($lead);
                        }

                        DB::table($this->createdMap)->updateOrInsert(
                            ['report_id' => (int) $report->id],
                            [
                                'customer_id' => $customerId,
                                'lead_id' => $leadId ?: null,
                                'created_at' => now(),
                            ]
                        );
                    }

                    DB::table('sales_work_reports')
                        ->where('id', $report->id)
                        ->update([
                            'customer_id' => $customerId,
                            'assigned_to' => $thuNgaId,
                            'updated_at' => now(),
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_work_reports') && Schema::hasTable($this->reportBackup)) {
            DB::table($this->reportBackup)
                ->orderBy('report_id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('sales_work_reports')
                            ->where('id', $row->report_id)
                            ->update([
                                'assigned_to' => $row->assigned_to,
                                'customer_id' => $row->customer_id,
                            ]);
                    }
                }, 'report_id');
        }

        if (Schema::hasTable('crm_customers') && Schema::hasTable($this->customerBackup)) {
            DB::table($this->customerBackup)
                ->orderBy('customer_id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('crm_customers')
                            ->where('id', $row->customer_id)
                            ->update(['owner_id' => $row->owner_id]);
                    }
                }, 'customer_id');
        }

        // Xóa lead bàn giao chỉ khi chưa có đơn hàng mới sử dụng lead đó.
        if (Schema::hasTable($this->handoverLeadMap) && Schema::hasTable('crm_leads')) {
            foreach (DB::table($this->handoverLeadMap)->orderByDesc('lead_id')->get() as $row) {
                $hasOrder = Schema::hasTable('crm_orders')
                    && Schema::hasColumn('crm_orders', 'lead_id')
                    && DB::table('crm_orders')->where('lead_id', $row->lead_id)->exists();

                if (! $hasOrder) {
                    DB::table('crm_leads')->where('id', $row->lead_id)->delete();
                }
            }
        }

        // Xóa những customer/lead sinh mới chỉ khi chưa có đơn hàng phát sinh sau khi nâng cấp.
        if (Schema::hasTable($this->createdMap)) {
            $rows = DB::table($this->createdMap)->orderByDesc('customer_id')->get();

            foreach ($rows as $row) {
                $hasOrder = false;
                if (
                    ! empty($row->lead_id)
                    && Schema::hasTable('crm_orders')
                    && Schema::hasColumn('crm_orders', 'lead_id')
                ) {
                    $hasOrder = DB::table('crm_orders')
                        ->where('lead_id', $row->lead_id)
                        ->exists();
                }

                if ($hasOrder) {
                    continue;
                }

                if (! empty($row->lead_id) && Schema::hasTable('crm_leads')) {
                    DB::table('crm_leads')->where('id', $row->lead_id)->delete();
                }

                if (Schema::hasTable('crm_customers')) {
                    DB::table('crm_customers')->where('id', $row->customer_id)->delete();
                }
            }
        }

        Schema::dropIfExists($this->handoverLeadMap);
        Schema::dropIfExists($this->createdMap);
        Schema::dropIfExists($this->reportBackup);
        Schema::dropIfExists($this->customerBackup);
    }

    private function createBackupTables(): void
    {
        if (! Schema::hasTable($this->customerBackup)) {
            Schema::create($this->customerBackup, function (Blueprint $table): void {
                $table->unsignedBigInteger('customer_id')->primary();
                $table->unsignedBigInteger('owner_id')->nullable();
            });
        }

        if (! Schema::hasTable($this->reportBackup)) {
            Schema::create($this->reportBackup, function (Blueprint $table): void {
                $table->unsignedBigInteger('report_id')->primary();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
            });
        }

        if (! Schema::hasTable($this->createdMap)) {
            Schema::create($this->createdMap, function (Blueprint $table): void {
                $table->unsignedBigInteger('report_id')->primary();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable($this->handoverLeadMap)) {
            Schema::create($this->handoverLeadMap, function (Blueprint $table): void {
                $table->unsignedBigInteger('customer_id')->primary();
                $table->unsignedBigInteger('lead_id');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    private function createHandoverLeads(int $thuNgaId): void
    {
        if (! Schema::hasTable('crm_leads')) {
            return;
        }

        $columns = Schema::getColumnListing('crm_leads');
        if (! in_array('customer_id', $columns, true)) {
            return;
        }

        $put = static function (array &$target, array $columns, string $key, mixed $value): void {
            if (in_array($key, $columns, true)) {
                $target[$key] = $value;
            }
        };

        // Chỉ các customer có trước lúc migration; khách tạo từ report sẽ có lead riêng ở bước sau.
        $originalCustomerIds = DB::table($this->customerBackup)->pluck('customer_id');

        foreach ($originalCustomerIds as $customerId) {
            $latestLead = DB::table('crm_leads')
                ->where('customer_id', $customerId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($latestLead && (int) ($latestLead->assigned_to ?? 0) === $thuNgaId) {
                continue;
            }

            $lead = [];
            $put($lead, $columns, 'customer_id', (int) $customerId);
            $put($lead, $columns, 'assigned_to', $thuNgaId);
            $put($lead, $columns, 'contact_date', now()->toDateString());
            $put($lead, $columns, 'status_id', $latestLead->status_id ?? 1);
            $put($lead, $columns, 'note', 'Bàn giao khách hàng hiện tại cho Thu Nga; giữ nguyên lead và đơn hàng cũ.');
            $put($lead, $columns, 'created_by', $thuNgaId);
            $put($lead, $columns, 'created_at', now());
            $put($lead, $columns, 'updated_at', now());

            $leadId = (int) DB::table('crm_leads')->insertGetId($lead);

            DB::table($this->handoverLeadMap)->updateOrInsert(
                ['customer_id' => (int) $customerId],
                ['lead_id' => $leadId, 'created_at' => now()]
            );
        }
    }

    private function backupCurrentAssignments(): void
    {
        DB::table('crm_customers')
            ->select(['id', 'owner_id'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $payload = [];
                foreach ($rows as $row) {
                    $payload[] = [
                        'customer_id' => (int) $row->id,
                        'owner_id' => $row->owner_id,
                    ];
                }
                if ($payload) {
                    DB::table($this->customerBackup)->insertOrIgnore($payload);
                }
            }, 'id');

        if (Schema::hasTable('sales_work_reports')) {
            DB::table('sales_work_reports')
                ->select(['id', 'assigned_to', 'customer_id'])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    $payload = [];
                    foreach ($rows as $row) {
                        $payload[] = [
                            'report_id' => (int) $row->id,
                            'assigned_to' => $row->assigned_to,
                            'customer_id' => $row->customer_id,
                        ];
                    }
                    if ($payload) {
                        DB::table($this->reportBackup)->insertOrIgnore($payload);
                    }
                }, 'id');
        }
    }

    private function buildCustomerIndexes(callable $normalizePhone): array
    {
        $phoneIndex = [];
        $emailIndex = [];

        foreach (DB::table('crm_customers')->select(['id', 'phone', 'email'])->get() as $customer) {
            $phone = $normalizePhone($customer->phone ?? null);
            if ($phone !== '') {
                if (array_key_exists($phone, $phoneIndex) && $phoneIndex[$phone] !== (int) $customer->id) {
                    $phoneIndex[$phone] = false; // trùng -> không tự ghép
                } else {
                    $phoneIndex[$phone] = (int) $customer->id;
                }
            }

            $email = mb_strtolower(trim((string) ($customer->email ?? '')));
            if ($email !== '') {
                if (array_key_exists($email, $emailIndex) && $emailIndex[$email] !== (int) $customer->id) {
                    $emailIndex[$email] = false;
                } else {
                    $emailIndex[$email] = (int) $customer->id;
                }
            }
        }

        return [$phoneIndex, $emailIndex];
    }
};
