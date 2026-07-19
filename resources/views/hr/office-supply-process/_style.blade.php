<style>
.vpp-page,
.vpp-page * {
    font-family: inherit !important;
    box-sizing: border-box;
}

.vpp-page {
    padding: 14px;
    background: #f4f7fb;
    min-height: 100vh;
    color: #0f172a;
    font-size: 13px;
}

.vpp-mini-hero {
    background: linear-gradient(135deg, #073b63, #0f766e);
    border-radius: 16px;
    padding: 16px 18px;
    color: #fff;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    box-shadow: 0 12px 28px rgba(15,23,42,.12);
}

.vpp-mini-title {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.2;
}

.vpp-mini-sub {
    margin: 4px 0 0;
    font-size: 12.5px;
    font-weight: 500;
    color: #e7faff;
}

.vpp-hero-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.vpp-alert {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #bbf7d0;
    padding: 8px 11px;
    border-radius: 11px;
    margin-bottom: 9px;
    font-weight: 600;
    font-size: 13px;
}

.vpp-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 8px 11px;
    border-radius: 11px;
    margin-bottom: 9px;
    font-weight: 600;
    font-size: 13px;
}

.vpp-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    margin-bottom: 10px;
}

.vpp-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 13px;
    padding: 10px 12px;
    box-shadow: 0 8px 20px rgba(15,23,42,.04);
}

.vpp-stat-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
}

.vpp-stat-value {
    font-size: 20px;
    line-height: 1.1;
    font-weight: 800;
    margin-top: 2px;
    color: #0f172a;
}

.vpp-layout {
    display: grid;
    grid-template-columns: 1.45fr .85fr;
    gap: 10px;
    align-items: start;
}

.vpp-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    margin-bottom: 10px;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(15,23,42,.04);
}

.vpp-card-head {
    padding: 10px 12px;
    border-bottom: 1px solid #edf2f7;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.vpp-card-title {
    margin: 0;
    font-size: 14.5px;
    font-weight: 750;
    color: #0f172a;
}

.vpp-card-note {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
    font-weight: 500;
}

.vpp-card-body {
    padding: 11px 12px;
}

.vpp-toolbar {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
}

.vpp-search {
    display: flex;
    gap: 7px;
    align-items: center;
    max-width: 420px;
    width: 100%;
}

.vpp-input,
.vpp-select,
.vpp-textarea {
    width: 100%;
    border: 1px solid #dbe3ef;
    border-radius: 9px;
    background: #fff;
    color: #0f172a;
    font-size: 13px;
    font-weight: 500;
    outline: none;
}

.vpp-input,
.vpp-select {
    height: 34px;
    padding: 7px 9px;
}

.vpp-textarea {
    min-height: 62px;
    padding: 8px 9px;
    resize: vertical;
}

.vpp-label {
    display: block;
    font-size: 12px;
    color: #475569;
    font-weight: 650;
    margin-bottom: 4px;
}

.vpp-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.vpp-field-full {
    grid-column: 1 / -1;
}

.vpp-btn,
.vpp-btn-outline,
.vpp-btn-soft,
.vpp-btn-danger {
    border-radius: 9px;
    padding: 7px 10px;
    font-size: 13px;
    line-height: 1.2;
    font-weight: 650;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
}

.vpp-btn {
    background: #0f766e;
    color: #fff;
}

.vpp-btn-outline {
    background: #eef6ff;
    color: #0369a1;
    border: 1px solid #bfdbfe;
}

.vpp-btn-soft {
    background: #f8fafc;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.vpp-btn-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.vpp-table-wrap {
    overflow: auto;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.vpp-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

.vpp-table th,
.vpp-table td {
    padding: 8px 9px;
    border-bottom: 1px solid #edf2f7;
    text-align: left;
    vertical-align: middle;
    font-size: 13px;
    font-weight: 500;
}

.vpp-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 12.5px;
}

.vpp-table strong {
    font-weight: 750;
}

.vpp-muted {
    color: #64748b;
    font-size: 12px;
    font-weight: 500;
}

.vpp-code {
    color: #0369a1;
    text-decoration: none;
    font-weight: 700;
}

.vpp-badge {
    display: inline-flex;
    padding: 3px 7px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.vpp-in {
    background: #ecfdf5;
    color: #047857;
}

.vpp-out {
    background: #fff7ed;
    color: #c2410c;
}

.vpp-ok {
    background: #ecfeff;
    color: #0e7490;
}

.vpp-low {
    background: #fef2f2;
    color: #b91c1c;
}

.vpp-empty {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    padding: 13px;
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 13px;
}

.vpp-action-row {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.vpp-row {
    display: grid;
    grid-template-columns: 1fr 86px 32px;
    gap: 7px;
    margin-bottom: 7px;
}

.vpp-remove {
    border: 0;
    border-radius: 8px;
    background: #fee2e2;
    color: #b91c1c;
    font-weight: 800;
    cursor: pointer;
}

/* Popup */
.vpp-modal-backdrop {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(15,23,42,.5);
    z-index: 99999;
    padding: 18px;
}

.vpp-modal-backdrop.active {
    display: flex;
}

.vpp-modal {
    width: min(540px, 100%);
    max-height: 88vh;
    overflow: auto;
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 25px 80px rgba(15,23,42,.35);
}

.vpp-modal.large {
    width: min(720px, 100%);
}

.vpp-modal-head {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    padding: 12px 14px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.vpp-modal-title {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
}

.vpp-modal-close {
    width: 31px;
    height: 31px;
    border: 0;
    border-radius: 9px;
    background: #f1f5f9;
    color: #334155;
    font-size: 19px;
    cursor: pointer;
}

.vpp-modal-body {
    padding: 13px 14px;
}

.vpp-modal-foot {
    position: sticky;
    bottom: 0;
    background: #f8fafc;
    padding: 11px 14px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.vpp-page ::placeholder {
    color: #94a3b8;
    font-weight: 500;
}

@media(max-width: 1200px) {
    .vpp-layout,
    .vpp-stats {
        grid-template-columns: 1fr;
    }

    .vpp-mini-hero,
    .vpp-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .vpp-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
