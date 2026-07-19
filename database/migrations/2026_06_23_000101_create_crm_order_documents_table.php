<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_order_documents')) {
            Schema::create('crm_order_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('customer_profile_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('document_type', 80)->default('other')->index();
                $table->string('title')->nullable();
                $table->string('original_name');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->timestamps();

                $table->index(['order_id', 'document_type']);
                $table->index(['customer_profile_id', 'document_type']);
            });

            return;
        }

        Schema::table('crm_order_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_order_documents', 'order_id')) {
                $table->unsignedBigInteger('order_id')->index()->after('id');
            }

            if (!Schema::hasColumn('crm_order_documents', 'customer_profile_id')) {
                $table->unsignedBigInteger('customer_profile_id')->nullable()->index()->after('order_id');
            }

            if (!Schema::hasColumn('crm_order_documents', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->index()->after('customer_profile_id');
            }

            if (!Schema::hasColumn('crm_order_documents', 'document_type')) {
                $table->string('document_type', 80)->default('other')->index()->after('customer_id');
            }

            if (!Schema::hasColumn('crm_order_documents', 'title')) {
                $table->string('title')->nullable()->after('document_type');
            }

            if (!Schema::hasColumn('crm_order_documents', 'original_name')) {
                $table->string('original_name')->after('title');
            }

            if (!Schema::hasColumn('crm_order_documents', 'file_path')) {
                $table->string('file_path')->after('original_name');
            }

            if (!Schema::hasColumn('crm_order_documents', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('file_path');
            }

            if (!Schema::hasColumn('crm_order_documents', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')->default(0)->after('mime_type');
            }

            if (!Schema::hasColumn('crm_order_documents', 'note')) {
                $table->text('note')->nullable()->after('size_bytes');
            }

            if (!Schema::hasColumn('crm_order_documents', 'uploaded_by')) {
                $table->unsignedBigInteger('uploaded_by')->nullable()->index()->after('note');
            }

            if (!Schema::hasColumn('crm_order_documents', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_documents');
    }
};
