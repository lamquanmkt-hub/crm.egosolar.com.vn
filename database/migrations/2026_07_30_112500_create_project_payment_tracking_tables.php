<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_test_payment_milestones')) {
            Schema::create('project_test_payment_milestones', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedInteger('sequence')->default(1);
                $table->string('title', 255);
                $table->decimal('percentage', 5, 2)->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->date('due_date')->nullable()->index();
                $table->text('condition_text')->nullable();
                $table->text('note')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();

                $table->index(['project_id', 'sequence'], 'pt_pay_milestone_project_sequence');
            });
        }

        if (! Schema::hasTable('project_test_payment_transactions')) {
            Schema::create('project_test_payment_transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('milestone_id')->nullable()->index();
                $table->string('transaction_code', 60)->nullable()->unique();
                $table->date('paid_at')->index();
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 30)->default('bank_transfer');
                $table->string('receiving_account', 255)->nullable();
                $table->string('reference_no', 255)->nullable()->index();
                $table->string('payer_name', 255)->nullable();
                $table->string('proof_path', 700)->nullable();
                $table->text('note')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedBigInteger('recorded_by')->nullable()->index();
                $table->unsignedBigInteger('confirmed_by')->nullable()->index();
                $table->timestamp('confirmed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable()->index();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();

                $table->index(['project_id', 'status', 'paid_at'], 'pt_pay_tx_project_status_date');
            });
        }

        // Chuyển số tiền "Đã thu" cũ thành một giao dịch xác nhận để không mất lịch sử.
        if (Schema::hasTable('project_test_projects') && Schema::hasColumn('project_test_projects', 'amount_collected')) {
            DB::table('project_test_projects')
                ->where('amount_collected', '>', 0)
                ->orderBy('id')
                ->chunkById(100, function ($projects): void {
                    foreach ($projects as $project) {
                        $alreadyMigrated = DB::table('project_test_payment_transactions')
                            ->where('project_id', $project->id)
                            ->exists();

                        if ($alreadyMigrated) {
                            continue;
                        }

                        $now = now();
                        $milestoneId = DB::table('project_test_payment_milestones')->insertGetId([
                            'project_id' => $project->id,
                            'sequence' => 1,
                            'title' => 'Số tiền đã thu trước khi nâng cấp',
                            'amount' => (float) $project->amount_collected,
                            'status' => 'paid',
                            'created_by' => $project->financial_updated_by ?? null,
                            'updated_by' => $project->financial_updated_by ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        DB::table('project_test_payment_transactions')->insert([
                            'project_id' => $project->id,
                            'milestone_id' => $milestoneId,
                            'transaction_code' => 'LEGACY-'.$project->id.'-'.$now->format('YmdHis'),
                            'paid_at' => $project->financial_updated_at ? Carbon::parse($project->financial_updated_at)->format('Y-m-d') : $now->format('Y-m-d'),
                            'amount' => (float) $project->amount_collected,
                            'payment_method' => 'other',
                            'note' => 'Dữ liệu tổng đã thu được chuyển tự động khi nâng cấp quản lý thanh toán từng đợt.',
                            'status' => 'confirmed',
                            'recorded_by' => $project->financial_updated_by ?? null,
                            'confirmed_by' => $project->financial_updated_by ?? null,
                            'confirmed_at' => $project->financial_updated_at ?? $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Không tự xóa bảng để tránh mất lịch sử thu tiền khi rollback code.
    }
};
