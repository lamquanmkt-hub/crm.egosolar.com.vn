<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sites')) {
            Schema::table(
                'sites',
                function (Blueprint $table) {
                    if (
                        !Schema::hasColumn(
                            'sites',
                            'created_by'
                        )
                    ) {
                        $table
                            ->unsignedBigInteger('created_by')
                            ->nullable()
                            ->index();
                    }

                    if (
                        !Schema::hasColumn(
                            'sites',
                            'company_id'
                        )
                    ) {
                        $table
                            ->unsignedBigInteger('company_id')
                            ->nullable()
                            ->index();
                    }
                }
            );
        }

        if (Schema::hasTable('receipts')) {
            Schema::table(
                'receipts',
                function (Blueprint $table) {
                    if (
                        !Schema::hasColumn(
                            'receipts',
                            'site_id'
                        )
                    ) {
                        $table
                            ->unsignedBigInteger('site_id')
                            ->nullable()
                            ->index();
                    }

                    if (
                        !Schema::hasColumn(
                            'receipts',
                            'site_payment_term_id'
                        )
                    ) {
                        $table
                            ->unsignedBigInteger(
                                'site_payment_term_id'
                            )
                            ->nullable()
                            ->index();
                    }
                }
            );
        }
    }

    public function down(): void
    {
        // Không tự động xóa cột để tránh mất dữ liệu production.
    }
};
