<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies') && !Schema::hasColumn('companies', 'bank_accounts')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->longText('bank_accounts')->nullable()->after('bank_holder');
            });
        }

        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'bank_accounts')) {
            $companies = DB::table('companies')->get();

            foreach ($companies as $company) {
                if (!empty($company->bank_accounts)) {
                    continue;
                }

                $account = trim((string) ($company->bank_account ?? ''));
                $bank = trim((string) ($company->bank_name ?? ''));
                $holder = trim((string) ($company->bank_holder ?? ''));

                if ($account !== '' || $bank !== '' || $holder !== '') {
                    DB::table('companies')
                        ->where('id', $company->id)
                        ->update([
                            'bank_accounts' => json_encode([
                                [
                                    'bank_account' => $account,
                                    'bank_name' => $bank,
                                    'bank_holder' => $holder,
                                    'is_default' => 1,
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'bank_accounts')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('bank_accounts');
            });
        }
    }
};
