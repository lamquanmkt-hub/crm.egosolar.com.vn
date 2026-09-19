<?php

declare(strict_types=1);
namespace App\Services\Projects;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Same ledger as /du-an/{site}?step=finance. No ProjectTest money is read. */
final class UnifiedProjectRevenue
{
    public function contract($site): float
    {
        foreach (['contract_amount_after_vat', 'contract_amount', 'quote_grand_total'] as $key) {
            if (isset($site->{$key}) && (float) $site->{$key} > 0) { return (float) $site->{$key}; }
        }
        return 0.0;
    }

    /** @return array<int, float> */
    public function receivedBySite(Collection $siteIds): array
    {
        $ids = $siteIds->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($ids === []) { return []; }
        $result = array_fill_keys($ids, 0.0);
        $linkedReceiptIds = [];
        if (Schema::hasTable('project_payment_records')) {
            $records = DB::table('project_payment_records')->whereIn('site_id', $ids);
            if (Schema::hasColumn('project_payment_records', 'deleted_at')) { $records->whereNull('deleted_at'); }
            if (Schema::hasColumn('project_payment_records', 'status')) { $records->where('status', 'CONFIRMED'); }
            foreach ((clone $records)->select('site_id')->selectRaw('SUM(amount) AS received')->groupBy('site_id')->get() as $row) {
                $result[(int) $row->site_id] += (float) $row->received;
            }
            if (Schema::hasColumn('project_payment_records', 'receipt_id')) {
                $linkedReceiptIds = (clone $records)->whereNotNull('receipt_id')->pluck('receipt_id')->all();
            }
        }
        // Retain the technical module's receipt eligibility rule verbatim.
        if (Schema::hasTable('receipts') && Schema::hasColumn('receipts', 'site_id') && Schema::hasColumn('receipts', 'amount')) {
            $receipts = DB::table('receipts')->whereIn('site_id', $ids);
            if (Schema::hasColumn('receipts', 'deleted_at')) { $receipts->whereNull('deleted_at'); }
            if ($linkedReceiptIds !== []) { $receipts->whereNotIn('id', $linkedReceiptIds); }
            foreach ($receipts->select('site_id')->selectRaw('SUM(amount) AS received')->groupBy('site_id')->get() as $row) {
                $result[(int) $row->site_id] += (float) $row->received;
            }
        }
        return $result;
    }

    /** @return array<int, array{contract:float,received:float,debt:float}> */
    public function forSites(Collection $sites): array
    {
        $received = $this->receivedBySite($sites->pluck('id'));
        $result = [];
        foreach ($sites as $site) {
            $contract = $this->contract($site);
            $paid = (float) ($received[(int) $site->id] ?? 0);
            $result[(int) $site->id] = [
                'contract' => $contract, 'received' => $paid, 'debt' => max(0.0, $contract - $paid),
            ];
        }
        return $result;
    }

    public function summary(Collection $sites): array
    {
        $result = ['contract' => 0.0, 'received' => 0.0, 'debt' => 0.0];
        foreach ($this->forSites($sites) as $row) {
            foreach ($result as $key => $value) { $result[$key] += $row[$key]; }
        }
        return $result;
    }
}
