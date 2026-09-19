<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proposals')) {
            return;
        }

        Schema::table('proposals', function (Blueprint $table) {
            if (! Schema::hasColumn('proposals', 'payment_request_id')) {
                $table->unsignedBigInteger('payment_request_id')
                    ->nullable()
                    ->after('status');

                $table->unique(
                    'payment_request_id',
                    'proposals_pr_id_unique'
                );
            }

            if (! Schema::hasColumn(
                'proposals',
                'payment_request_created_at'
            )) {
                $table->timestamp('payment_request_created_at')
                    ->nullable()
                    ->after('payment_request_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('proposals')) {
            return;
        }

        Schema::table('proposals', function (Blueprint $table) {
            if (Schema::hasColumn(
                'proposals',
                'payment_request_created_at'
            )) {
                $table->dropColumn(
                    'payment_request_created_at'
                );
            }

            if (Schema::hasColumn(
                'proposals',
                'payment_request_id'
            )) {
                try {
                    $table->dropUnique(
                        'proposals_pr_id_unique'
                    );
                } catch (\Throwable $e) {
                    // Tương thích trường hợp index đã được đổi tên.
                }

                $table->dropColumn('payment_request_id');
            }
        });
    }
};
