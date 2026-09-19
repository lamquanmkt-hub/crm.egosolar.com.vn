@extends('layouts.app')

@section('title', 'Khách hàng - Chăm sóc & Pipeline')

@section('content')
@php
    $money = function ($value) {
        if ($value === null || $value === '') return '0 đ';
        return number_format((float) $value, 0, ',', '.') . ' đ';
    };

    $date = function ($value, $format = 'd/m/Y') {
        if (!$value) return '—';
        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return $value;
        }
    };

    $typeLabel = fn ($key) => $customerTypes[$key] ?? $key ?? '—';
    $stageLabel = fn ($key) => $customerStages[$key] ?? $key ?? '—';
    $statusLabel = fn ($key) => $statuses[$key] ?? $key ?? '—';
    $priorityLabel = fn ($key) => $priorities[$key] ?? $key ?? '—';

    $typeClass = function ($value) {
        return match ($value) {
            'dealer', 'business', 'contractor', 'factory' => 'blue',
            'retail', 'personal' => 'orange',
            'turnkey' => 'green',
            default => 'gray',
        };
    };

    $statusClass = function ($value) {
        return match ($value) {
            'new', 'new_need_confirm' => 'green',
            'quoted', 'quoted_waiting' => 'blue',
            'consulting', 'interested', 'comparing_price' => 'orange',
            'follow_up', 'need_follow' => 'yellow',
            'won', 'closed_won' => 'green',
            'lost', 'closed_lost', 'invalid', 'not_interested' => 'gray',
            'no_answer', 'unreachable' => 'red',
            default => 'blue',
        };
    };

    $firstReportId = optional($reports->first())->id;

    /*
     * Gom danh sách khách hàng theo nhân viên phụ trách.
     * Lưu ý: controller đã tăng per_page lên 500 để không bị tình trạng trang 1 chỉ hiện 1-2 nhân viên.
     */
    $reportCollection = $reports->getCollection();
    $salesNameOrder = collect($salesUsers ?? [])->pluck('name')->filter()->values();
    $reportGroups = collect();

    foreach ($salesNameOrder as $salesNameInList) {
        $items = $reportCollection
            ->filter(fn ($report) => (string) ($report->sales_name ?: 'Chưa phân công') === (string) $salesNameInList)
            ->values();

        if ($items->count() > 0) {
            $reportGroups->put($salesNameInList, $items);
        }
    }

    $extraGroups = $reportCollection
        ->filter(fn ($report) => !$salesNameOrder->contains((string) ($report->sales_name ?: 'Chưa phân công')))
        ->groupBy(fn ($report) => $report->sales_name ?: 'Chưa phân công');

    $reportGroups = $reportGroups->merge($extraGroups);
@endphp

<style>
    :root {
        --ego-bg: #f6f8fb;
        --ego-card: #ffffff;
        --ego-line: #e8eef5;
        --ego-text: #0f172a;
        --ego-muted: #64748b;
        --ego-primary: #00a886;
        --ego-primary-dark: #00856d;
        --ego-blue: #2f80ed;
        --ego-orange: #f59e0b;
        --ego-red: #ef4444;
        --ego-green: #10b981;
    }

    .ego-sales-page {
        background: var(--ego-bg);
        padding: 16px;
        min-height: calc(100vh - 80px);
    }

    .ego-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 12px;
    }

    .ego-title {
        margin: 0;
        font-size: 28px;
        font-weight: 950;
        color: var(--ego-text);
        letter-spacing: -.04em;
    }

    .ego-subtitle {
        margin-top: 3px;
        color: var(--ego-muted);
        font-size: 13px;
    }

    .ego-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ego-btn {
        height: 36px;
        border-radius: 12px;
        border: 0;
        padding: 0 14px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        font-weight: 850;
        text-decoration: none;
        white-space: nowrap;
    }

    .ego-btn-light {
        background: #fff;
        color: #334155;
        border: 1px solid var(--ego-line);
    }

    .ego-btn-primary {
        background: var(--ego-primary);
        color: #fff;
        box-shadow: 0 8px 18px rgba(0, 168, 134, .22);
    }

    .ego-toolbar {
        background: var(--ego-card);
        border: 1px solid var(--ego-line);
        border-radius: 18px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, .045);
    }

    .ego-tabs {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .ego-tab {
        border: 1px solid #e6edf5;
        background: #f8fafc;
        color: #334155;
        height: 32px;
        border-radius: 10px;
        padding: 0 12px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
    }

    .ego-tab.active {
        color: #fff;
        border-color: var(--ego-primary);
        background: linear-gradient(135deg, #00a886, #00bf99);
        box-shadow: 0 8px 18px rgba(0, 168, 134, .18);
    }

    .ego-count {
        min-width: 24px;
        height: 20px;
        padding: 0 7px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        background: rgba(15, 23, 42, .07);
        color: inherit;
    }

    .ego-tab.active .ego-count {
        background: rgba(255, 255, 255, .24);
    }

    .ego-filter-grid {
        display: grid;
        grid-template-columns: minmax(220px, 1.5fr) minmax(145px, .7fr) minmax(145px, .7fr) minmax(160px, .8fr) 135px 135px 74px;
        gap: 8px;
        align-items: center;
    }

    .ego-input,
    .ego-select {
        height: 36px;
        width: 100%;
        border: 1px solid #dfe7f1;
        border-radius: 10px;
        background: #fff;
        color: #0f172a;
        padding: 0 12px;
        font-size: 13px;
        outline: none;
    }

    .ego-input:focus,
    .ego-select:focus {
        border-color: rgba(0, 168, 134, .65);
        box-shadow: 0 0 0 3px rgba(0, 168, 134, .10);
    }

    .ego-filter-btn {
        height: 36px;
        border: 0;
        border-radius: 10px;
        background: var(--ego-primary);
        color: #fff;
        font-weight: 900;
        font-size: 13px;
    }

    .ego-main-card {
        background: var(--ego-card);
        border: 1px solid var(--ego-line);
        border-radius: 18px;
        box-shadow: 0 16px 42px rgba(15, 23, 42, .055);
        overflow: hidden;
    }

    .ego-split {
        display: grid;
        grid-template-columns: minmax(560px, 43%) minmax(620px, 57%);
        min-height: 650px;
    }

    .ego-left {
        border-right: 1px solid var(--ego-line);
        min-width: 0;
        background: #fff;
    }

    .ego-list-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--ego-line);
        background: linear-gradient(135deg, #ffffff, #f8fffc);
    }

    .ego-list-title {
        margin: 0;
        font-size: 14px;
        font-weight: 950;
        color: var(--ego-text);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ego-list-note {
        color: #64748b;
        font-size: 11px;
        font-weight: 750;
    }

    .ego-staff-group-row td {
        padding: 0 !important;
        border-bottom: 1px solid #ddeee8 !important;
        background: #f1fffb !important;
        position: sticky;
        top: 37px;
        z-index: 2;
    }

    .ego-staff-toggle {
        width: 100%;
        min-height: 38px;
        border: 0;
        background: transparent;
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 12px;
        cursor: pointer;
        text-align: left;
    }

    .ego-staff-toggle-main {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
    }

    .ego-staff-toggle-icon {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        background: #d9fff4;
        color: #00856d;
        display: inline-grid;
        place-items: center;
        font-weight: 950;
        font-size: 10px;
    }

    .ego-staff-toggle-name {
        font-size: 12px;
        font-weight: 950;
        color: #0f172a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ego-staff-toggle-count {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #00856d;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .ego-staff-toggle.is-collapsed .bi-chevron-down {
        transform: rotate(-90deg);
    }

    .ego-staff-toggle .bi-chevron-down {
        transition: transform .15s ease;
    }

    .ego-row.is-hidden-by-staff {
        display: none;
    }

    .ego-right {
        min-width: 0;
        background: #fbfdff;
    }

    .ego-table-wrap {
        overflow: auto;
        max-height: calc(100vh - 265px);
    }

    .ego-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        font-size: 12px;
    }

    .ego-table thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: #f8fafc;
        color: #64748b;
        font-size: 10px;
        line-height: 1.15;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .035em;
        padding: 10px 8px;
        border-bottom: 1px solid var(--ego-line);
        white-space: nowrap;
    }

    .ego-table tbody td {
        padding: 10px 8px;
        border-bottom: 1px solid #eef3f8;
        vertical-align: middle;
        color: #1e293b;
    }

    .ego-row {
        cursor: pointer;
        transition: .14s ease;
    }

    .ego-row:hover {
        background: #f6fffc;
    }

    .ego-row.is-active {
        background: #f1fffb;
        box-shadow: inset 4px 0 0 var(--ego-primary);
    }

    .ego-check {
        width: 15px;
        height: 15px;
        accent-color: var(--ego-primary);
    }

    .ego-avatar {
        width: 28px;
        height: 28px;
        flex: 0 0 28px;
        border-radius: 50%;
        background: #d9fff4;
        color: var(--ego-primary-dark);
        display: grid;
        place-items: center;
        font-weight: 950;
        font-size: 11px;
    }

    .ego-customer-cell {
        display: flex;
        gap: 8px;
        align-items: center;
        min-width: 175px;
    }

    .ego-name {
        color: var(--ego-text);
        font-weight: 950;
        line-height: 1.2;
        margin-bottom: 2px;
    }

    .ego-code {
        color: #64748b;
        font-size: 10px;
        font-weight: 700;
    }

    .ego-staff {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 120px;
    }

    .ego-staff-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #eef2ff;
        display: grid;
        place-items: center;
        font-size: 10px;
        font-weight: 950;
        color: #3730a3;
    }

    .ego-money {
        font-weight: 900;
        color: #334155;
        white-space: nowrap;
    }

    .ego-date {
        color: #475569;
        white-space: nowrap;
        font-weight: 750;
    }

    .ego-badge {
        display: inline-flex;
        align-items: center;
        min-height: 20px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 950;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .ego-badge.blue {
        background: #eaf2ff;
        color: #1d4ed8;
        border-color: #cfe0ff;
    }

    .ego-badge.green {
        background: #e9fff7;
        color: #00856d;
        border-color: #c5f6e7;
    }

    .ego-badge.orange {
        background: #fff4e4;
        color: #b45309;
        border-color: #ffe0b2;
    }

    .ego-badge.yellow {
        background: #fff9db;
        color: #a16207;
        border-color: #fde68a;
    }

    .ego-badge.red {
        background: #fff1f2;
        color: #be123c;
        border-color: #fecdd3;
    }

    .ego-badge.gray {
        background: #f1f5f9;
        color: #475569;
        border-color: #e2e8f0;
    }

    .ego-pager {
        padding: 10px 12px;
        border-top: 1px solid var(--ego-line);
        background: #fff;
    }

    .detail-shell {
        height: 100%;
        max-height: calc(100vh - 220px);
        overflow: auto;
    }

    .detail-top {
        position: sticky;
        top: 0;
        z-index: 5;
        background: rgba(255, 255, 255, .96);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--ego-line);
        padding: 12px 14px;
    }

    .detail-breadcrumb {
        color: #64748b;
        font-size: 11px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    .detail-profile {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .detail-person {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .detail-avatar {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        background: #d9fff4;
        color: #00856d;
        font-weight: 950;
        font-size: 16px;
    }

    .detail-name {
        margin: 0;
        font-size: 19px;
        font-weight: 950;
        color: var(--ego-text);
        letter-spacing: -.02em;
    }

    .detail-code {
        color: #64748b;
        font-size: 12px;
        margin-top: 2px;
    }

    .detail-contact {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5px 18px;
        margin-top: 10px;
        color: #475569;
        font-size: 12px;
    }

    .detail-action-btn {
        width: 32px;
        height: 32px;
        border: 1px solid #e6edf5;
        background: #fff;
        color: #0f172a;
        border-radius: 10px;
        display: inline-grid;
        place-items: center;
        text-decoration: none;
    }

    .detail-metrics {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        background: #fff;
        border-bottom: 1px solid var(--ego-line);
    }

    .detail-metric {
        padding: 10px 12px;
        border-right: 1px solid var(--ego-line);
        min-width: 0;
    }

    .detail-metric:last-child {
        border-right: 0;
    }

    .metric-icon {
        width: 26px;
        height: 26px;
        border-radius: 9px;
        display: inline-grid;
        place-items: center;
        background: #e9fff7;
        color: #00856d;
        margin-bottom: 7px;
        font-size: 13px;
    }

    .metric-label {
        color: #64748b;
        font-size: 9px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .035em;
        white-space: nowrap;
    }

    .metric-value {
        color: var(--ego-text);
        font-size: 13px;
        font-weight: 950;
        margin-top: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .detail-tabs {
        display: flex;
        gap: 18px;
        align-items: center;
        padding: 0 14px;
        height: 42px;
        background: #fff;
        border-bottom: 1px solid var(--ego-line);
    }

    .detail-tab {
        height: 42px;
        display: inline-flex;
        align-items: center;
        color: #64748b;
        font-size: 12px;
        font-weight: 900;
        border-bottom: 2px solid transparent;
    }

    .detail-tab.active {
        color: var(--ego-primary-dark);
        border-color: var(--ego-primary);
    }

    .detail-body {
        padding: 12px;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        gap: 12px;
    }

    .info-card {
        background: #fff;
        border: 1px solid var(--ego-line);
        border-radius: 14px;
        padding: 12px;
        margin-bottom: 12px;
    }

    .info-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
        color: var(--ego-text);
        font-size: 13px;
        font-weight: 950;
    }

    .info-row {
        display: grid;
        grid-template-columns: 128px minmax(0, 1fr);
        gap: 10px;
        padding: 7px 0;
        border-bottom: 1px dashed #edf2f7;
        font-size: 12px;
    }

    .info-row:last-child {
        border-bottom: 0;
    }

    .info-label {
        color: #64748b;
        font-weight: 800;
    }

    .info-value {
        color: #0f172a;
        font-weight: 800;
        word-break: break-word;
    }

    .timeline-item {
        display: grid;
        grid-template-columns: 30px 1fr;
        gap: 8px;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .timeline-icon {
        width: 26px;
        height: 26px;
        border-radius: 9px;
        background: #e9fff7;
        color: #00856d;
        display: grid;
        place-items: center;
        font-size: 12px;
    }

    .timeline-title {
        font-size: 12px;
        font-weight: 950;
        color: #0f172a;
    }

    .timeline-text {
        color: #64748b;
        font-size: 11px;
        margin-top: 2px;
    }

    .project-card {
        display: grid;
        grid-template-columns: 76px 1fr auto;
        gap: 10px;
        align-items: center;
        background: #fff;
        border: 1px solid var(--ego-line);
        border-radius: 14px;
        padding: 10px;
    }

    .project-img {
        width: 76px;
        height: 56px;
        border-radius: 12px;
        background: linear-gradient(135deg, #dbeafe, #dcfce7);
        display: grid;
        place-items: center;
        color: #0f766e;
    }

    .empty-state {
        padding: 38px 18px;
        text-align: center;
        color: #64748b;
    }

    .detail-important-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 10px;
    }

    .detail-important-card {
        background: #fff;
        border: 1px solid #e2f4ee;
        border-radius: 14px;
        padding: 10px 12px;
    }

    .detail-important-card.is-hot {
        background: linear-gradient(135deg, #ecfdf5, #ffffff);
        border-color: #bdf3e4;
    }

    .detail-important-label {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #00856d;
        font-size: 10px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 5px;
    }

    .detail-important-value {
        color: #0f172a;
        font-size: 12px;
        font-weight: 850;
        line-height: 1.45;
        word-break: break-word;
    }

    .important-note-card {
        border-color: #dff3ed !important;
        background: linear-gradient(135deg, #ffffff, #fbfffd) !important;
    }

    .note-line {
        padding: 8px 0;
        border-bottom: 1px dashed #e5eef5;
    }

    .note-line:last-child {
        border-bottom: 0;
    }

    .note-label {
        color: #00856d;
        font-size: 10.5px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .035em;
        margin-bottom: 3px;
    }

    .note-content {
        color: #0f172a;
        font-size: 12px;
        font-weight: 800;
        line-height: 1.45;
        word-break: break-word;
    }

    @media (max-width: 1500px) {
        .ego-split {
            grid-template-columns: 1fr;
        }

        .ego-left {
            border-right: 0;
            border-bottom: 1px solid var(--ego-line);
        }

        .ego-table-wrap,
        .detail-shell {
            max-height: none;
        }
    }

    @media (max-width: 1100px) {
        .ego-filter-grid {
            grid-template-columns: 1fr 1fr;
        }

        .detail-grid,
        .detail-contact,
        .detail-important-grid {
            grid-template-columns: 1fr;
        }

        .detail-metrics {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 700px) {
        .ego-sales-page {
            padding: 10px;
        }

        .ego-header {
            flex-direction: column;
        }

        .ego-filter-grid {
            grid-template-columns: 1fr;
        }

        .ego-table {
            min-width: 850px;
        }
    }

/* EGO_DETAIL_COMPACT_V2_START */
.ego-split {
    grid-template-columns: minmax(520px, 42%) minmax(640px, 58%) !important;
}

.detail-shell {
    background: #f8fbfd !important;
    max-height: calc(100vh - 210px) !important;
}

.detail-top {
    padding: 10px 12px !important;
    background: #fff !important;
}

.detail-breadcrumb {
    margin-bottom: 8px !important;
    font-size: 11px !important;
}

.detail-profile {
    align-items: flex-start !important;
}

.detail-person {
    align-items: flex-start !important;
}

.detail-avatar {
    width: 42px !important;
    height: 42px !important;
    flex: 0 0 42px !important;
    border-radius: 14px !important;
    font-size: 15px !important;
}

.detail-name {
    font-size: 18px !important;
    line-height: 1.15 !important;
}

.detail-code {
    font-size: 11px !important;
}

.detail-contact {
    grid-template-columns: 1fr !important;
    gap: 4px !important;
    margin-top: 8px !important;
    font-size: 11px !important;
    padding-left: 54px;
}

.detail-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    flex: 0 0 auto;
}

.detail-edit-btn {
    height: 32px;
    padding: 0 12px;
    border-radius: 10px;
    border: 0;
    background: #00a886;
    color: #fff !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 950;
    text-decoration: none;
    box-shadow: 0 8px 18px rgba(0, 168, 134, .18);
}

.detail-action-btn {
    width: 32px !important;
    height: 32px !important;
    border-radius: 10px !important;
}

.detail-metrics {
    grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
}

.detail-metric {
    padding: 8px 10px !important;
}

.metric-icon {
    width: 24px !important;
    height: 24px !important;
    margin-bottom: 5px !important;
    font-size: 12px !important;
}

.metric-label {
    font-size: 8.5px !important;
}

.metric-value {
    font-size: 12px !important;
}

.detail-tabs {
    height: 36px !important;
    gap: 14px !important;
    padding: 0 12px !important;
    overflow-x: auto;
}

.detail-tab {
    height: 36px !important;
    font-size: 11px !important;
    white-space: nowrap;
}

.detail-body {
    padding: 10px !important;
}

.detail-grid {
    grid-template-columns: 1fr 1fr !important;
    gap: 10px !important;
}

.info-card {
    border-radius: 13px !important;
    padding: 10px !important;
    margin-bottom: 10px !important;
    box-shadow: 0 8px 22px rgba(15, 23, 42, .035);
}

.info-title {
    font-size: 12px !important;
    margin-bottom: 8px !important;
}

.info-title .edit-mini {
    height: 26px;
    padding: 0 9px;
    border-radius: 8px;
    background: #f1fffb;
    color: #00856d;
    border: 1px solid #bdf3e4;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 950;
    text-decoration: none;
}

.info-row {
    grid-template-columns: 112px minmax(0, 1fr) !important;
    gap: 8px !important;
    padding: 5px 0 !important;
    font-size: 11px !important;
}

.info-label {
    color: #718096 !important;
    font-weight: 850 !important;
}

.info-value {
    font-weight: 850 !important;
    color: #111827 !important;
}

.timeline-item {
    grid-template-columns: 26px 1fr !important;
    gap: 7px !important;
    padding: 7px 0 !important;
}

.timeline-icon {
    width: 24px !important;
    height: 24px !important;
    border-radius: 8px !important;
    font-size: 11px !important;
}

.timeline-title {
    font-size: 11px !important;
}

.timeline-text {
    font-size: 10.5px !important;
}

.project-card {
    grid-template-columns: 58px 1fr auto !important;
    padding: 8px !important;
    border-radius: 12px !important;
}

.project-img {
    width: 58px !important;
    height: 46px !important;
    border-radius: 10px !important;
}

.customer-summary-box {
    border: 1px solid #e8eef5;
    border-radius: 12px;
    background: linear-gradient(135deg, #ffffff, #f6fffc);
    padding: 9px 10px;
    margin-bottom: 10px;
}

.customer-summary-title {
    font-size: 12px;
    font-weight: 950;
    color: #0f172a;
    margin-bottom: 5px;
}

.customer-summary-text {
    font-size: 11px;
    color: #475569;
    line-height: 1.45;
}

@media (max-width: 1500px) {
    .ego-split {
        grid-template-columns: 1fr !important;
    }

    .detail-shell {
        max-height: none !important;
    }
}

@media (max-width: 1000px) {
    .detail-grid {
        grid-template-columns: 1fr !important;
    }

    .detail-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .detail-contact {
        padding-left: 0;
    }
}
/* EGO_DETAIL_COMPACT_V2_END */




/* EGO_REPORT_MODAL_CSS_START */
.detail-tab {
    cursor: pointer !important;
    user-select: none;
}

.detail-tab:hover {
    color: #00856d !important;
}

.ego-report-modal[hidden] {
    display: none !important;
}

.ego-report-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
}

.ego-report-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .52);
    backdrop-filter: blur(7px);
}

.ego-report-modal-dialog {
    position: relative;
    z-index: 2;
    width: min(960px, calc(100vw - 32px));
    max-height: calc(100vh - 32px);
    margin: 16px auto;
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 28px 80px rgba(15, 23, 42, .30);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ego-report-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 16px;
    background: linear-gradient(120deg, #ffffff, #effff9);
    border-bottom: 1px solid #e6edf5;
}

.ego-report-modal-kicker {
    color: #00a886;
    font-size: 10px;
    font-weight: 950;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 3px;
}

.ego-report-modal-title {
    margin: 0;
    color: #0f172a;
    font-size: 21px;
    font-weight: 950;
    letter-spacing: -.035em;
    line-height: 1.1;
}

.ego-report-modal-subtitle {
    margin-top: 3px;
    color: #64748b;
    font-size: 12px;
}

.ego-report-modal-close {
    width: 36px;
    height: 36px;
    border: 1px solid #dbe4ef;
    background: #fff;
    color: #0f172a;
    border-radius: 13px;
    display: grid;
    place-items: center;
    font-size: 18px;
    cursor: pointer;
}

.ego-popup-tabs {
    display: flex;
    gap: 8px;
    padding: 10px 16px;
    background: #fff;
    border-bottom: 1px solid #e6edf5;
    overflow-x: auto;
}

.ego-popup-tab {
    height: 32px;
    border: 1px solid #dbe4ef;
    background: #f8fafc;
    color: #475569;
    border-radius: 999px;
    padding: 0 13px;
    font-size: 12px;
    font-weight: 950;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
}

.ego-popup-tab.active {
    background: #00a886;
    border-color: #00a886;
    color: #fff;
    box-shadow: 0 8px 18px rgba(0, 168, 134, .18);
}

.ego-report-modal-body {
    padding: 14px;
    overflow: auto;
    background: #f7fafc;
}

.ego-form-alert {
    display: none;
    padding: 10px 12px;
    border-radius: 14px;
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #be123c;
    font-size: 13px;
    font-weight: 750;
    margin-bottom: 10px;
}

.ego-popup-loading {
    display: none;
    align-items: center;
    gap: 8px;
    color: #00856d;
    font-size: 13px;
    font-weight: 900;
    margin-bottom: 10px;
}

.ego-popup-loading.show {
    display: flex;
}

.ego-popup-form {
    display: block;
}

.ego-popup-section {
    display: none;
    background: #fff;
    border: 1px solid #e8eef5;
    border-radius: 18px;
    padding: 14px;
    box-shadow: 0 12px 26px rgba(15, 23, 42, .045);
}

.ego-popup-section.active {
    display: block;
}

.ego-popup-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-size: 14px;
    font-weight: 950;
    margin-bottom: 12px;
}

.ego-popup-section-title i {
    width: 28px;
    height: 28px;
    display: inline-grid;
    place-items: center;
    border-radius: 10px;
    background: #e9fff7;
    color: #00856d;
}

.ego-popup-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 11px;
}

.ego-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
}

.ego-field.span-12 { grid-column: span 12; }
.ego-field.span-8 { grid-column: span 8; }
.ego-field.span-6 { grid-column: span 6; }
.ego-field.span-4 { grid-column: span 4; }
.ego-field.span-3 { grid-column: span 3; }

.ego-field label {
    margin: 0;
    color: #475569;
    font-size: 11px;
    font-weight: 900;
}

.ego-field label .req {
    color: #ef4444;
}

.ego-field input,
.ego-field select,
.ego-field textarea {
    width: 100% !important;
    max-width: 100% !important;
    border: 1px solid #dbe4ef;
    background: #fff;
    color: #0f172a;
    border-radius: 12px;
    min-height: 38px;
    padding: 8px 11px;
    font-size: 13px;
    font-weight: 650;
    outline: none;
}

.ego-field textarea {
    min-height: 90px;
    resize: vertical;
}

.ego-field input:focus,
.ego-field select:focus,
.ego-field textarea:focus {
    border-color: rgba(0, 168, 134, .75);
    box-shadow: 0 0 0 4px rgba(0, 168, 134, .10);
}

.ego-popup-footer {
    position: sticky;
    bottom: -14px;
    margin: 14px -14px -14px;
    padding: 12px 14px;
    background: rgba(255, 255, 255, .96);
    border-top: 1px solid #e8eef5;
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: space-between;
    gap: 9px;
}

.ego-popup-left-actions,
.ego-popup-right-actions {
    display: flex;
    gap: 9px;
    align-items: center;
}

.ego-popup-btn {
    height: 38px;
    border-radius: 13px;
    padding: 0 15px;
    border: 0;
    font-size: 13px;
    font-weight: 950;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
}

.ego-popup-btn-cancel {
    background: #f1f5f9;
    color: #334155;
}

.ego-popup-btn-next,
.ego-popup-btn-prev {
    background: #ecfdf5;
    color: #00856d;
}

.ego-popup-btn-save {
    background: #00a886;
    color: #fff;
    box-shadow: 0 10px 24px rgba(0, 168, 134, .22);
}

.ego-popup-btn-save:disabled {
    opacity: .65;
    cursor: wait;
}

body.ego-modal-open {
    overflow: hidden;
}

@media (max-width: 900px) {
    .ego-report-modal-dialog {
        width: calc(100vw - 16px);
        margin: 8px auto;
        max-height: calc(100vh - 16px);
        border-radius: 18px;
    }

    .ego-popup-grid {
        grid-template-columns: 1fr;
    }

    .ego-field.span-12,
    .ego-field.span-8,
    .ego-field.span-6,
    .ego-field.span-4,
    .ego-field.span-3 {
        grid-column: span 1;
    }

    .ego-popup-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .ego-popup-left-actions,
    .ego-popup-right-actions {
        justify-content: flex-end;
    }
}
/* EGO_REPORT_MODAL_CSS_END */


/* EGO_CLEAN_DETAIL_TABS_START */
.detail-tabs {
    height: 42px !important;
    display: flex !important;
    align-items: flex-end !important;
    gap: 2px !important;
    padding: 0 12px !important;
    background: #fff !important;
    border-top: 1px solid #eef2f7 !important;
    border-bottom: 1px solid #e8eef5 !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
}

.detail-tab {
    appearance: none !important;
    -webkit-appearance: none !important;
    height: 42px !important;
    min-width: auto !important;
    margin: 0 !important;
    padding: 0 12px !important;
    border: 0 !important;
    border-bottom: 2px solid transparent !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    outline: none !important;
    color: #64748b !important;
    font-size: 12px !important;
    font-weight: 900 !important;
    line-height: 42px !important;
    white-space: nowrap !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.detail-tab:hover {
    color: #00856d !important;
    background: #f7fffc !important;
}

.detail-tab.active {
    color: #00856d !important;
    background: transparent !important;
    border-bottom-color: #00a886 !important;
}

.detail-tab:focus,
.detail-tab:active {
    outline: none !important;
    box-shadow: none !important;
}

.detail-body {
    background: #f8fbfd !important;
    padding-top: 10px !important;
}

.customer-summary-box {
    border-radius: 12px !important;
    border: 1px solid #dff3ed !important;
    background: #fbfffd !important;
    padding: 10px 12px !important;
    margin-bottom: 10px !important;
}

.customer-summary-title {
    font-size: 12px !important;
    font-weight: 950 !important;
    color: #0f766e !important;
    margin-bottom: 4px !important;
}

.customer-summary-text {
    font-size: 11.5px !important;
    line-height: 1.45 !important;
    color: #475569 !important;
}

.info-card {
    border-radius: 13px !important;
    border: 1px solid #e8eef5 !important;
    box-shadow: none !important;
}

.info-title {
    font-size: 12px !important;
    padding-bottom: 7px !important;
    margin-bottom: 8px !important;
    border-bottom: 1px solid #f1f5f9 !important;
}

.edit-mini {
    height: 24px !important;
    padding: 0 8px !important;
    font-size: 10.5px !important;
    border-radius: 8px !important;
}
/* EGO_CLEAN_DETAIL_TABS_END */

</style>

<div class="ego-sales-page">
    <div class="ego-header">
        <div>
            <h1 class="ego-title">Khách hàng</h1>
            <div class="ego-subtitle">Chăm sóc, follow-up và theo dõi pipeline khách hàng</div>
        </div>

        <div class="ego-actions">
            <a class="ego-btn ego-btn-light" href="{{ route('sales.work-reports.export', request()->query()) }}">
                <i class="bi bi-download"></i> Xuất CSV
            </a>
            <a class="ego-btn ego-btn-primary" href="{{ route('sales.work-reports.create') }}">
                <i class="bi bi-plus-lg"></i> Thêm data chăm sóc
            </a>
        </div>
    </div>

    @include('customers._module_nav')

    <div class="ego-toolbar">
        <div class="ego-tabs">
            <a class="ego-tab {{ !request('customer_type') && !request('status') && !request('quick') && !request('period') ? 'active' : '' }}" href="{{ route('customers.pipeline') }}">
                Tất cả <span class="ego-count">{{ $reports->total() }}</span>
            </a>
            <a class="ego-tab {{ request('customer_type') === 'turnkey' ? 'active' : '' }}" href="{{ route('customers.pipeline', ['customer_type' => 'turnkey']) }}">
                Khách lắp đặt trọn gói
            </a>
            <a class="ego-tab {{ request('customer_type') === 'dealer' ? 'active' : '' }}" href="{{ route('customers.pipeline', ['customer_type' => 'dealer']) }}">
                Đại lý sản phẩm
            </a>
            <a class="ego-tab {{ request('status') === 'consulting' ? 'active' : '' }}" href="{{ route('customers.pipeline', ['status' => 'consulting']) }}">
                Đang chăm sóc
            </a>
            <a class="ego-tab {{ request('quick') === 'quote_sent' ? 'active' : '' }}" href="{{ route('customers.pipeline', ['quick' => 'quote_sent']) }}">
                Đã gửi báo giá <span class="ego-count">{{ $stats['quote_sent'] ?? 0 }}</span>
            </a>
            <a class="ego-tab {{ request('status') === 'won' ? 'active' : '' }}" href="{{ route('customers.pipeline', ['status' => 'won']) }}">
                Đã chốt
            </a>
            <a class="ego-tab {{ request('status') === 'new' ? 'active' : '' }}" href="{{ route('customers.pipeline', ['status' => 'new']) }}">
                Khách hàng cũ
            </a>
        </div>

        <form method="GET" class="ego-filter-grid">
            <input class="ego-input" name="q" value="{{ request('q') }}" placeholder="Tìm tên, SĐT, email, địa chỉ...">

            <select class="ego-select" name="customer_type">
                <option value="">Loại khách hàng</option>
                @foreach($customerTypes as $key => $label)
                    <option value="{{ $key }}" @selected(request('customer_type') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="ego-select" name="status">
                <option value="">Trạng thái</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            @if($canManage)
                <select class="ego-select" name="assigned_to">
                    <option value="">Nhân viên phụ trách</option>
                    @foreach($salesUsers as $u)
                        <option value="{{ $u->id }}" @selected((string) request('assigned_to') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="assigned_to" value="{{ request('assigned_to') }}">
            @endif

            <input type="date" class="ego-input" name="from" value="{{ request('from') }}">
            <input type="date" class="ego-input" name="to" value="{{ request('to') }}">

            <button class="ego-filter-btn" type="submit">
                <i class="bi bi-funnel"></i> Lọc
            </button>
        </form>
    </div>

    <div class="ego-main-card">
        <div class="ego-split">
            <section class="ego-left">
                <div class="ego-list-head">
                    <div>
                        <h2 class="ego-list-title">
                            <i class="bi bi-people text-success"></i> Danh sách khách hàng
                        </h2>
                        <div class="ego-list-note">Bấm tên nhân viên để ẩn/hiện danh sách khách hàng phụ trách.</div>
                    </div>
                    <div class="ego-list-note">{{ $reports->total() }} khách</div>
                </div>

                <div class="ego-table-wrap">
                    <table class="ego-table">
                        <thead>
                            <tr>
                                <th style="width:34px"><input class="ego-check" type="checkbox" disabled></th>
                                <th>Khách hàng</th>
                                <th>Loại khách</th>
                                <th>Trạng thái</th>
                                <th>Giá trị tiềm năng</th>
                                <th>Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportGroups as $salesName => $staffReports)
                                @php
                                    $staffKey = md5((string) $salesName);
                                    $firstInitial = mb_strtoupper(mb_substr((string) $salesName ?: 'NV', 0, 1));
                                @endphp

                                <tr class="ego-staff-group-row" data-staff-key="{{ $staffKey }}">
                                    <td colspan="6">
                                        <button type="button" class="ego-staff-toggle is-collapsed" data-staff-key="{{ $staffKey }}" aria-expanded="false">
                                            <span class="ego-staff-toggle-main">
                                                <span class="ego-staff-toggle-icon">{{ $firstInitial }}</span>
                                                <span>
                                                    <span class="ego-staff-toggle-name">{{ $salesName }}</span>
                                                    <span class="ego-list-note d-block">Tên nhân viên phụ trách</span>
                                                </span>
                                            </span>
                                            <span class="ego-staff-toggle-count">
                                                {{ $staffReports->count() }} khách hàng
                                                <i class="bi bi-chevron-down"></i>
                                            </span>
                                        </button>
                                    </td>
                                </tr>

                                @foreach($staffReports as $report)
                                    <tr class="ego-row is-hidden-by-staff" data-id="{{ $report->id }}" data-staff-key="{{ $staffKey }}">
                                        <td><input class="ego-check" type="checkbox"></td>
                                        <td>
                                            <div class="ego-customer-cell">
                                                <div class="ego-avatar">
                                                    {{ mb_strtoupper(mb_substr($report->customer_name ?: 'KH', 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="ego-name">{{ $report->customer_name ?: '—' }}</div>
                                                    <div class="ego-code">KH-{{ str_pad($report->id, 6, '0', STR_PAD_LEFT) }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ego-badge {{ $typeClass($report->customer_type) }}">
                                                {{ $typeLabel($report->customer_type) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ego-badge {{ $statusClass($report->status) }}">
                                                {{ $statusLabel($report->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ego-money">{{ $money($report->revenue_expectation) }}</span>
                                        </td>
                                        <td>
                                            <span class="ego-date">{{ $date($report->created_at) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                            Chưa có dữ liệu phù hợp.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="ego-pager">
                    {{ $reports->links() }}
                </div>
            </section>

            <aside class="ego-right">
                <div class="detail-shell" id="customerDetail">
                    <div class="empty-state">
                        <div class="spinner-border text-success mb-3" role="status"></div>
                        <div class="fw-bold">Đang tải chi tiết khách hàng...</div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>



{{-- EGO_REPORT_MODAL_HTML_START --}}
<div class="ego-report-modal" id="egoReportModal" hidden>
    <div class="ego-report-modal-backdrop" data-ego-close-modal></div>

    <div class="ego-report-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="egoReportModalTitle">
        <div class="ego-report-modal-head">
            <div>
                <div class="ego-report-modal-kicker">CRM Sales</div>
                <h2 class="ego-report-modal-title" id="egoReportModalTitle">Thêm khách hàng</h2>
                <div class="ego-report-modal-subtitle" id="egoReportModalSubtitle">
                    Nhập nhanh thông tin khách hàng, trạng thái chăm sóc và nhu cầu.
                </div>
            </div>

            <button type="button" class="ego-report-modal-close" data-ego-close-modal aria-label="Đóng">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="ego-popup-tabs">
            <button type="button" class="ego-popup-tab active" data-popup-tab="customer">
                <i class="bi bi-person-vcard"></i> Khách hàng
            </button>
            <button type="button" class="ego-popup-tab" data-popup-tab="care">
                <i class="bi bi-graph-up-arrow"></i> Chăm sóc
            </button>
            <button type="button" class="ego-popup-tab" data-popup-tab="quote">
                <i class="bi bi-telephone-outbound"></i> Gọi & báo giá
            </button>
            <button type="button" class="ego-popup-tab" data-popup-tab="need">
                <i class="bi bi-chat-square-text"></i> Nhu cầu
            </button>
        </div>

        <div class="ego-report-modal-body">
            <div class="ego-popup-loading" id="egoPopupLoading">
                <span class="spinner-border spinner-border-sm"></span>
                Đang tải dữ liệu...
            </div>

            <div class="ego-form-alert" id="egoFormAlert"></div>

            <form id="egoReportForm" class="ego-popup-form" method="POST" action="{{ route('sales.work-reports.store') }}">
                @csrf
                <input type="hidden" name="_method" id="egoReportMethod" value="POST">

                <div class="ego-popup-section active" data-popup-panel="customer">
                    <div class="ego-popup-section-title">
                        <i class="bi bi-person-vcard"></i>
                        Thông tin khách hàng
                    </div>

                    <div class="ego-popup-grid">
                        <div class="ego-field span-4">
                            <label>Họ và tên <span class="req">*</span></label>
                            <input name="customer_name" required placeholder="VD: Huỳnh Bá Thụy">
                        </div>

                        <div class="ego-field span-4">
                            <label>Số điện thoại</label>
                            <input name="customer_phone" placeholder="090...">
                        </div>

                        <div class="ego-field span-4">
                            <label>Email</label>
                            <input type="email" name="customer_email" placeholder="email@gmail.com">
                        </div>

                        <div class="ego-field span-4">
                            <label>Công ty</label>
                            <input name="customer_company" placeholder="Tên công ty">
                        </div>

                        <div class="ego-field span-4">
                            <label>Loại khách <span class="req">*</span></label>
                            <select name="customer_type" required>
                                @foreach($customerTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-4">
                            <label>Nguồn khách</label>
                            <select name="data_source_id">
                                <option value="">Chưa chọn</option>
                                @foreach($sources as $source)
                                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-6">
                            <label>Địa chỉ</label>
                            <input name="customer_address" placeholder="Địa chỉ khách hàng">
                        </div>

                        <div class="ego-field span-6">
                            <label>Khu vực / ghi chú địa chỉ</label>
                            <input name="region_text" placeholder="VD: Đức Huệ, Long An">
                        </div>

                        <div class="ego-field span-4">
                            <label>Facebook name</label>
                            <input name="facebook_name">
                        </div>

                        <div class="ego-field span-4">
                            <label>Facebook link</label>
                            <input name="facebook_link">
                        </div>

                        <div class="ego-field span-4">
                            <label>Zalo</label>
                            <input name="zalo_id">
                        </div>
                    </div>
                </div>

                <div class="ego-popup-section" data-popup-panel="care">
                    <div class="ego-popup-section-title">
                        <i class="bi bi-graph-up-arrow"></i>
                        Trạng thái chăm sóc
                    </div>

                    <div class="ego-popup-grid">
                        @if($canManage)
                            <div class="ego-field span-4">
                                <label>Nhân viên phụ trách</label>
                                <select name="assigned_to">
                                    <option value="">Tự động / người tạo</option>
                                    @foreach($salesUsers as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="ego-field span-4">
                            <label>Trạng thái <span class="req">*</span></label>
                            <select name="status" required>
                                @foreach($statuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-4">
                            <label>Giai đoạn khách</label>
                            <select name="customer_stage">
                                <option value="">Chưa chọn</option>
                                @foreach($customerStages as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-3">
                            <label>Độ nóng <span class="req">*</span></label>
                            <select name="priority" required>
                                @foreach($priorities as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-3">
                            <label>Kênh liên hệ <span class="req">*</span></label>
                            <select name="contact_channel" required>
                                @foreach($channels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-3">
                            <label>Ngày nhận data</label>
                            <input type="datetime-local" name="data_received_at">
                        </div>

                        <div class="ego-field span-3">
                            <label>Follow-up tiếp theo</label>
                            <input type="datetime-local" name="next_followup_at">
                        </div>

                        <div class="ego-field span-12">
                            <label>Hành động tiếp theo</label>
                            <input name="next_action" placeholder="VD: Gọi xác nhận nhu cầu và tư vấn giải pháp điện mặt trời">
                        </div>
                    </div>
                </div>

                <div class="ego-popup-section" data-popup-panel="quote">
                    <div class="ego-popup-section-title">
                        <i class="bi bi-telephone-outbound"></i>
                        Gọi điện & báo giá
                    </div>

                    <div class="ego-popup-grid">
                        <div class="ego-field span-3">
                            <label>Gọi lần 1</label>
                            <input type="datetime-local" name="call_1_at">
                        </div>

                        <div class="ego-field span-3">
                            <label>Kết quả gọi lần 1</label>
                            <select name="call_1_result">
                                <option value="">Chưa chọn</option>
                                @foreach($callResults as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-3">
                            <label>Gọi lần 2</label>
                            <input type="datetime-local" name="call_2_at">
                        </div>

                        <div class="ego-field span-3">
                            <label>Kết quả gọi lần 2</label>
                            <select name="call_2_result">
                                <option value="">Chưa chọn</option>
                                @foreach($callResults as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-3">
                            <label>Trạng thái báo giá</label>
                            <select name="quote_status">
                                <option value="">Chưa chọn</option>
                                @foreach($quoteStatuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field span-3">
                            <label>Ngày gửi báo giá</label>
                            <input type="datetime-local" name="quote_sent_at">
                        </div>

                        <div class="ego-field span-3">
                            <label>Công suất dự kiến</label>
                            <input type="number" step="0.01" min="0" name="system_size_kw" placeholder="VD: 5">
                        </div>

                        <div class="ego-field span-3">
                            <label>Giá trị tiềm năng</label>
                            <input type="number" step="1000" min="0" name="revenue_expectation" placeholder="VD: 210000000">
                        </div>

                        <div class="ego-field span-4">
                            <label>Liên hệ cuối</label>
                            <input type="datetime-local" name="last_contact_at">
                        </div>

                        <div class="ego-field span-4">
                            <label>Kết quả / outcome</label>
                            <select name="outcome">
                                <option value="">Chưa chọn</option>
                                @foreach($outcomes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="ego-popup-section" data-popup-panel="need">
                    <div class="ego-popup-section-title">
                        <i class="bi bi-chat-square-text"></i>
                        Nhu cầu & ghi chú tư vấn
                    </div>

                    <div class="ego-popup-grid">
                        <div class="ego-field span-4">
                            <label>Ngân sách</label>
                            <input name="budget_range" placeholder="VD: 70 - 100 triệu">
                        </div>

                        <div class="ego-field span-4">
                            <label>Thời gian muốn lắp</label>
                            <input name="project_timeline" placeholder="VD: Trong tháng này">
                        </div>

                        <div class="ego-field span-4">
                            <label>Sản phẩm / cấu hình đã báo giá</label>
                            <input name="quoted_products" placeholder="VD: Inverter, pin, lưu trữ...">
                        </div>

                        <div class="ego-field span-12">
                            <label>Nhu cầu khách hàng</label>
                            <textarea name="customer_need" placeholder="Mô tả nhu cầu, điện trung bình, mục tiêu lắp đặt..."></textarea>
                        </div>

                        <div class="ego-field span-12">
                            <label>Tóm tắt tư vấn</label>
                            <textarea name="consultation_summary" placeholder="Nội dung đã tư vấn, hướng xử lý, cấu hình đề xuất..."></textarea>
                        </div>

                        <div class="ego-field span-6">
                            <label>Phản hồi khách hàng</label>
                            <textarea name="customer_feedback"></textarea>
                        </div>

                        <div class="ego-field span-6">
                            <label>Lý do mất khách nếu có</label>
                            <textarea name="lost_reason"></textarea>
                        </div>

                        <div class="ego-field span-12">
                            <label>Link bằng chứng / tài liệu</label>
                            <textarea name="proof_links" placeholder="Mỗi link một dòng"></textarea>
                        </div>
                    </div>
                </div>

                <div class="ego-popup-footer">
                    <div class="ego-popup-left-actions">
                        <button type="button" class="ego-popup-btn ego-popup-btn-cancel" data-ego-close-modal>
                            <i class="bi bi-x-circle"></i> Hủy
                        </button>
                    </div>

                    <div class="ego-popup-right-actions">
                        <button type="button" class="ego-popup-btn ego-popup-btn-prev" id="egoPopupPrevBtn">
                            <i class="bi bi-chevron-left"></i> Trước
                        </button>
                        <button type="button" class="ego-popup-btn ego-popup-btn-next" id="egoPopupNextBtn">
                            Tiếp <i class="bi bi-chevron-right"></i>
                        </button>
                        <button type="submit" class="ego-popup-btn ego-popup-btn-save" id="egoReportSaveBtn">
                            <i class="bi bi-check2-circle"></i> Lưu khách hàng
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- EGO_REPORT_MODAL_HTML_END --}}

@endsection

@push('scripts')
<script>
(function () {
    const detailBox = document.getElementById('customerDetail');
    const rows = Array.from(document.querySelectorAll('.ego-row'));
    const urlTemplate = @json(url('/sales/work-reports/__ID__/detail-json'));

    function esc(value) {
        if (value === null || value === undefined || value === '') return '—';
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function nl(value) {
        return esc(value).replace(/\n/g, '<br>');
    }

    function initials(name) {
        name = String(name || 'KH').trim();
        const parts = name.split(/\s+/);
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function item(label, value) {
        return `
            <div class="info-row">
                <div class="info-label">${esc(label)}</div>
                <div class="info-value">${nl(value)}</div>
            </div>
        `;
    }

    function timelineHtml(items) {
        if (!items || !items.length) {
            return '<div class="timeline-text">Chưa có lịch sử liên hệ.</div>';
        }

        return items.map(item => `
            <div class="timeline-item">
                <div class="timeline-icon"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="timeline-title">${esc(item.status_label)} · ${esc(item.customer_stage_label)}</div>
                    <div class="timeline-text">${esc(item.updated_at_text)} · ${esc(item.sales_name)}</div>
                    <div class="timeline-text">Gọi: ${esc(item.call_1_result_label)} · Báo giá: ${esc(item.quote_status_label)}</div>
                </div>
            </div>
        `).join('');
    }

    function followupHtml(items) {
        if (!items || !items.length) {
            return '<div class="timeline-text">Chưa có ghi chú follow-up trong bảng mới.</div>';
        }

        return items.map(item => `
            <div class="timeline-item">
                <div class="timeline-icon"><i class="bi bi-chat-dots"></i></div>
                <div>
                    <div class="timeline-title">${esc(item.title || item.action_type)}</div>
                    <div class="timeline-text">${esc(item.created_at_text)} · ${esc(item.creator_name)}</div>
                    <div class="timeline-text">${nl(item.content)}</div>
                    <div class="timeline-text text-success fw-bold">Hẹn xử lý: ${esc(item.followup_at_text)}</div>
                </div>
            </div>
        `).join('');
    }

    function render(payload) {
        const r = payload.report || {};
        const history = payload.history || [];
        const followups = payload.followups || [];
        const code = 'KH-' + String(r.id || '').padStart(6, '0');
        const showUrl = `{{ url('/sales/work-reports') }}/${esc(r.id)}`;
        const editUrl = `{{ url('/sales/work-reports') }}/${esc(r.id)}/sua`;

        detailBox.innerHTML = `
            <div class="detail-top">
                <div class="detail-breadcrumb">
                    <span>Khách hàng / <b>Chi tiết khách hàng</b></span>
                    <span class="detail-actions">
                        <a class="detail-edit-btn" href="${editUrl}">
                            <i class="bi bi-pencil-square"></i> Sửa khách
                        </a>
                        <a class="detail-action-btn" href="${showUrl}" title="Mở trang chi tiết">
                            <i class="bi bi-arrows-angle-expand"></i>
                        </a>
                    </span>
                </div>

                <div class="detail-profile">
                    <div class="detail-person">
                        <div class="detail-avatar">${esc(initials(r.customer_name))}</div>
                        <div>
                            <h2 class="detail-name">${esc(r.customer_name)}</h2>
                            <div class="detail-code">${esc(code)}</div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="ego-badge green">${esc(r.customer_type_label)}</span>
                                <span class="ego-badge blue">${esc(r.status_label)}</span>
                                <span class="ego-badge orange">${esc(r.priority_label)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-contact">
                    <div><i class="bi bi-telephone text-success"></i> <b>${esc(r.customer_phone)}</b></div>
                    <div><i class="bi bi-envelope text-primary"></i> ${esc(r.customer_email)}</div>
                    <div><i class="bi bi-geo-alt text-danger"></i> ${esc(r.customer_address || r.region_text)}</div>
                </div>
            </div>

            <div class="detail-metrics">
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-cash-stack"></i></div>
                    <div class="metric-label">Tổng giá trị</div>
                    <div class="metric-value">${esc(r.revenue_expectation_text)}</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-lightning"></i></div>
                    <div class="metric-label">Công suất</div>
                    <div class="metric-value">${esc(r.system_size_kw || '—')} kWp</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-file-earmark-check"></i></div>
                    <div class="metric-label">Báo giá</div>
                    <div class="metric-value">${esc(r.quote_status_label)}</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-calendar2-check"></i></div>
                    <div class="metric-label">Follow-up</div>
                    <div class="metric-value">${esc(r.next_followup_at_text)}</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-telephone"></i></div>
                    <div class="metric-label">Liên hệ cuối</div>
                    <div class="metric-value">${esc(r.last_contact_at_text)}</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-person-check"></i></div>
                    <div class="metric-label">Sales</div>
                    <div class="metric-value">${esc(r.sales_name)}</div>
                </div>
            </div>

            <div class="detail-tabs">
                <div class="detail-tab active">Thông tin</div>
                <div class="detail-tab">Nhu cầu</div>
                <div class="detail-tab">Hệ thống</div>
                <div class="detail-tab">Liên hệ</div>
                <div class="detail-tab">Ghi chú</div>
            </div>

            <div class="detail-body">
                <div class="customer-summary-box">
                    <div class="customer-summary-title">
                        <i class="bi bi-stars text-success"></i> Tóm tắt chăm sóc
                    </div>
                    <div class="customer-summary-text">
                        <b>${esc(r.customer_name)}</b> - ${esc(r.customer_type_label)}.
                        Trạng thái: <b>${esc(r.status_label || 'Chưa cập nhật')}</b>.
                        Giai đoạn: <b>${esc(r.customer_stage_label || 'Chưa cập nhật')}</b>.
                        Báo giá: <b>${esc(r.quote_status_label || 'Chưa gửi')}</b>.
                        Việc tiếp theo: <b>${esc(r.next_action || 'chưa có')}</b>.
                    </div>
                </div>

                <div class="detail-grid">
                    <div>
                        <div class="info-card">
                            <div class="info-title">
                                <span><i class="bi bi-person-vcard text-success"></i> Thông tin nhanh</span>
                                <a class="edit-mini" href="${editUrl}">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>
                            </div>
                            ${item('Khách hàng', r.customer_name)}
                            ${item('Điện thoại', r.customer_phone)}
                            ${item('Email', r.customer_email)}
                            ${item('Khu vực', r.customer_address || r.region_text)}
                            ${item('Nguồn khách', r.source_name)}
                        </div>

                        <div class="info-card">
                            <div class="info-title">
                                <span><i class="bi bi-lightning-charge text-warning"></i> Nhu cầu & tư vấn</span>
                                <a class="edit-mini" href="${editUrl}">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>
                            </div>
                            ${item('Nhu cầu chính', r.customer_need)}
                            ${item('Ngân sách dự kiến', r.budget_range)}
                            ${item('Thời gian dự kiến', r.project_timeline)}
                            ${item('Sản phẩm / báo giá', r.quoted_products)}
                            ${item('Phản hồi khách', r.customer_feedback)}
                            ${item('Ghi chú tư vấn', r.consultation_summary)}
                        </div>

                        <div class="info-card">
                            <div class="info-title">
                                <span><i class="bi bi-house-gear text-primary"></i> Dự án liên quan</span>
                                <a class="edit-mini" href="${showUrl}">
                                    <i class="bi bi-eye"></i> Xem
                                </a>
                            </div>
                            <div class="project-card">
                                <div class="project-img"><i class="bi bi-house fs-5"></i></div>
                                <div>
                                    <div class="fw-bold small">${esc(r.system_size_kw ? ('Hệ thống ' + r.system_size_kw + ' kWp') : 'Chưa nhập thông tin hệ thống')}</div>
                                    <div class="timeline-text">Data #${esc(r.id)} · ${esc(r.system_size_kw || '—')} kWp</div>
                                    <div class="timeline-text">${esc(r.status_label)} · ${esc(r.created_at_text)}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold small">${esc(r.revenue_expectation_text)}</div>
                                    <div class="timeline-text">${esc(r.quote_status_label)}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="info-card">
                            <div class="info-title">
                                <span><i class="bi bi-gear text-primary"></i> Thông tin xử lý</span>
                                <a class="edit-mini" href="${editUrl}">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>
                            </div>
                            ${item('Nhân viên phụ trách', r.sales_name)}
                            ${item('Người tạo', r.creator_name)}
                            ${item('Trạng thái', r.status_label)}
                            ${item('Giai đoạn', r.customer_stage_label)}
                            ${item('Ngày nhận data', r.data_received_at_text)}
                            ${item('Ngày tạo', r.created_at_text)}
                            ${item('Cập nhật cuối', r.updated_at_text)}
                        </div>

                        <div class="info-card">
                            <div class="info-title">
                                <span><i class="bi bi-telephone text-success"></i> Liên hệ & báo giá</span>
                            </div>
                            ${item('Gọi lần 1', `${r.call_1_result_label || '—'} - ${r.call_1_at_text || '—'}`)}
                            ${item('Gọi lần 2', `${r.call_2_result_label || '—'} - ${r.call_2_at_text || '—'}`)}
                            ${item('Báo giá', r.quote_status_label)}
                            ${item('Ngày gửi', r.quote_sent_at_text)}
                            ${item('Liên hệ cuối', r.last_contact_at_text)}
                            ${item('Follow-up', r.next_followup_at_text)}
                            ${item('Việc tiếp theo', r.next_action)}
                        </div>

                        <div class="info-card">
                            <div class="info-title">
                                <span><i class="bi bi-clock-history text-success"></i> Lịch sử liên hệ</span>
                            </div>
                            ${timelineHtml(history)}
                        </div>

                        <div class="info-card">
                            <div class="info-title">
                                <span><i class="bi bi-chat-left-dots text-info"></i> Ghi chú DB</span>
                            </div>
                            ${followupHtml(followups)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async function loadDetail(id) {
        if (!id) return;

        rows.forEach(row => {
            row.classList.toggle('is-active', row.dataset.id === String(id));
            const cb = row.querySelector('.ego-check');
            if (cb) cb.checked = row.dataset.id === String(id);
        });

        detailBox.innerHTML = `
            <div class="empty-state">
                <div class="spinner-border text-success mb-3" role="status"></div>
                <div class="fw-bold">Đang tải chi tiết khách hàng...</div>
            </div>
        `;

        try {
            const res = await fetch(urlTemplate.replace('__ID__', id), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);
            render(await res.json());
        } catch (err) {
            detailBox.innerHTML = `
                <div class="empty-state text-danger">
                    <i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>
                    Không tải được chi tiết khách hàng.
                    <div class="small text-muted mt-1">${esc(err.message)}</div>
                </div>
            `;
        }
    }

    rows.forEach(row => {
        row.addEventListener('click', function (event) {
            if (event.target.closest('a')) return;
            loadDetail(row.dataset.id);
        });
    });

    if (rows[0]) {
        loadDetail(rows[0].dataset.id);
    } else {
        detailBox.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-person-lines-fill fs-2 d-block mb-2"></i>
                Chọn một khách hàng để xem chi tiết.
            </div>
        `;
    }
})();
</script>
@endpush


@push('scripts')
<script>
/* EGO_REPORT_MODAL_JS_START */
(function () {
    const modal = document.getElementById('egoReportModal');
    const form = document.getElementById('egoReportForm');
    const methodInput = document.getElementById('egoReportMethod');
    const title = document.getElementById('egoReportModalTitle');
    const subtitle = document.getElementById('egoReportModalSubtitle');
    const alertBox = document.getElementById('egoFormAlert');
    const loading = document.getElementById('egoPopupLoading');
    const saveBtn = document.getElementById('egoReportSaveBtn');
    const nextBtn = document.getElementById('egoPopupNextBtn');
    const prevBtn = document.getElementById('egoPopupPrevBtn');
    const detailBox = document.getElementById('customerDetail');

    const popupOrder = ['customer', 'care', 'quote', 'need'];
    let activePopupTab = 'customer';

    if (!modal || !form) return;

    const baseUrl = @json(url('/sales/work-reports'));
    const createUrl = @json(route('sales.work-reports.store'));

    const dateFields = new Set([
        'data_received_at',
        'first_call_at',
        'call_1_at',
        'call_2_at',
        'last_contact_at',
        'quote_sent_at',
        'next_followup_at'
    ]);

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function toInputDateTime(value) {
        if (!value || value === '—') return '';

        let raw = String(value).trim();

        if (/^\d{2}\/\d{2}\/\d{4}/.test(raw)) {
            const m = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?/);
            if (m) {
                return `${m[3]}-${m[2]}-${m[1]}T${m[4] || '00'}:${m[5] || '00'}`;
            }
        }

        raw = raw.replace(' ', 'T');

        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            return raw + 'T00:00';
        }

        return raw.slice(0, 16);
    }

    function nowInputDateTime() {
        const d = new Date();
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    }

    function setField(name, value) {
        const el = form.elements[name];
        if (!el) return;

        if (dateFields.has(name)) {
            el.value = toInputDateTime(value);
            return;
        }

        el.value = value ?? '';
    }

    function getField(name) {
        return form.elements[name] || null;
    }

    function setPopupTab(tab) {
        activePopupTab = tab;

        modal.querySelectorAll('.ego-popup-tab').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.popupTab === tab);
        });

        modal.querySelectorAll('.ego-popup-section').forEach(panel => {
            panel.classList.toggle('active', panel.dataset.popupPanel === tab);
        });

        const index = popupOrder.indexOf(tab);
        prevBtn.style.display = index <= 0 ? 'none' : 'inline-flex';
        nextBtn.style.display = index >= popupOrder.length - 1 ? 'none' : 'inline-flex';
        saveBtn.style.display = index >= popupOrder.length - 1 ? 'inline-flex' : 'none';
    }

    function resetForm() {
        form.reset();
        alertBox.style.display = 'none';
        alertBox.innerHTML = '';
        loading.classList.remove('show');

        methodInput.value = 'POST';
        form.action = createUrl;

        setField('customer_type', 'turnkey');
        setField('status', 'new');
        setField('customer_stage', 'new_need_confirm');
        setField('priority', 'normal');
        setField('contact_channel', 'call');
        setField('quote_status', 'not_sent');
        setField('call_1_result', 'not_called');
        setField('data_received_at', nowInputDateTime());

        setPopupTab('customer');
    }

    function showModal() {
        modal.hidden = false;
        document.body.classList.add('ego-modal-open');

        setTimeout(() => {
            const first = getField('customer_name');
            if (first) first.focus();
        }, 80);
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('ego-modal-open');
    }

    function showError(html) {
        alertBox.innerHTML = html;
        alertBox.style.display = 'block';
    }

    function fillReport(r) {
        const fields = [
            'assigned_to',
            'data_source_id',
            'customer_name',
            'customer_phone',
            'customer_email',
            'customer_company',
            'customer_address',
            'facebook_name',
            'facebook_link',
            'zalo_id',
            'customer_type',
            'customer_stage',
            'region_text',
            'data_received_at',
            'first_call_at',
            'call_1_at',
            'call_1_result',
            'call_2_at',
            'call_2_result',
            'last_contact_at',
            'contact_channel',
            'customer_need',
            'system_size_kw',
            'budget_range',
            'project_timeline',
            'consultation_summary',
            'quoted_products',
            'quote_status',
            'quote_sent_at',
            'customer_feedback',
            'status',
            'priority',
            'outcome',
            'next_followup_at',
            'next_action',
            'revenue_expectation',
            'lost_reason',
            'proof_links'
        ];

        fields.forEach(name => setField(name, r[name]));

        if (Array.isArray(r.proof_links)) {
            setField('proof_links', r.proof_links.join("\n"));
        }

        setPopupTab('customer');
    }

    async function openCreateModal() {
        resetForm();

        title.textContent = 'Thêm khách hàng';
        subtitle.textContent = 'Nhập nhanh thông tin khách hàng, trạng thái chăm sóc và nhu cầu.';
        saveBtn.innerHTML = '<i class="bi bi-check2-circle"></i> Lưu khách hàng';

        showModal();
    }

    async function openEditModal(id) {
        resetForm();

        title.textContent = 'Sửa khách hàng';
        subtitle.textContent = 'Cập nhật thông tin khách hàng, trạng thái chăm sóc và ghi chú tư vấn.';
        saveBtn.innerHTML = '<i class="bi bi-check2-circle"></i> Cập nhật khách hàng';

        methodInput.value = 'PUT';
        form.action = `${baseUrl}/${id}`;

        showModal();
        loading.classList.add('show');

        try {
            const res = await fetch(`${baseUrl}/${id}/detail-json`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const payload = await res.json();
            fillReport(payload.report || {});
        } catch (err) {
            showError('Không tải được dữ liệu khách hàng. ' + err.message);
        } finally {
            loading.classList.remove('show');
        }
    }

    function setupDetailTabs() {
        if (!detailBox || detailBox.dataset.detailTabsVersion === 'staff_v2') return;

        const tabs = Array.from(detailBox.querySelectorAll('.detail-tab'));
        const cards = Array.from(detailBox.querySelectorAll('.info-card'));
        const summary = detailBox.querySelector('.customer-summary-box');

        if (!tabs.length || !cards.length) return;

        const tabMap = ['info', 'need', 'system', 'contact', 'note'];

        tabs.forEach((tab, index) => {
            tab.dataset.detailTab = tabMap[index] || 'info';
        });

        cards.forEach(card => {
            const text = card.textContent || '';
            let key = 'info';

            if (text.includes('Nhu cầu') || text.includes('tư vấn')) {
                key = 'need';
            } else if (text.includes('Thông tin hệ thống')) {
                key = 'system';
            } else if (text.includes('Liên hệ') || text.includes('Lịch sử')) {
                key = 'contact';
            } else if (text.includes('Ghi chú')) {
                key = 'note';
            }

            card.dataset.detailPanel = key;
        });

        function showDetailTab(key) {
            tabs.forEach(tab => {
                tab.classList.toggle('active', tab.dataset.detailTab === key);
            });

            if (summary) {
                summary.style.display = key === 'info' ? '' : 'none';
            }

            cards.forEach(card => {
                card.style.display = card.dataset.detailPanel === key ? '' : 'none';
            });

            const visible = cards.some(card => card.dataset.detailPanel === key);

            if (!visible) {
                cards.forEach(card => card.style.display = '');
            }
        }

        if (!detailBox.dataset.detailTabsReady) {
            detailBox.addEventListener('click', function (event) {
                const tab = event.target.closest('.detail-tab');
                if (!tab || !detailBox.contains(tab)) return;

                event.preventDefault();
                showDetailTab(tab.dataset.detailTab || 'info');
            });

            detailBox.dataset.detailTabsReady = '1';
        }

        showDetailTab('info');
    }

    if (detailBox) {
        const observer = new MutationObserver(function () {
            setTimeout(setupDetailTabs, 0);
        });

        observer.observe(detailBox, { childList: true });
        setTimeout(setupDetailTabs, 300);
    }

    modal.querySelectorAll('.ego-popup-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            setPopupTab(btn.dataset.popupTab);
        });
    });

    prevBtn.addEventListener('click', function () {
        const index = popupOrder.indexOf(activePopupTab);
        if (index > 0) setPopupTab(popupOrder[index - 1]);
    });

    nextBtn.addEventListener('click', function () {
        const index = popupOrder.indexOf(activePopupTab);
        if (index < popupOrder.length - 1) setPopupTab(popupOrder[index + 1]);
    });

    document.addEventListener('click', function (event) {
        const closeBtn = event.target.closest('[data-ego-close-modal]');
        if (closeBtn) {
            event.preventDefault();
            closeModal();
            return;
        }

        const a = event.target.closest('a');
        if (!a) return;

        let url;
        try {
            url = new URL(a.href, window.location.origin);
        } catch (e) {
            return;
        }

        const basePath = new URL(baseUrl, window.location.origin).pathname.replace(/\/+$/, '');
        const path = url.pathname.replace(/\/+$/, '');

        if (path === basePath + '/tao') {
            event.preventDefault();
            openCreateModal();
            return;
        }

        const editRegex = new RegExp('^' + escapeRegExp(basePath) + '/(\\d+)/sua$');
        const match = path.match(editRegex);

        if (match) {
            event.preventDefault();
            openEditModal(match[1]);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        alertBox.style.display = 'none';
        alertBox.innerHTML = '';
        saveBtn.disabled = true;
        const oldBtn = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang lưu...';

        try {
            const fd = new FormData(form);

            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: fd
            });

            if (res.status === 422) {
                const data = await res.json();
                const errors = data.errors || {};
                const list = Object.values(errors).flat().map(msg => `<li>${msg}</li>`).join('');
                showError(`<b>Vui lòng kiểm tra lại thông tin:</b><ul class="mb-0 mt-1">${list}</ul>`);
                return;
            }

            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }

            closeModal();
            window.location.reload();
        } catch (err) {
            showError('Không lưu được khách hàng. ' + err.message);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = oldBtn;
        }
    });

    setPopupTab('customer');
})();
/* EGO_REPORT_MODAL_JS_END */
</script>
@endpush


@push('scripts')
<script>
/* EGO_FIX_ROW_CLICK_DELEGATED_START */
(function () {
    const detailBox = document.getElementById('customerDetail');
    const table = document.querySelector('.ego-table');

    if (!detailBox || !table) return;

    const baseUrl = @json(url('/sales/work-reports'));

    function esc(value) {
        if (value === null || value === undefined || value === '') return '—';
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function nl(value) {
        return esc(value).replace(/\n/g, '<br>');
    }

    function initials(name) {
        name = String(name || 'KH').trim();
        const parts = name.split(/\s+/);
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function item(label, value) {
        return `
            <div class="info-row">
                <div class="info-label">${esc(label)}</div>
                <div class="info-value">${nl(value)}</div>
            </div>
        `;
    }

    function timelineHtml(items) {
        if (!items || !items.length) {
            return '<div class="timeline-text">Chưa có lịch sử liên hệ.</div>';
        }

        return items.map(item => `
            <div class="timeline-item">
                <div class="timeline-icon"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="timeline-title">${esc(item.status_label)} · ${esc(item.customer_stage_label)}</div>
                    <div class="timeline-text">${esc(item.updated_at_text)} · ${esc(item.sales_name)}</div>
                    <div class="timeline-text">Gọi: ${esc(item.call_1_result_label)} · Báo giá: ${esc(item.quote_status_label)}</div>
                </div>
            </div>
        `).join('');
    }

    function followupHtml(items) {
        if (!items || !items.length) {
            return '<div class="timeline-text">Chưa có ghi chú follow-up trong bảng mới.</div>';
        }

        return items.map(item => `
            <div class="timeline-item">
                <div class="timeline-icon"><i class="bi bi-chat-dots"></i></div>
                <div>
                    <div class="timeline-title">${esc(item.title || item.action_type)}</div>
                    <div class="timeline-text">${esc(item.created_at_text)} · ${esc(item.creator_name)}</div>
                    <div class="timeline-text">${nl(item.content)}</div>
                    <div class="timeline-text text-success fw-bold">Hẹn xử lý: ${esc(item.followup_at_text)}</div>
                </div>
            </div>
        `).join('');
    }

    function importantText(value, fallback = 'Chưa cập nhật') {
        if (value === null || value === undefined || value === '' || value === '—') return fallback;
        return value;
    }

    function importantNotesHtml(r, followups) {
        const notes = [];

        if (r.next_action) notes.push({ label: 'Việc tiếp theo', content: r.next_action });
        if (r.consultation_summary) notes.push({ label: 'Ghi chú tư vấn', content: r.consultation_summary });
        if (r.customer_feedback) notes.push({ label: 'Phản hồi khách', content: r.customer_feedback });
        if (r.lost_reason) notes.push({ label: 'Lý do mất khách', content: r.lost_reason });

        (followups || []).slice(0, 3).forEach(item => {
            notes.push({
                label: item.title || item.action_type || 'Ghi chú chăm sóc',
                content: item.content || '',
                meta: item.created_at_text || item.creator_name ? `${item.created_at_text || '—'} · ${item.creator_name || '—'}` : ''
            });
        });

        if (!notes.length) {
            return '<div class="timeline-text">Chưa có ghi chú. Nên cập nhật ngay sau khi gọi/tư vấn để không mất lịch sử chăm sóc.</div>';
        }

        return notes.map(note => `
            <div class="note-line">
                <div class="note-label">${esc(note.label)}</div>
                <div class="note-content">${nl(note.content)}</div>
                ${note.meta ? `<div class="timeline-text mt-1">${esc(note.meta)}</div>` : ''}
            </div>
        `).join('');
    }

    function renderCustomerDetail(payload) {
        detailBox.dataset.detailTabsVersion = 'staff_v2';
        const r = payload.report || {};
        const history = payload.history || [];
        const followups = payload.followups || [];
        const code = 'KH-' + String(r.id || '').padStart(6, '0');
        const showUrl = `${baseUrl}/${esc(r.id)}`;
        const editUrl = `${baseUrl}/${esc(r.id)}/sua`;

        detailBox.innerHTML = `
            <div class="detail-top">
                <div class="detail-breadcrumb">
                    <span>Khách hàng / <b>Chi tiết khách hàng</b></span>
                    <span class="detail-actions">
                        <a class="detail-edit-btn" href="${editUrl}">
                            <i class="bi bi-pencil-square"></i> Sửa khách
                        </a>
                        <a class="detail-action-btn" href="${showUrl}" title="Mở trang chi tiết">
                            <i class="bi bi-arrows-angle-expand"></i>
                        </a>
                    </span>
                </div>

                <div class="detail-profile">
                    <div class="detail-person">
                        <div class="detail-avatar">${esc(initials(r.customer_name))}</div>
                        <div>
                            <h2 class="detail-name">${esc(r.customer_name)}</h2>
                            <div class="detail-code">${esc(code)}</div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="ego-badge green">${esc(r.customer_type_label)}</span>
                                <span class="ego-badge blue">${esc(r.status_label)}</span>
                                <span class="ego-badge orange">${esc(r.priority_label)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-contact">
                    <div><i class="bi bi-telephone text-success"></i> <b>${esc(r.customer_phone)}</b></div>
                    <div><i class="bi bi-envelope text-primary"></i> ${esc(r.customer_email)}</div>
                    <div><i class="bi bi-geo-alt text-danger"></i> ${esc(r.customer_address || r.region_text)}</div>
                </div>
            </div>

            <div class="detail-metrics">
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-cash-stack"></i></div>
                    <div class="metric-label">Tổng giá trị</div>
                    <div class="metric-value">${esc(r.revenue_expectation_text)}</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-lightning"></i></div>
                    <div class="metric-label">Công suất</div>
                    <div class="metric-value">${esc(r.system_size_kw || '—')} kWp</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-file-earmark-check"></i></div>
                    <div class="metric-label">Báo giá</div>
                    <div class="metric-value">${esc(r.quote_status_label)}</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-calendar2-check"></i></div>
                    <div class="metric-label">Follow-up</div>
                    <div class="metric-value">${esc(r.next_followup_at_text)}</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-telephone"></i></div>
                    <div class="metric-label">Liên hệ cuối</div>
                    <div class="metric-value">${esc(r.last_contact_at_text)}</div>
                </div>
                <div class="detail-metric">
                    <div class="metric-icon"><i class="bi bi-person-check"></i></div>
                    <div class="metric-label">Sales</div>
                    <div class="metric-value">${esc(r.sales_name)}</div>
                </div>
            </div>

            <div class="detail-tabs">
                <button type="button" class="detail-tab active" data-detail-tab="info">Quan trọng</button>
                <button type="button" class="detail-tab" data-detail-tab="need">Nhu cầu</button>
                <button type="button" class="detail-tab" data-detail-tab="system">Trạng thái</button>
                <button type="button" class="detail-tab" data-detail-tab="contact">Liên hệ</button>
                <button type="button" class="detail-tab" data-detail-tab="note">Ghi chú</button>
            </div>

            <div class="detail-body">
                
                <div class="detail-important-grid" data-detail-panel="info">
                    <div class="detail-important-card is-hot">
                        <div class="detail-important-label"><i class="bi bi-lightning-charge"></i> Việc cần làm</div>
                        <div class="detail-important-value">${nl(importantText(r.next_action, 'Chưa có việc tiếp theo'))}</div>
                    </div>
                    <div class="detail-important-card">
                        <div class="detail-important-label"><i class="bi bi-calendar2-check"></i> Lịch chăm sóc</div>
                        <div class="detail-important-value">${esc(importantText(r.next_followup_at_text, 'Chưa hẹn follow-up'))}</div>
                    </div>
                    <div class="detail-important-card">
                        <div class="detail-important-label"><i class="bi bi-flag"></i> Trạng thái khách hàng</div>
                        <div class="detail-important-value">${esc(r.status_label || 'Chưa cập nhật')} · ${esc(r.customer_stage_label || 'Chưa cập nhật')} · ${esc(r.priority_label || 'Bình thường')}</div>
                    </div>
                    <div class="detail-important-card">
                        <div class="detail-important-label"><i class="bi bi-person-check"></i> Phụ trách</div>
                        <div class="detail-important-value">${esc(r.sales_name || 'Chưa phân công')} · ${esc(r.last_contact_at_text || 'Chưa liên hệ')}</div>
                    </div>
                </div>

                <div class="info-card important-note-card" data-detail-panel="info">
                    <div class="info-title">
                        <span><i class="bi bi-journal-text text-success"></i> Ghi chú sales</span>
                        <a class="edit-mini" href="${editUrl}">
                            <i class="bi bi-pencil"></i> Cập nhật
                        </a>
                    </div>
                    <div class="timeline-text mb-2">Sales cập nhật nhu cầu thực tế, nội dung tư vấn và phản hồi của khách tại đây.</div>
                    ${importantNotesHtml(r, followups)}
                </div>

                <div class="detail-grid">

                    <div>
                        <div class="info-card" data-detail-panel="info">
                            <div class="info-title">
                                <span><i class="bi bi-person-vcard text-success"></i> Thông tin nhanh</span>
                                <a class="edit-mini" href="${editUrl}">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>
                            </div>
                            ${item('Khách hàng', r.customer_name)}
                            ${item('Điện thoại', r.customer_phone)}
                            ${item('Email', r.customer_email)}
                            ${item('Khu vực', r.customer_address || r.region_text)}
                            ${item('Nguồn khách', r.source_name)}
                        </div>

                        <div class="info-card" data-detail-panel="need">
                            <div class="info-title">
                                <span><i class="bi bi-lightning-charge text-warning"></i> Nhu cầu & tư vấn</span>
                                <a class="edit-mini" href="${editUrl}">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>
                            </div>
                            ${item('Nhu cầu chính', r.customer_need)}
                            ${item('Ngân sách dự kiến', r.budget_range)}
                            ${item('Thời gian dự kiến', r.project_timeline)}
                            ${item('Sản phẩm / báo giá', r.quoted_products)}
                            ${item('Phản hồi khách', r.customer_feedback)}
                            ${item('Ghi chú tư vấn', r.consultation_summary)}
                        </div>

                        <div class="info-card" data-detail-panel="system">
                            <div class="info-title">
                                <span><i class="bi bi-house-gear text-primary"></i> Dự án liên quan</span>
                                <a class="edit-mini" href="${showUrl}">
                                    <i class="bi bi-eye"></i> Xem
                                </a>
                            </div>
                            <div class="project-card">
                                <div class="project-img"><i class="bi bi-house fs-5"></i></div>
                                <div>
                                    <div class="fw-bold small">${esc(r.system_size_kw ? ('Hệ thống ' + r.system_size_kw + ' kWp') : 'Chưa nhập thông tin hệ thống')}</div>
                                    <div class="timeline-text">Data #${esc(r.id)} · ${esc(r.system_size_kw || '—')} kWp</div>
                                    <div class="timeline-text">${esc(r.status_label)} · ${esc(r.created_at_text)}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold small">${esc(r.revenue_expectation_text)}</div>
                                    <div class="timeline-text">${esc(r.quote_status_label)}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="info-card" data-detail-panel="system">
                            <div class="info-title">
                                <span><i class="bi bi-gear text-primary"></i> Thông tin xử lý</span>
                                <a class="edit-mini" href="${editUrl}">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>
                            </div>
                            ${item('Nhân viên phụ trách', r.sales_name)}
                            ${item('Người tạo', r.creator_name)}
                            ${item('Trạng thái', r.status_label)}
                            ${item('Giai đoạn', r.customer_stage_label)}
                            ${item('Ngày nhận data', r.data_received_at_text)}
                            ${item('Ngày tạo', r.created_at_text)}
                            ${item('Cập nhật cuối', r.updated_at_text)}
                        </div>

                        <div class="info-card" data-detail-panel="contact">
                            <div class="info-title">
                                <span><i class="bi bi-telephone text-success"></i> Liên hệ & báo giá</span>
                            </div>
                            ${item('Gọi lần 1', `${r.call_1_result_label || '—'} - ${r.call_1_at_text || '—'}`)}
                            ${item('Gọi lần 2', `${r.call_2_result_label || '—'} - ${r.call_2_at_text || '—'}`)}
                            ${item('Báo giá', r.quote_status_label)}
                            ${item('Ngày gửi', r.quote_sent_at_text)}
                            ${item('Liên hệ cuối', r.last_contact_at_text)}
                            ${item('Follow-up', r.next_followup_at_text)}
                            ${item('Việc tiếp theo', r.next_action)}
                        </div>

                        <div class="info-card" data-detail-panel="contact">
                            <div class="info-title">
                                <span><i class="bi bi-clock-history text-success"></i> Lịch sử liên hệ</span>
                            </div>
                            ${timelineHtml(history)}
                        </div>

                        <div class="info-card important-note-card" data-detail-panel="note">
                            <div class="info-title">
                                <span><i class="bi bi-chat-left-dots text-info"></i> Nhật ký chăm sóc</span>
                                <a class="edit-mini" href="${editUrl}">
                                    <i class="bi bi-pencil"></i> Thêm ghi chú
                                </a>
                            </div>
                            ${importantNotesHtml(r, followups)}
                        </div>
                    </div>
                </div>
            </div>
        `;

        bindDetailTabs();
    }

    function bindDetailTabs() {
        const tabs = detailBox.querySelectorAll('.detail-tab');
        const panels = detailBox.querySelectorAll('[data-detail-panel]');

        function show(tabKey) {
            tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.detailTab === tabKey));

            panels.forEach(panel => {
                panel.style.display = panel.dataset.detailPanel === tabKey ? '' : 'none';
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', function () {
                show(tab.dataset.detailTab);
            });
        });

        show('info');
    }

    async function loadCustomer(id) {
        if (!id) return;

        document.querySelectorAll('.ego-row').forEach(row => {
            const active = String(row.dataset.id) === String(id);
            row.classList.toggle('is-active', active);

            const cb = row.querySelector('.ego-check');
            if (cb) cb.checked = active;
        });

        detailBox.innerHTML = `
            <div class="empty-state">
                <div class="spinner-border text-success mb-3" role="status"></div>
                <div class="fw-bold">Đang tải chi tiết khách hàng...</div>
            </div>
        `;

        try {
            const res = await fetch(`${baseUrl}/${id}/detail-json`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            renderCustomerDetail(await res.json());
        } catch (err) {
            detailBox.innerHTML = `
                <div class="empty-state text-danger">
                    <i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>
                    Không tải được chi tiết khách hàng.
                    <div class="small text-muted mt-1">${esc(err.message)}</div>
                </div>
            `;
        }
    }

    table.addEventListener('click', function (event) {
        const toggle = event.target.closest('.ego-staff-toggle');
        if (!toggle) return;

        event.preventDefault();
        event.stopPropagation();

        const staffKey = toggle.dataset.staffKey;
        const collapsed = toggle.getAttribute('aria-expanded') === 'true';

        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.classList.toggle('is-collapsed', collapsed);

        table.querySelectorAll(`.ego-row[data-staff-key="${staffKey}"]`).forEach(row => {
            row.classList.toggle('is-hidden-by-staff', collapsed);
        });
    }, true);

    table.addEventListener('click', function (event) {
        const row = event.target.closest('.ego-row');
        if (!row || row.classList.contains('is-hidden-by-staff')) return;

        event.preventDefault();
        event.stopPropagation();

        loadCustomer(row.dataset.id);
    }, true);

    table.querySelectorAll('.ego-check').forEach(cb => {
        cb.removeAttribute('disabled');
        cb.style.pointerEvents = 'none';
    });

    document.querySelectorAll('.ego-row').forEach(row => {
        row.style.cursor = 'pointer';
    });

    detailBox.innerHTML = `
        <div class="empty-state">
            <i class="bi bi-person-lines-fill fs-2 d-block mb-2"></i>
            <div class="fw-bold">Chọn nhân viên để mở danh sách khách hàng</div>
            <div class="small text-muted mt-1">Sau đó bấm vào khách hàng để xem chi tiết.</div>
        </div>
    `;
})();
/* EGO_FIX_ROW_CLICK_DELEGATED_END */
</script>
@endpush


<!-- EGO_ADS_AUTOFILL_START -->
<style>
    .ego-ads-import-card {
        margin: 12px 0 14px;
        padding: 12px;
        border: 1px solid #c8f3e6;
        background: linear-gradient(135deg, #f0fffb 0%, #ffffff 100%);
        border-radius: 16px;
        box-shadow: 0 8px 22px rgba(0, 168, 134, .08);
    }

    .ego-ads-import-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .ego-ads-import-title {
        font-weight: 900;
        color: #055e4f;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .ego-ads-import-title i {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #dffcf3;
        color: #00a886;
    }

    .ego-ads-import-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ego-ads-import-btn {
        border: 0;
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .ego-ads-import-btn.primary {
        background: #00a886;
        color: white;
        box-shadow: 0 8px 18px rgba(0, 168, 134, .20);
    }

    .ego-ads-import-btn.secondary {
        background: #edf7f5;
        color: #055e4f;
    }

    .ego-ads-import-textarea {
        width: 100%;
        min-height: 96px;
        border: 1px solid #cae9e3;
        border-radius: 14px;
        padding: 10px 12px;
        font-size: 13px;
        line-height: 1.45;
        outline: none;
        resize: vertical;
        background: #fff;
    }

    .ego-ads-import-textarea:focus {
        border-color: #00a886;
        box-shadow: 0 0 0 3px rgba(0, 168, 134, .12);
    }

    .ego-ads-import-preview {
        margin-top: 8px;
        display: none;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 7px;
    }

    .ego-ads-import-chip {
        background: #ffffff;
        border: 1px solid #e2ecea;
        border-radius: 12px;
        padding: 7px 9px;
        font-size: 11px;
        color: #475569;
        min-height: 42px;
    }

    .ego-ads-import-chip b {
        display: block;
        font-size: 10px;
        color: #00a886;
        text-transform: uppercase;
        margin-bottom: 2px;
    }

    @media (max-width: 900px) {
        .ego-ads-import-preview {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>

<script>
(function () {
    if (window.EgoAdsAutofillInstalled) return;
    window.EgoAdsAutofillInstalled = true;

    const ADS_SAMPLE = `3. Ngân sách bạn dự kiến đầu tư là bao nhiêu?: 70 – 100 triệu
1. Tiền điện trung bình mỗi tháng của bạn là bao nhiêu?: 2 – 3 triệu
Full name: Nam Trương
Phone number: 037 800 0139
5. Bạn đang ở khu vực nào? (Địa chỉ lắp đặt): TP HCM
2. Bạn muốn lắp điện mặt trời trong thời gian nào?: Ngay trong tháng này
Email: nhatnam1976@gmail.com
4. Diện tích mái ước tính bao nhiêu?: 30 – 60m²`;

    function norm(str) {
        return String(str || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function cleanValue(value) {
        return String(value || '')
            .replace(/^[\s:\-–—]+/, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function parseAdsText(raw) {
        const result = {
            customer_name: '',
            customer_phone: '',
            customer_email: '',
            customer_address: '',
            region_text: '',
            budget_range: '',
            electricity_bill: '',
            project_timeline: '',
            roof_area: '',
            customer_need: '',
            consultation_summary: '',
            raw: String(raw || '').trim()
        };

        const lines = String(raw || '')
            .replace(/\r/g, '\n')
            .split('\n')
            .map(line => line.trim())
            .filter(Boolean);

        const pairs = [];

        lines.forEach(line => {
            let key = '';
            let value = '';

            const idx = line.indexOf(':');

            if (idx >= 0) {
                key = line.slice(0, idx).trim();
                value = line.slice(idx + 1).trim();
            } else {
                key = line.trim();
                value = '';
            }

            key = key.replace(/^\d+\.\s*/, '').trim();
            value = cleanValue(value);

            pairs.push({ key, keyNorm: norm(key), value, raw: line });
        });

        const findValue = (...needles) => {
            const n = needles.map(norm);

            for (const pair of pairs) {
                if (n.some(x => pair.keyNorm.includes(x))) {
                    return pair.value;
                }
            }

            return '';
        };

        result.customer_name =
            findValue('full name', 'ho va ten', 'họ và tên', 'ten khach', 'tên khách') ||
            '';

        result.customer_phone =
            findValue('phone number', 'so dien thoai', 'số điện thoại', 'dien thoai', 'điện thoại') ||
            '';

        result.customer_email =
            findValue('email', 'e-mail') ||
            '';

        result.customer_address =
            findValue('dia chi lap dat', 'địa chỉ lắp đặt', 'ban dang o khu vuc nao', 'bạn đang ở khu vực nào', 'khu vuc', 'khu vực', 'address') ||
            '';

        result.region_text = result.customer_address;

        result.budget_range =
            findValue('ngan sach', 'ngân sách', 'du kien dau tu', 'dự kiến đầu tư') ||
            '';

        result.electricity_bill =
            findValue('tien dien', 'tiền điện', 'dien trung binh', 'điện trung bình') ||
            '';

        result.project_timeline =
            findValue('thoi gian nao', 'thời gian nào', 'muon lap', 'muốn lắp', 'timeline') ||
            '';

        result.roof_area =
            findValue('dien tich mai', 'diện tích mái', 'mai uoc tinh', 'mái ước tính') ||
            '';

        const needParts = [];

        if (result.electricity_bill) {
            needParts.push('Tiền điện trung bình: ' + result.electricity_bill);
        }

        if (result.budget_range) {
            needParts.push('Ngân sách dự kiến: ' + result.budget_range);
        }

        if (result.project_timeline) {
            needParts.push('Thời gian muốn lắp: ' + result.project_timeline);
        }

        if (result.roof_area) {
            needParts.push('Diện tích mái ước tính: ' + result.roof_area);
        }

        if (result.customer_address) {
            needParts.push('Khu vực lắp đặt: ' + result.customer_address);
        }

        result.customer_need = needParts.join('\n');

        result.consultation_summary = lines.join('\n');

        return result;
    }

    function setField(form, names, value, overwrite = true) {
        if (!value) return false;

        for (const name of names) {
            const selectors = [
                `[name="${name}"]`,
                `[name="${name}[]"]`,
                `#${name}`,
                `[data-field="${name}"]`
            ];

            for (const selector of selectors) {
                const field = form.querySelector(selector);

                if (!field) continue;

                if (!overwrite && String(field.value || '').trim()) {
                    return false;
                }

                field.value = value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));

                return true;
            }
        }

        return false;
    }

    function appendField(form, names, value) {
        if (!value) return false;

        for (const name of names) {
            const field = form.querySelector(`[name="${name}"], #${name}, [data-field="${name}"]`);

            if (!field) continue;

            const current = String(field.value || '').trim();

            if (current && current.includes(value)) {
                return true;
            }

            field.value = current ? (current + '\n' + value) : value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));

            return true;
        }

        return false;
    }

    function setSelectByText(form, names, text) {
        if (!text) return false;

        for (const name of names) {
            const field = form.querySelector(`select[name="${name}"], select#${name}`);

            if (!field) continue;

            const t = norm(text);

            for (const opt of Array.from(field.options || [])) {
                const label = norm(opt.textContent || opt.label || opt.value);

                if (label.includes(t) || t.includes(label)) {
                    field.value = opt.value;
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                }
            }
        }

        return false;
    }

    function applyAdsData(form, data) {
        setField(form, ['customer_name', 'name', 'full_name'], data.customer_name);
        setField(form, ['customer_phone', 'phone', 'phone_number', 'mobile'], data.customer_phone);
        setField(form, ['customer_email', 'email'], data.customer_email);
        setField(form, ['customer_address', 'address'], data.customer_address);
        setField(form, ['region_text', 'region', 'area', 'install_area'], data.region_text);
        setField(form, ['budget_range', 'budget', 'investment_budget'], data.budget_range);
        setField(form, ['project_timeline', 'timeline', 'installation_time'], data.project_timeline);
        setField(form, ['roof_area', 'roof_area_estimate', 'roof_size'], data.roof_area);

        setSelectByText(form, ['customer_type', 'type'], 'Lắp đặt trọn gói');

        appendField(form, ['customer_need', 'need', 'requirement'], data.customer_need);
        appendField(form, ['consultation_summary', 'consulting_note', 'note', 'notes'], data.consultation_summary);

        if (data.customer_name || data.customer_phone || data.customer_email) {
            const nextAction = 'Gọi xác nhận nhu cầu, khu vực lắp đặt, diện tích mái và ngân sách.';
            setField(form, ['next_action', 'action_next', 'todo'], nextAction, false);
        }
    }

    function updatePreview(card, data) {
        const preview = card.querySelector('[data-ego-ads-preview]');
        if (!preview) return;

        const chips = [
            ['Tên', data.customer_name],
            ['SĐT', data.customer_phone],
            ['Email', data.customer_email],
            ['Khu vực', data.customer_address],
            ['Ngân sách', data.budget_range],
            ['Tiền điện', data.electricity_bill],
            ['Thời gian', data.project_timeline],
            ['Diện tích mái', data.roof_area]
        ];

        preview.innerHTML = chips.map(([label, value]) => `
            <div class="ego-ads-import-chip">
                <b>${label}</b>
                <span>${value || '—'}</span>
            </div>
        `).join('');

        preview.style.display = 'grid';
    }

    function buildImportCard(form) {
        if (!form || form.querySelector('[data-ego-ads-import-card]')) return;

        const card = document.createElement('div');
        card.className = 'ego-ads-import-card';
        card.setAttribute('data-ego-ads-import-card', '1');
        card.innerHTML = `
            <div class="ego-ads-import-head">
                <div class="ego-ads-import-title">
                    <i class="bi bi-magic"></i>
                    Dán data ads để tự điền form
                </div>
                <div class="ego-ads-import-actions">
                    <button type="button" class="ego-ads-import-btn secondary" data-ego-ads-sample>
                        <i class="bi bi-clipboard-plus"></i> Dán mẫu
                    </button>
                    <button type="button" class="ego-ads-import-btn primary" data-ego-ads-parse>
                        <i class="bi bi-lightning-charge"></i> Tự điền
                    </button>
                </div>
            </div>
            <textarea class="ego-ads-import-textarea" data-ego-ads-raw placeholder="Copy data từ Ads rồi dán vào đây. Ví dụ: Full name, Phone number, Email, ngân sách, tiền điện, khu vực..."></textarea>
            <div class="ego-ads-import-preview" data-ego-ads-preview></div>
        `;

        const target =
            form.querySelector('.modal-body') ||
            form.querySelector('.crm-modal-body') ||
            form.querySelector('[data-step], .tab-content, .card, .form-card') ||
            form.firstElementChild;

        if (target && target.parentNode === form) {
            form.insertBefore(card, target);
        } else if (target) {
            target.insertBefore(card, target.firstChild);
        } else {
            form.prepend(card);
        }

        const raw = card.querySelector('[data-ego-ads-raw]');
        const btnParse = card.querySelector('[data-ego-ads-parse]');
        const btnSample = card.querySelector('[data-ego-ads-sample]');

        btnSample.addEventListener('click', function () {
            raw.value = ADS_SAMPLE;
            const data = parseAdsText(raw.value);
            updatePreview(card, data);
        });

        btnParse.addEventListener('click', function () {
            const data = parseAdsText(raw.value);
            updatePreview(card, data);
            applyAdsData(form, data);

            btnParse.innerHTML = '<i class="bi bi-check2-circle"></i> Đã tự điền';
            setTimeout(() => {
                btnParse.innerHTML = '<i class="bi bi-lightning-charge"></i> Tự điền';
            }, 1600);
        });

        raw.addEventListener('paste', function () {
            setTimeout(() => {
                const data = parseAdsText(raw.value);
                updatePreview(card, data);
                applyAdsData(form, data);
            }, 80);
        });
    }

    function enhanceForms() {
        const forms = Array.from(document.querySelectorAll('form')).filter(form => {
            const txt = norm(form.textContent || '');
            const hasCustomerField = form.querySelector('[name="customer_name"], [name="customer_phone"], [name="customer_email"]');
            return hasCustomerField || txt.includes('sua khach hang') || txt.includes('them khach hang') || txt.includes('crm sales');
        });

        forms.forEach(buildImportCard);
    }

    document.addEventListener('DOMContentLoaded', enhanceForms);
    document.addEventListener('click', function () {
        setTimeout(enhanceForms, 150);
        setTimeout(enhanceForms, 500);
    });

    const observer = new MutationObserver(function () {
        enhanceForms();
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });

    window.EgoParseAdsCustomerText = parseAdsText;
    window.EgoApplyAdsCustomerData = function (form, raw) {
        const data = parseAdsText(raw);
        applyAdsData(form, data);
        return data;
    };
})();
</script>
<!-- EGO_ADS_AUTOFILL_END -->

