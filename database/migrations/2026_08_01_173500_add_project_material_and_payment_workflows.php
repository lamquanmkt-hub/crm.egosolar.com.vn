<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_material_proposals')) {
            Schema::create('project_material_proposals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('status', 50)->default('SUBMITTED')->index();
                $table->string('priority', 30)->default('normal');
                $table->date('needed_at')->nullable()->index();
                $table->text('purpose')->nullable();
                $table->text('note')->nullable();
                $table->string('attachment_path')->nullable();
                $table->unsignedBigInteger('technical_approved_by')->nullable()->index();
                $table->dateTime('technical_approved_at')->nullable();
                $table->text('approval_note')->nullable();
                $table->text('warehouse_note')->nullable();
                $table->timestamps();
                $table->index(['site_id', 'status']);
            });
        }

        if (! Schema::hasTable('project_material_proposal_items')) {
            Schema::create('project_material_proposal_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('proposal_id')->index();
                $table->string('requested_name');
                $table->text('requested_spec')->nullable();
                $table->decimal('requested_qty', 15, 2)->default(0);
                $table->string('requested_unit', 50)->nullable();
                $table->date('need_date')->nullable();
                $table->boolean('is_critical')->default(false);
                $table->text('request_note')->nullable();
                $table->unsignedBigInteger('selected_product_id')->nullable()->index();
                $table->unsignedBigInteger('selected_warehouse_id')->nullable()->index();
                $table->decimal('selected_qty', 15, 2)->nullable();
                $table->string('warehouse_status', 50)->default('PENDING')->index();
                $table->text('substitution_reason')->nullable();
                $table->boolean('serial_required')->default(false);
                $table->unsignedBigInteger('warehouse_selected_by')->nullable()->index();
                $table->dateTime('warehouse_selected_at')->nullable();
                $table->unsignedBigInteger('material_request_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_payment_records')) {
            Schema::create('project_payment_records', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id')->index();
                $table->unsignedBigInteger('payment_term_id')->nullable()->index();
                $table->unsignedBigInteger('receipt_id')->nullable()->index();
                $table->decimal('amount', 18, 2)->default(0);
                $table->date('paid_at')->index();
                $table->string('payment_method', 100)->default('bank_transfer');
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('transaction_reference')->nullable();
                $table->string('payer_name')->nullable();
                $table->text('note')->nullable();
                $table->string('attachment_path')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('confirmed_by')->nullable()->index();
                $table->string('status', 50)->default('CONFIRMED')->index();
                $table->timestamps();
                $table->index(['site_id', 'paid_at']);
            });
        }

        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table): void {
                if (! Schema::hasColumn('receipts', 'site_id')) {
                    $table->unsignedBigInteger('site_id')->nullable()->index();
                }
                if (! Schema::hasColumn('receipts', 'site_payment_term_id')) {
                    $table->unsignedBigInteger('site_payment_term_id')->nullable()->index();
                }
                if (! Schema::hasColumn('receipts', 'transaction_reference')) {
                    $table->string('transaction_reference')->nullable();
                }
                if (! Schema::hasColumn('receipts', 'attachment_path')) {
                    $table->string('attachment_path')->nullable();
                }
                if (! Schema::hasColumn('receipts', 'confirmed_by')) {
                    $table->unsignedBigInteger('confirmed_by')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_payment_records');
        Schema::dropIfExists('project_material_proposal_items');
        Schema::dropIfExists('project_material_proposals');
    }
};
