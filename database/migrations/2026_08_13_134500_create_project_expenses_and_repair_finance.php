<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_test_expenses')) {
            Schema::create('project_test_expenses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->string('expense_code', 60)->unique();
                $table->date('expense_date')->index();
                $table->string('category', 40)->index();
                $table->string('description', 500);
                $table->decimal('amount', 15, 2);
                $table->string('payee_name', 255)->nullable();
                $table->text('note')->nullable();
                $table->string('proof_path', 700)->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('confirmed_by')->nullable()->index();
                $table->timestamp('confirmed_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable()->index();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable()->index();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();

                $table->index(['project_id', 'status', 'expense_date'], 'pt_exp_project_status_date');
            });
        }

        if (! Schema::hasTable('project_test_finance_patch_backups')) {
            Schema::create('project_test_finance_patch_backups', function (Blueprint $table): void {
                $table->id();
                $table->string('patch_key', 120)->unique();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->longText('payload');
                $table->timestamp('created_at')->nullable();
            });
        }

        $this->repairCt36PaymentPlan();
    }

    public function down(): void
    {
        // Cố ý giữ nguyên sổ chi phí và dữ liệu tài chính đã phát sinh khi rollback code.
        // Snapshot trước khi sửa CT-OLD-000036 nằm trong project_test_finance_patch_backups.
    }

    private function repairCt36PaymentPlan(): void
    {
        if (
            ! Schema::hasTable('project_test_projects')
            || ! Schema::hasTable('project_test_payment_milestones')
            || ! Schema::hasTable('project_test_payment_transactions')
        ) {
            return;
        }

        $project = DB::table('project_test_projects')->where('code', 'CT-OLD-000036')->first();
        if (! $project) {
            return;
        }

        $patchKey = 'finance-v4-ct-old-000036-20260813';
        if (! DB::table('project_test_finance_patch_backups')->where('patch_key', $patchKey)->exists()) {
            DB::table('project_test_finance_patch_backups')->insert([
                'patch_key' => $patchKey,
                'project_id' => $project->id,
                'payload' => json_encode([
                    'project' => $project,
                    'milestones' => DB::table('project_test_payment_milestones')->where('project_id', $project->id)->orderBy('id')->get()->all(),
                    'transactions' => DB::table('project_test_payment_transactions')->where('project_id', $project->id)->orderBy('id')->get()->all(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        }

        DB::transaction(function () use ($project): void {
            $milestone1 = DB::table('project_test_payment_milestones')
                ->where('project_id', $project->id)
                ->where('sequence', 1)
                ->orderBy('id')
                ->first();

            if ($milestone1) {
                DB::table('project_test_payment_milestones')->where('id', $milestone1->id)->update([
                    'title' => 'Đợt 1 - Đặt cọc',
                    'percentage' => 30.00,
                    'amount' => 43500000.00,
                    'status' => 'paid',
                    'updated_at' => now(),
                ]);
            } else {
                $milestone1Id = DB::table('project_test_payment_milestones')->insertGetId([
                    'project_id' => $project->id,
                    'sequence' => 1,
                    'title' => 'Đợt 1 - Đặt cọc',
                    'percentage' => 30.00,
                    'amount' => 43500000.00,
                    'due_date' => null,
                    'condition_text' => null,
                    'note' => 'Khôi phục từ kế hoạch thanh toán công trình cũ.',
                    'status' => 'paid',
                    'created_by' => $project->sales_user_id ?: $project->created_by,
                    'updated_by' => $project->sales_user_id ?: $project->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $milestone1 = (object) ['id' => $milestone1Id];
            }

            $milestone2 = DB::table('project_test_payment_milestones')
                ->where('project_id', $project->id)
                ->where('sequence', 2)
                ->orderBy('id')
                ->first();
            if (! $milestone2) {
                $milestone2Id = DB::table('project_test_payment_milestones')->insertGetId([
                    'project_id' => $project->id,
                    'sequence' => 2,
                    'title' => 'Đợt 2 - Triển khai',
                    'percentage' => 60.00,
                    'amount' => 87000000.00,
                    'due_date' => null,
                    'condition_text' => 'Theo tiến độ triển khai công trình.',
                    'note' => 'Khôi phục từ kế hoạch thanh toán công trình cũ.',
                    'status' => 'paid',
                    'created_by' => $project->sales_user_id ?: $project->created_by,
                    'updated_by' => $project->sales_user_id ?: $project->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $milestone2 = (object) ['id' => $milestone2Id];
            } else {
                DB::table('project_test_payment_milestones')->where('id', $milestone2->id)->update([
                    'title' => 'Đợt 2 - Triển khai',
                    'percentage' => 60.00,
                    'amount' => 87000000.00,
                    'updated_at' => now(),
                ]);
            }

            $milestone3 = DB::table('project_test_payment_milestones')
                ->where('project_id', $project->id)
                ->where('sequence', 3)
                ->orderBy('id')
                ->first();
            if (! $milestone3) {
                DB::table('project_test_payment_milestones')->insert([
                    'project_id' => $project->id,
                    'sequence' => 3,
                    'title' => 'Đợt 3 - Nghiệm thu / bàn giao',
                    'percentage' => 10.00,
                    'amount' => 14500000.00,
                    'due_date' => null,
                    'condition_text' => 'Thanh toán khi nghiệm thu / bàn giao.',
                    'note' => 'Khôi phục từ kế hoạch thanh toán công trình cũ.',
                    'status' => 'pending',
                    'created_by' => $project->sales_user_id ?: $project->created_by,
                    'updated_by' => $project->sales_user_id ?: $project->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('project_test_payment_milestones')->where('id', $milestone3->id)->update([
                    'title' => 'Đợt 3 - Nghiệm thu / bàn giao',
                    'percentage' => 10.00,
                    'amount' => 14500000.00,
                    'updated_at' => now(),
                ]);
            }

            $confirmed87 = DB::table('project_test_payment_transactions')
                ->where('project_id', $project->id)
                ->where('transaction_code', 'PT-36-20260811-001')
                ->where('status', 'confirmed')
                ->first();
            if ($confirmed87 && abs((float) $confirmed87->amount - 87000000.00) < 1) {
                DB::table('project_test_payment_transactions')->where('id', $confirmed87->id)->update([
                    'milestone_id' => $milestone2->id,
                    'updated_at' => now(),
                ]);
            }

            $duplicatePending = DB::table('project_test_payment_transactions')
                ->where('project_id', $project->id)
                ->where('transaction_code', 'PT-36-20260810-001')
                ->where('status', 'pending')
                ->first();
            if ($duplicatePending && abs((float) $duplicatePending->amount - 87000000.00) < 1) {
                DB::table('project_test_payment_transactions')->where('id', $duplicatePending->id)->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Hệ thống đánh dấu giao dịch trùng khi chuẩn hóa công nợ CT-OLD-000036. Giữ nguyên bản ghi để đối soát.',
                    'updated_at' => now(),
                ]);
            }

            $confirmed = (float) DB::table('project_test_payment_transactions')
                ->where('project_id', $project->id)
                ->where('status', 'confirmed')
                ->sum('amount');
            $adjusted = Schema::hasTable('project_test_payment_adjustments')
                ? (float) DB::table('project_test_payment_adjustments')
                    ->where('project_id', $project->id)
                    ->where('status', 'approved')
                    ->sum('delta_amount')
                : 0.0;

            DB::table('project_test_projects')->where('id', $project->id)->update([
                'amount_collected' => max(0, $confirmed + $adjusted),
                'financial_updated_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (DB::table('project_test_payment_milestones')->where('project_id', $project->id)->get() as $milestone) {
                $milestoneConfirmed = (float) DB::table('project_test_payment_transactions')
                    ->where('milestone_id', $milestone->id)
                    ->where('status', 'confirmed')
                    ->sum('amount');
                $status = $milestoneConfirmed >= (float) $milestone->amount && (float) $milestone->amount > 0
                    ? 'paid'
                    : ($milestoneConfirmed > 0 ? 'partial' : 'pending');
                DB::table('project_test_payment_milestones')->where('id', $milestone->id)->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('project_test_histories')) {
                DB::table('project_test_histories')->insert([
                    'project_id' => $project->id,
                    'user_id' => null,
                    'action' => 'Hệ thống chuẩn hóa kế hoạch thanh toán CT-OLD-000036',
                    'from_status' => $project->status,
                    'to_status' => $project->status,
                    'note' => 'Khôi phục kế hoạch 30% / 60% / 10%, chuyển khoản 87 triệu đã xác nhận về Đợt 2 và đánh dấu giao dịch pending trùng là đã hủy.',
                    'meta' => json_encode(['patch' => 'finance-v4', 'snapshot_key' => 'finance-v4-ct-old-000036-20260813'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
