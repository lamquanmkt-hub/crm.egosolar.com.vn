<style>
    :root {
        --cp-primary: #0f766e;
        --cp-primary-2: #0891b2;
        --cp-ink: #111827;
        --cp-soft: #475569;
        --cp-muted: #64748b;
        --cp-border: #e2e8f0;
        --cp-border-2: #edf2f7;
        --cp-bg: #f6f8fb;
        --cp-card: #ffffff;
        --cp-danger: #e11d48;
        --cp-warn: #d97706;
        --cp-ok: #059669;
    }

    .cp-page {
        max-width: 1480px;
        margin: 0 auto;
        padding: 14px 16px 22px;
        color: var(--cp-ink);
        font-size: 13px;
    }

    .cp-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .cp-title {
        margin: 0;
        font-size: 24px;
        line-height: 1.15;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .cp-sub {
        margin-top: 3px;
        color: var(--cp-muted);
        font-size: 13px;
        font-weight: 500;
    }

    .cp-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
    }

    .cp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 34px;
        border: 1px solid var(--cp-border);
        border-radius: 10px;
        padding: 0 11px;
        background: #fff;
        color: #0f172a;
        font-size: 13px;
        font-weight: 650;
        text-decoration: none;
        cursor: pointer;
        transition: .16s ease;
        white-space: nowrap;
    }

    .cp-btn:hover {
        transform: translateY(-1px);
        border-color: #cbd5e1;
        box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
    }

    .cp-btn.primary {
        border-color: transparent;
        background: linear-gradient(135deg, #0f766e, #0891b2);
        color: #fff;
        box-shadow: 0 10px 20px rgba(8, 145, 178, .16);
    }

    .cp-btn.ok {
        background: #ecfdf5;
        color: #047857;
        border-color: #bbf7d0;
    }

    .cp-btn.danger {
        background: #fff1f2;
        color: #be123c;
        border-color: #fecdd3;
    }

    .cp-grid-kpi {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .cp-kpi {
        background: rgba(255, 255, 255, .92);
        border: 1px solid var(--cp-border);
        border-radius: 16px;
        padding: 12px 13px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .035);
    }

    .cp-kpi .label {
        font-size: 11px;
        color: var(--cp-muted);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .cp-kpi .value {
        margin-top: 4px;
        font-size: 20px;
        font-weight: 800;
    }

    .cp-kpi .hint {
        margin-top: 1px;
        font-size: 11px;
        color: var(--cp-muted);
        font-weight: 500;
    }

    .cp-card {
        margin-bottom: 12px;
        background: var(--cp-card);
        border: 1px solid var(--cp-border);
        border-radius: 18px;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .04);
        overflow: hidden;
    }

    .cp-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 11px 14px;
        border-bottom: 1px solid var(--cp-border-2);
        background: linear-gradient(180deg, #fff, #fbfdff);
    }

    .cp-card-title {
        font-size: 16px;
        font-weight: 750;
    }

    .cp-card-body {
        padding: 13px 14px;
    }

    .cp-filter {
        display: grid;
        grid-template-columns: 1.35fr .75fr .75fr .75fr .75fr auto;
        gap: 9px;
        align-items: end;
    }

    .cp-field label {
        display: block;
        margin-bottom: 4px;
        color: #475569;
        font-size: 11px;
        font-weight: 700;
    }

    .cp-input,
    .cp-select,
    .cp-textarea {
        width: 100%;
        min-height: 34px;
        border: 1px solid var(--cp-border);
        border-radius: 10px;
        padding: 7px 10px;
        background: #fff;
        color: var(--cp-ink);
        font-size: 13px;
        font-weight: 500;
    }

    .cp-input::placeholder,
    .cp-textarea::placeholder {
        color: #94a3b8;
        font-weight: 450;
    }

    .cp-textarea {
        min-height: 64px;
        resize: vertical;
        line-height: 1.45;
    }

    .cp-input:focus,
    .cp-select:focus,
    .cp-textarea:focus {
        outline: none;
        border-color: #67e8f9;
        box-shadow: 0 0 0 3px rgba(103, 232, 249, .22);
    }

    .cp-form-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 9px;
        align-items: start;
    }

    .cp-form-grid .span-2 {
        grid-column: span 2;
    }

    .cp-form-grid .span-3 {
        grid-column: span 3;
    }

    .cp-form-grid .span-4 {
        grid-column: span 4;
    }

    .cp-form-grid .span-5 {
        grid-column: span 5;
    }

    .cp-table-wrap {
        overflow: auto;
    }

    .cp-table {
        width: 100%;
        min-width: 1120px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .cp-table th {
        padding: 9px 10px;
        border-bottom: 1px solid var(--cp-border);
        background: #f8fafc;
        color: #475569;
        font-size: 11px;
        font-weight: 750;
        text-align: left;
        text-transform: uppercase;
    }

    .cp-table td {
        padding: 10px;
        border-bottom: 1px solid #eef2f7;
        color: #1f2937;
        font-size: 13px;
        font-weight: 500;
        vertical-align: middle;
    }

    .cp-table tr:hover td {
        background: #fbfdff;
    }

    .cp-name {
        color: #0f172a;
        font-weight: 750;
    }

    .cp-money {
        color: #047857;
        font-weight: 750;
    }

    .cp-muted {
        color: var(--cp-muted);
    }

    .cp-small {
        font-size: 11px;
        line-height: 1.35;
    }

    .cp-badge {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        border-radius: 999px;
        padding: 4px 9px;
        font-size: 11px;
        font-weight: 700;
        background: #eef2ff;
        color: #3730a3;
    }

    .cp-badge.draft {
        background: #f1f5f9;
        color: #334155;
    }

    .cp-badge.deposit_pending {
        background: #fff7ed;
        color: #c2410c;
    }

    .cp-badge.deposited {
        background: #fef3c7;
        color: #b45309;
    }

    .cp-badge.active {
        background: #dcfce7;
        color: #047857;
    }

    .cp-badge.need_documents {
        background: #fee2e2;
        color: #be123c;
    }

    .cp-badge.reviewing {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .cp-badge.paused {
        background: #f3f4f6;
        color: #4b5563;
    }

    .cp-badge.cancelled {
        background: #ffe4e6;
        color: #be123c;
    }

    .cp-doc-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 9px;
    }

    .cp-doc {
        border: 1px solid var(--cp-border);
        border-radius: 13px;
        padding: 10px;
        background: #fbfdff;
    }

    .cp-doc-name {
        font-weight: 700;
        word-break: break-word;
    }

    .cp-doc-meta {
        margin: 3px 0;
        color: var(--cp-muted);
        font-size: 11px;
        font-weight: 500;
    }

    .cp-alert {
        margin-bottom: 10px;
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 13px;
        font-weight: 650;
    }

    .cp-alert.ok {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #bbf7d0;
    }

    .cp-alert.err {
        background: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
    }

    .cp-error {
        margin: 5px 0 0;
        color: #be123c;
        font-size: 11px;
        font-weight: 650;
    }

    .cp-detail {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 12px;
    }

    .cp-info-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 9px;
    }

    .cp-info {
        background: #f8fafc;
        border: 1px solid #e5edf6;
        border-radius: 13px;
        padding: 10px;
    }

    .cp-info .k {
        color: #64748b;
        font-size: 10px;
        font-weight: 750;
        text-transform: uppercase;
    }

    .cp-info .v {
        margin-top: 3px;
        font-weight: 700;
    }

    .cp-progress {
        height: 7px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .cp-progress span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #0f766e, #0891b2);
    }

    @media (max-width: 1280px) {
        .cp-form-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    @media (max-width: 1100px) {
        .cp-grid-kpi {
            grid-template-columns: repeat(2, 1fr);
        }

        .cp-filter,
        .cp-form-grid,
        .cp-detail,
        .cp-info-grid,
        .cp-doc-grid {
            grid-template-columns: 1fr;
        }

        .cp-form-grid .span-2,
        .cp-form-grid .span-3,
        .cp-form-grid .span-4,
        .cp-form-grid .span-5 {
            grid-column: span 1;
        }

        .cp-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .cp-actions {
            justify-content: flex-start;
        }
    }

/* EGO_CUSTOMER_PROFILE_STATUS_HOAN_COC_START */
.cp-badge.deposit_refunded{
    background:#e0f2fe !important;
    color:#0369a1 !important;
    border:1px solid #bae6fd !important;
}
/* EGO_CUSTOMER_PROFILE_STATUS_HOAN_COC_END */

</style>
