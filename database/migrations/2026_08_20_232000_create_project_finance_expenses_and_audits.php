<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_finance_expenses')) {
            Schema::create('project_finance_expenses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->date('expense_date')->index();
                $table->string('category', 50)->default('other')->index();
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('payee')->nullable();
                $table->string('description', 1000);
                $table->text('note')->nullable();
                $table->string('status', 30)->default('ACTIVE')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_finance_audits')) {
            Schema::create('project_finance_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->string('entity_type', 50)->index();
                $table->string('source', 50);
                $table->unsignedBigInteger('entity_id')->index();
                $table->longText('before_data')->nullable();
                $table->longText('after_data')->nullable();
                $table->text('reason');
                $table->unsignedBigInteger('changed_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('sites') && Schema::hasColumn('sites', 'other_cost')) {
            $existingSiteIds = DB::table('project_finance_expenses')->distinct()->pluck('site_id');
            DB::table('sites')->where('other_cost', '>', 0)
                ->when($existingSiteIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $existingSiteIds->all()))
                ->orderBy('id')->chunkById(100, function ($sites): void {
                    foreach ($sites as $site) {
                        DB::table('project_finance_expenses')->insert([
                            'site_id' => $site->id, 'expense_date' => now()->toDateString(),
                            'category' => 'other', 'amount' => (float) $site->other_cost,
                            'description' => 'Chi phí khác trước khi nâng cấp giao diện tài chính',
                            'note' => 'Dữ liệu được hệ thống chuyển tự động.', 'status' => 'ACTIVE',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Giữ nguyên lịch sử chi phí và nhật ký tài chính khi rollback giao diện.
    }
};
