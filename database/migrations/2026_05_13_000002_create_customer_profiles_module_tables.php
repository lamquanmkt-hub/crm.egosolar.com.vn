<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('agent_name')->nullable()->index();
                $table->string('agent_level')->nullable()->index();
                $table->unsignedBigInteger('price_tier_id')->nullable()->index();
                $table->decimal('deposit_amount', 18, 2)->default(0);
                $table->date('deposit_date')->nullable()->index();
                $table->string('status', 50)->default('draft')->index();
                $table->string('phone')->nullable()->index();
                $table->string('email')->nullable()->index();
                $table->text('address')->nullable();
                $table->string('tax_code')->nullable()->index();
                $table->string('representative_name')->nullable();
                $table->string('representative_position')->nullable();
                $table->string('contract_code')->nullable()->index();
                $table->date('contract_date')->nullable();
                $table->date('next_followup_date')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique('customer_id', 'customer_profiles_customer_id_unique');
            });
        }

        if (!Schema::hasTable('customer_profile_documents')) {
            Schema::create('customer_profile_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_profile_id')->index();
                $table->string('document_type', 80)->default('other')->index();
                $table->string('title')->nullable();
                $table->string('original_name');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profile_documents');
        Schema::dropIfExists('customer_profiles');
    }
};
