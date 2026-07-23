<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('crm_customers')
            && ! Schema::hasColumn(
                'crm_customers',
                'ai_chatbot_link'
            )
        ) {
            Schema::table(
                'crm_customers',
                function (Blueprint $table): void {
                    $table
                        ->text('ai_chatbot_link')
                        ->nullable()
                        ->after('zalo_id');
                }
            );
        }

        if (
            ! Schema::hasTable(
                'crm_customer_handover_logs'
            )
        ) {
            Schema::create(
                'crm_customer_handover_logs',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->unsignedBigInteger('customer_id')
                        ->index();

                    $table
                        ->unsignedBigInteger('from_user_id')
                        ->nullable()
                        ->index();

                    $table
                        ->unsignedBigInteger('to_user_id')
                        ->index();

                    $table
                        ->unsignedBigInteger('handed_over_by')
                        ->nullable()
                        ->index();

                    $table
                        ->text('note')
                        ->nullable();

                    $table->timestamps();

                    $table->index([
                        'customer_id',
                        'created_at',
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'crm_customer_handover_logs'
        );

        if (
            Schema::hasTable('crm_customers')
            && Schema::hasColumn(
                'crm_customers',
                'ai_chatbot_link'
            )
        ) {
            Schema::table(
                'crm_customers',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'ai_chatbot_link'
                    );
                }
            );
        }
    }
};
