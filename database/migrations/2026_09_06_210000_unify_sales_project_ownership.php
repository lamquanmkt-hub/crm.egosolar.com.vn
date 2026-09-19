<?php

use App\Services\Projects\ProjectUnificationAudit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $plan = app(ProjectUnificationAudit::class)->plan();
        if (! $plan['ok']) { throw new RuntimeException('Project unification blocked: '.json_encode($plan['issues'], JSON_UNESCAPED_UNICODE)); }
        $columns = Schema::getColumnListing('sites');
        Schema::table('sites', function (Blueprint $table) use ($columns): void {
            if (! in_array('request_source', $columns, true)) { $table->string('request_source', 40)->nullable()->index(); }
            if (! in_array('sales_user_id', $columns, true)) { $table->unsignedBigInteger('sales_user_id')->nullable()->index(); }
            if (! in_array('sales_order_id', $columns, true)) { $table->unsignedBigInteger('sales_order_id')->nullable()->index(); }
        });
        // Metadata only. Never modify canonical money, workflow, company or creator.
        DB::transaction(function (): void {
            // Re-read and lock the source/canonical rows inside the transaction.
            // A report generated earlier is not permission to overwrite changed data.
            $plan = app(ProjectUnificationAudit::class)->plan(true);
            if (! $plan['ok']) { throw new RuntimeException('Project metadata changed during deployment: '.json_encode($plan['issues'], JSON_UNESCAPED_UNICODE)); }
            foreach ($plan['updates'] as $row) {
                DB::table('sites')->where('id', $row['site_id'])->update([
                    'request_source' => $row['request_source'],
                    'sales_user_id' => $row['sales_user_id'],
                    'sales_order_id' => $row['sales_order_id'],
                ]);
            }
        });
    }

    public function down(): void
    {
        // Intentionally additive. Rollback code from its backup, not business records.
    }
};
