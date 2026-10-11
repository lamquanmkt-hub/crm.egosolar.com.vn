@once
<style>
    .rk-wrap{padding:0 4px 34px;font-family:"Be Vietnam Pro",system-ui,-apple-system,"Segoe UI",sans-serif;min-width:0}
    .rk-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:12px}
    .rk-head h1{margin:0;font-size:22px;font-weight:950;color:#0f172a;letter-spacing:-.02em}
    .rk-head p{margin:4px 0 0;font-size:13px;color:#64748b;font-weight:700}
    .rk-actions{display:flex;gap:8px;flex-wrap:wrap}
    .rk-btn{height:36px;border-radius:11px;padding:0 13px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;background:#0f766e;color:#fff;white-space:nowrap}
    .rk-btn.outline{background:#fff;color:#0f172a;border-color:#dbe3ef}
    .rk-btn.danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .rk-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 12px 28px rgba(15,23,42,.05);margin-bottom:14px;padding:14px 16px;min-width:0}
    .rk-card h2{margin:0 0 8px;font-size:15px;font-weight:950;color:#0f172a}
    .rk-card .rk-note{font-size:12.5px;color:#64748b;margin:0 0 10px}
    .rk-alert{margin-bottom:12px;padding:10px 12px;border-radius:14px;font-size:13px;font-weight:800}
    .rk-alert.ok{border:1px solid #bbf7d0;background:#f0fdf4;color:#166534}
    .rk-alert.err{border:1px solid #fecaca;background:#fff1f2;color:#be123c}
    .rk-alert.info{border:1px solid #c7d2fe;background:#eef2ff;color:#3730a3}
    .rk-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}
    .rk-fact{background:#f8fafc;border:1px solid #e5edf5;border-radius:12px;padding:8px 10px;display:grid;gap:2px;min-width:0}
    .rk-fact span{font-size:11.5px;color:#64748b;font-weight:700}
    .rk-fact b{font-size:13px;color:#0f172a;word-break:break-word}
    .rk-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .rk-field{display:flex;flex-direction:column;gap:5px;min-width:0}
    .rk-field.full{grid-column:1/-1}
    .rk-field label{font-size:12px;font-weight:900;color:#475569}
    .rk-input{width:100%;height:38px;border:1px solid #dbe3ef;border-radius:11px;padding:0 10px;font-size:13px;background:#fff}
    textarea.rk-input{height:auto;min-height:60px;padding:8px 10px}
    .rk-check{display:flex;gap:8px;align-items:center;font-weight:800;font-size:13px;color:#0f3b78}
    .rk-table-wrap{border:1px solid #e2e8f0;border-radius:14px;overflow:auto}
    .rk-table{width:100%;border-collapse:collapse;min-width:820px}
    .rk-table th{background:#f1f5f9;font-size:11px;text-transform:uppercase;font-weight:950;color:#334155;padding:9px;text-align:left;white-space:nowrap}
    .rk-table td{padding:8px 9px;border-top:1px solid #edf2f7;font-size:13px;vertical-align:top}
    .rk-table td small{display:block;color:#64748b;font-size:11.5px;margin-top:2px}
    .rk-pill{display:inline-block;border-radius:999px;padding:2px 9px;font-size:11.5px;font-weight:900;background:#f1f5f9;color:#334155;white-space:nowrap}
    .rk-pill.counted,.rk-pill.earned{background:#e8f7ee;color:#15803d}
    .rk-pill.lost{background:#fdecec;color:#b91c1c}
    .rk-pill.pending{background:#e9f6fb;color:#0e6a8a}
    .rk-pill.waiting,.rk-pill.excluded{background:#f1f4f8;color:#556274}
    .rk-pill.missing{background:#fff6e5;color:#9a5b00}
    .rk-how{font-size:12.5px;color:#475569;background:#f8fafc;border:1px dashed #d3dce8;border-radius:12px;padding:9px 11px;margin-bottom:12px}
    .rk-logs div{font-size:12.5px;color:#475569;padding:3px 0;border-bottom:1px dashed #eef2f7}
    .rk-cards-mobile{display:none}
    @media(max-width:760px){
        .rk-grid{grid-template-columns:1fr}
        .rk-btn{width:100%}
        .rk-table-wrap.rk-responsive{display:none}
        .rk-cards-mobile{display:grid;gap:8px}
        .rk-mcard{border:1px solid #e2e8f0;border-radius:12px;padding:10px;display:grid;gap:4px;font-size:13px}
        .rk-mcard small{color:#64748b}
    }
</style>
@endonce
