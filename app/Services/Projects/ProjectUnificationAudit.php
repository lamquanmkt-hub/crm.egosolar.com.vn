<?php

declare(strict_types=1);
namespace App\Services\Projects;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ProjectUnificationAudit
{
    /**
     * One reviewed direct record, NOT a rule approving every Sales-looking row.
     * Evidence: project-unify-review-20260906-204833-2fcfb3.json.
     * User's rule: canonical projects created by Sales retain Sales provenance.
     * Match identity + live role again before assigning any ownership metadata.
     */
    private const REVIEWED_DIRECT_SALES = [
        36 => [
            'project_code' => 'DA-SITE-00036',
            'created_by' => 58,
            'company_id' => 2,
            'created_at' => '2026-07-15 11:32:57',
            'expected_role' => 'sales_manager',
        ],
    ];

    public function plan(bool $lockRows = false): array
    {
        foreach (['id', 'created_by', 'legacy_source', 'legacy_source_id', 'project_code', 'company_id'] as $column) {
            if (! Schema::hasColumn('sites', $column)) {
                throw new RuntimeException('Missing canonical sites.'.$column);
            }
        }
        $siteQuery = DB::table('sites')->orderBy('id');
        if ($lockRows) { $siteQuery->lockForUpdate(); }
        $sites = $siteQuery->get();
        $mapped = $sites->filter(fn ($s) => $s->legacy_source === 'project_test' && (int) $s->legacy_source_id > 0)
            ->groupBy('legacy_source_id');
        $issues = [];
        $updates = [];
        foreach ($mapped as $legacyId => $rows) {
            if ($rows->count() > 1) {
                $issues[] = ['type' => 'multiple_canonical_sites', 'legacy_id' => (int) $legacyId, 'site_ids' => $rows->pluck('id')->all()];
            }
        }
        $legacyRows = collect();
        if (Schema::hasTable('project_test_projects')) {
            $legacyQuery = DB::table('project_test_projects')->orderBy('id');
            if ($lockRows) { $legacyQuery->lockForUpdate(); }
            $legacyRows = $legacyQuery->get();
        }
        $liveIds = [];
        foreach ($legacyRows as $legacy) {
            $live = empty($legacy->deleted_at) && ! in_array(strtolower((string) ($legacy->status ?? '')), ['deleted', 'removed', 'trashed', 'archived'], true);
            $rows = $mapped[(int) $legacy->id] ?? collect();
            if (! $live) {
                // Refuse to resurrect a site previously hidden by the legacy deletion filter.
                foreach ($rows as $site) {
                    if ($this->siteIsLive($site)) { $issues[] = ['type' => 'legacy_deleted_canonical_active', 'legacy_id' => (int) $legacy->id, 'site_id' => (int) $site->id]; }
                }
                continue;
            }
            $liveIds[] = (int) $legacy->id;
            if ($rows->count() !== 1) {
                $issues[] = ['type' => 'unmapped_legacy_project', 'legacy_id' => (int) $legacy->id, 'code' => (string) ($legacy->code ?? '')];
                continue;
            }
            $site = $rows->first();
            if (! $this->siteIsLive($site)) {
                $issues[] = ['type' => 'legacy_active_canonical_inactive', 'legacy_id' => (int) $legacy->id, 'site_id' => (int) $site->id];
                continue;
            }
            if ((int) ($legacy->company_id ?? 0) > 0 && (int) ($site->company_id ?? 0) > 0
                && (int) $legacy->company_id !== (int) $site->company_id) {
                $issues[] = ['type' => 'company_mismatch', 'legacy_id' => (int) $legacy->id, 'site_id' => (int) $site->id];
                continue;
            }
            $source = trim((string) ($legacy->request_source ?? ''));
            if ($source === '') { $source = (int) ($legacy->sales_user_id ?? 0) > 0 ? 'sales' : 'technical'; }
            if (! in_array($source, ['sales', 'technical', 'internal', 'cskh', 'warranty', 'maintenance', 'legacy_maintenance'], true)) {
                $issues[] = ['type' => 'unknown_legacy_source', 'legacy_id' => (int) $legacy->id, 'request_source' => $source];
                continue;
            }
            // Maintenance origins remain maintenance. Never invent a Sales owner.
            if (in_array($source, ['maintenance', 'legacy_maintenance'], true)
                && ((int) ($legacy->sales_user_id ?? 0) > 0 || (int) ($legacy->sales_order_id ?? 0) > 0)) {
                $issues[] = ['type' => 'maintenance_sales_metadata_conflict', 'legacy_id' => (int) $legacy->id, 'site_id' => (int) $site->id];
                continue;
            }
            $owner = (int) ($legacy->sales_user_id ?? 0);
            if ($source === 'sales' && $owner <= 0) { $owner = (int) ($legacy->created_by ?? 0); }
            if ($source === 'sales' && ($owner <= 0 || ! DB::table('users')->where('id', $owner)->exists())) {
                $issues[] = ['type' => 'missing_sales_owner', 'legacy_id' => (int) $legacy->id, 'site_id' => (int) $site->id];
                continue;
            }
            if (! empty($site->request_source) && $site->request_source !== $source) {
                $issues[] = ['type' => 'source_conflict', 'site_id' => (int) $site->id];
                continue;
            }
            if (! empty($site->sales_user_id) && (int) $site->sales_user_id !== $owner) {
                $issues[] = ['type' => 'sales_owner_conflict', 'site_id' => (int) $site->id];
                continue;
            }
            if (! empty($site->sales_order_id) && (int) $site->sales_order_id !== (int) ($legacy->sales_order_id ?? 0)) {
                $issues[] = ['type' => 'sales_order_conflict', 'site_id' => (int) $site->id];
                continue;
            }
            $updates[] = [
                'site_id' => (int) $site->id,
                'legacy_id' => (int) $legacy->id,
                'request_source' => $source,
                'sales_user_id' => $source === 'sales' ? $owner : null,
                'sales_order_id' => (int) ($legacy->sales_order_id ?? 0) > 0 ? (int) $legacy->sales_order_id : null,
            ];
        }
        foreach ($mapped as $legacyId => $rows) {
            if (! $legacyRows->contains('id', (int) $legacyId)) {
                foreach ($rows as $site) {
                    if ($this->siteIsLive($site)) { $issues[] = ['type' => 'legacy_missing_canonical_active', 'site_id' => (int) $site->id, 'legacy_id' => (int) $legacyId]; }
                }
            }
        }
        // Detect duplicate canonical codes without guessing which record is correct.
        foreach ($sites->filter(fn ($s) => $this->siteIsLive($s) && !empty($s->project_code))
                     ->groupBy(fn ($s) => (string) $s->company_id.'|'.trim((string) $s->project_code)) as $code => $rows) {
            if ($rows->count() > 1) { $issues[] = ['type' => 'duplicate_project_code', 'code' => $code, 'site_ids' => $rows->pluck('id')->all()]; }
        }
        $mappedCount = count($updates);
        $reviewedDirect = [];
        foreach ($sites as $site) {
            if (! $this->siteIsLive($site) || (string) ($site->legacy_source ?? '') === 'project_test') { continue; }
            $id = (int) $site->id;
            if (isset(self::REVIEWED_DIRECT_SALES[$id])) {
                $row = $this->reviewedDirectSalesUpdate($site, self::REVIEWED_DIRECT_SALES[$id], $issues);
                if ($row !== null) {
                    $updates[] = $row;
                    $reviewedDirect[] = ['site_id' => $id, 'sales_user_id' => $row['sales_user_id'], 'reason' => 'reviewed_direct_sales_creator'];
                }
                continue;
            }
            if (! empty($site->request_source)) { continue; }
            if (! empty($site->sales_user_id) || ! empty($site->sales_order_id)) {
                $issues[] = ['type' => 'direct_site_source_unknown', 'site_id' => $id];
                continue;
            }
            $creator = !empty($site->created_by) ? User::query()->find((int) $site->created_by) : null;
            if ($creator && (new UnifiedProjectAccess)->isSalesScoped($creator)) {
                // Unreviewed Sales-created records still STOP; this is not a bypass.
                $issues[] = ['type' => 'direct_sales_creator_needs_review', 'site_id' => $id, 'created_by' => (int) $creator->id];
                continue;
            }
            // Previous non-Sales default, but only for live, explicitly inspected rows.
            // No blanket UPDATE over every site with empty metadata.
            $updates[] = ['site_id' => $id, 'legacy_id' => null, 'request_source' => 'technical', 'sales_user_id' => null, 'sales_order_id' => null];
        }
        return [
            'ok' => $issues === [], 'canonical_count' => $sites->count(),
            'legacy_live_count' => count($liveIds), 'mapped_count' => $mappedCount,
            'direct_update_count' => count($updates) - $mappedCount,
            'reviewed_direct_sales' => $reviewedDirect,
            'issues' => $issues, 'updates' => $updates,
        ];
    }

    private function reviewedDirectSalesUpdate($site, array $expected, array &$issues): ?array
    {
        $id = (int) $site->id;
        $mismatches = [];
        foreach (['project_code', 'created_at'] as $field) {
            if ((string) ($site->{$field} ?? '') !== $expected[$field]) { $mismatches[] = $field; }
        }
        foreach (['created_by', 'company_id'] as $field) {
            if ((int) ($site->{$field} ?? 0) !== $expected[$field]) { $mismatches[] = $field; }
        }
        if (! empty($site->legacy_source) || ! empty($site->legacy_source_id)) { $mismatches[] = 'legacy_link'; }
        if ($mismatches !== []) {
            $issues[] = ['type' => 'reviewed_direct_site_identity_changed', 'site_id' => $id, 'fields' => $mismatches];
            return null;
        }
        $source = (string) ($site->request_source ?? '');
        $owner = (int) ($site->sales_user_id ?? 0);
        if (($source !== '' && $source !== 'sales') || ($owner > 0 && $owner !== $expected['created_by']) || !empty($site->sales_order_id)) {
            $issues[] = ['type' => 'reviewed_direct_site_metadata_conflict', 'site_id' => $id];
            return null;
        }
        $creator = User::query()->find($expected['created_by']);
        $access = new UnifiedProjectAccess;
        if (!$creator || (int) ($creator->is_active ?? 0) !== 1 || !$access->isSalesScoped($creator)
            || !in_array($expected['expected_role'], $access->roles($creator), true)) {
            $issues[] = ['type' => 'reviewed_direct_sales_creator_changed', 'site_id' => $id, 'created_by' => $expected['created_by']];
            return null;
        }
        return [
            'site_id' => $id, 'legacy_id' => null,
            'request_source' => 'sales', 'sales_user_id' => $expected['created_by'], 'sales_order_id' => null,
        ];
    }

    private function siteIsLive($site): bool
    {
        return empty($site->deleted_at)
            && ! in_array(strtolower((string) ($site->status ?? '')), ['deleted', 'removed', 'trashed', 'archived'], true);
    }
}
