@php
    $paymentMilestones = $project->paymentMilestones()
        ->with(['transactions.recorder:id,name', 'transactions.confirmer:id,name'])
        ->orderBy('sequence')
        ->orderBy('id')
        ->get();

    $paymentTransactions = $project->paymentTransactions()
        ->with(['milestone:id,title', 'recorder:id,name', 'confirmer:id,name', 'canceller:id,name'])
        ->orderByDesc('paid_at')
        ->orderByDesc('id')
        ->get();

    // EGO_PROJECT_FINANCE_FLOW_V3
    $pendingTransactions = $paymentTransactions
        ->where('status', 'pending')
        ->values();

    $historyTransactions = $paymentTransactions
        ->whereIn('status', ['confirmed', 'rejected', 'cancelled'])
        ->values();

    $paymentAdjustments = \App\Models\ProjectTest\PaymentAdjustment::query()
        ->where('project_id', $project->id)
        ->with(['transaction:id,transaction_code,amount,milestone_id', 'requester:id,name', 'reviewer:id,name'])
        ->orderByDesc('id')
        ->get();

    $pendingAdjustments = $paymentAdjustments
        ->where('status', 'pending')
        ->values();

    $adjustmentHistory = $paymentAdjustments
        ->whereIn('status', ['approved', 'rejected'])
        ->values();

    $approvedAdjustmentByTransaction = $paymentAdjustments
        ->where('status', 'approved')
        ->groupBy('transaction_id')
        ->map(fn ($items) => (float) $items->sum('delta_amount'));

    $approvedAdjustmentTotal = (float) $paymentAdjustments
        ->where('status', 'approved')
        ->sum('delta_amount');

    $confirmedCollected = (float) $paymentTransactions->where('status', 'confirmed')->sum('amount')
        + $approvedAdjustmentTotal;
    $pendingCollected = (float) $paymentTransactions->where('status', 'pending')->sum('amount');
    $totalRevenue = (float) $project->contract_amount + (float) $project->extra_revenue;
    $receivable = max(0, $totalRevenue - $confirmedCollected);
    $overdueAmount = (float) $paymentMilestones->sum(function ($milestone) use ($approvedAdjustmentByTransaction) {
        $confirmed = (float) $milestone->transactions
            ->where('status', 'confirmed')
            ->sum(function ($transaction) use ($approvedAdjustmentByTransaction) {
                return (float) $transaction->amount
                    + (float) ($approvedAdjustmentByTransaction[$transaction->id] ?? 0);
            });

        return $milestone->due_date && $milestone->due_date->isPast()
            ? max(0, (float) $milestone->amount - $confirmed)
            : 0;
    });
    $collectionProgress = $totalRevenue > 0
        ? min(100, ($confirmedCollected / $totalRevenue) * 100)
        : 0;
    $planTotal = (float) $paymentMilestones->sum('amount');

    $user = auth()->user();
    $isProjectSalesOwner = $user && $user->hasAnyRole(['sales', 'sales_staff'])
        && (
            (int) $project->sales_user_id === (int) $user->id
            || (int) $project->created_by === (int) $user->id
        );
    $canManagePaymentPlan = $user && (
        $user->hasAnyRole(['admin', 'management', 'manager', 'accounting', 'sales_manager'])
        || $isProjectSalesOwner
    );
    $canRecordPayment = $canManagePaymentPlan;
    $canConfirmPayment = $user && $user->hasAnyRole(['admin', 'management', 'manager', 'accounting']);
    $canRequestAdjustment = $canManagePaymentPlan;
    $canReviewAdjustment = $canConfirmPayment;
    $canViewProfit = (bool) ($showProjectProfit ?? false);
    $canEditRevenue = (bool) ($canEditProjectValue ?? false);
    $canEditCosts = (bool) ($canEditProjectCosts ?? false);

    $financeRoleLabel = $canViewProfit
        ? 'QUẢN TRỊ TÀI CHÍNH'
        : ($canConfirmPayment ? 'KẾ TOÁN XÁC NHẬN' : 'NỘI BỘ SALES');

    $paymentStatusLabels = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'rejected' => 'Bị từ chối',
        'cancelled' => 'Đã hủy',
    ];
    $adjustmentStatusLabels = [
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Bị từ chối',
    ];
    $milestoneStatusLabels = [
        'pending' => 'Chưa đến hạn',
        'partial' => 'Đã thu một phần',
        'paid' => 'Đã hoàn thành',
        'overdue' => 'Quá hạn',
    ];
    $paymentMethods = [
        'bank_transfer' => 'Chuyển khoản',
        'cash' => 'Tiền mặt',
        'card' => 'Quẹt thẻ',
        'offset' => 'Bù trừ công nợ',
        'other' => 'Khác',
    ];

    $financeHistories = $canViewProfit
        ? $project->histories
            ->filter(function ($history) {
                $action = mb_strtolower((string) $history->action);

                return str_contains($action, 'doanh thu')
                    || str_contains($action, 'chi phí')
                    || str_contains($action, 'tài chính');
            })
            ->take(8)
        : collect();
@endphp

<style>
.pt-fin-v2{--fin-navy:#102b48;--fin-muted:#72849a;--fin-line:#dfe7f0;--fin-soft:#f7fafc;--fin-brand:#0b9d87;--fin-green:#0a8a72;--fin-orange:#bc6c17;--fin-red:#c53a46}.pt-fin-v2 *{box-sizing:border-box}.pt-fin-v2__header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:17px}.pt-fin-v2__header h2{margin:0;color:var(--fin-navy);font-size:20px;letter-spacing:-.02em}.pt-fin-v2__header p{margin:5px 0 0;color:var(--fin-muted);font-size:12px;line-height:1.55}.pt-fin-role{display:inline-flex;align-items:center;min-height:28px;padding:0 10px;border:1px solid #cce5f6;border-radius:999px;background:#eef8ff;color:#1769a5;font-size:10px;font-weight:850;white-space:nowrap}.pt-fin-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:14px}.pt-fin-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:38px;padding:8px 13px;border:1px solid var(--fin-line);border-radius:10px;background:#fff;color:#29445e;font-size:12px;font-weight:750;cursor:pointer;text-decoration:none}.pt-fin-btn:hover{border-color:#a9c5da;color:#102b48}.pt-fin-btn--primary{border-color:var(--fin-brand);background:var(--fin-brand);color:#fff}.pt-fin-btn--primary:hover{background:#078c78;color:#fff}.pt-fin-btn--danger{border-color:#f0bec2;background:#fff7f7;color:#b92e3b}.pt-fin-btn--sm{min-height:32px;padding:6px 9px;font-size:11px}.pt-fin-btn--icon{width:34px;padding:0}.pt-fin-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}.pt-fin-stat{min-height:103px;padding:15px 16px;border:1px solid var(--fin-line);border-radius:13px;background:#fff}.pt-fin-stat small{display:block;color:var(--fin-muted);font-size:10px;font-weight:850;letter-spacing:.035em;text-transform:uppercase}.pt-fin-stat strong{display:block;margin-top:10px;color:var(--fin-navy);font-size:22px;line-height:1.1}.pt-fin-stat--paid{border-color:#b9e6da}.pt-fin-stat--paid strong{color:var(--fin-green)}.pt-fin-stat--due{border-color:#f0d2af}.pt-fin-stat--due strong{color:var(--fin-orange)}.pt-fin-stat--overdue.is-active{border-color:#efbec3;background:#fffafa}.pt-fin-stat--overdue.is-active strong{color:var(--fin-red)}.pt-fin-progress-card{margin-top:11px;padding:12px 14px;border-radius:12px;background:#f4f8fb}.pt-fin-progress-card__top{display:flex;align-items:center;justify-content:space-between;gap:12px;color:#50667d;font-size:11px}.pt-fin-progress-card__top strong{color:var(--fin-navy);font-size:12px}.pt-fin-progress{height:8px;margin-top:9px;border-radius:999px;background:#e4ebf2;overflow:hidden}.pt-fin-progress>span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#0b9d87,#35c5ae)}.pt-fin-alert{margin-top:12px;padding:11px 13px;border:1px solid #f2d3ae;border-radius:11px;background:#fffaf3;color:#925a1a;font-size:12px}.pt-fin-section{margin-top:16px;border:1px solid var(--fin-line);border-radius:14px;background:#fff;overflow:hidden}.pt-fin-section__head{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 16px;border-bottom:1px solid #e8edf3}.pt-fin-section__head h3{margin:0;color:var(--fin-navy);font-size:15px}.pt-fin-section__head p{margin:4px 0 0;color:var(--fin-muted);font-size:11px}.pt-fin-section__body{padding:14px}.pt-fin-empty{padding:24px 16px;border:1px dashed #cbd8e5;border-radius:12px;background:#fbfdff;text-align:center;color:#8191a4;font-size:12px}.pt-fin-empty .pt-fin-btn{margin-top:12px}.pt-fin-milestone{display:grid;grid-template-columns:minmax(220px,1.25fr) minmax(290px,1fr) auto;gap:15px;align-items:center;padding:15px;border:1px solid #e2e9f1;border-radius:12px;background:#fff}.pt-fin-milestone+.pt-fin-milestone{margin-top:10px}.pt-fin-milestone__name{display:flex;gap:11px;min-width:0}.pt-fin-milestone__seq{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;flex:0 0 32px;border-radius:9px;background:#eaf8f5;color:#087866;font-size:11px;font-weight:900}.pt-fin-milestone__name h4{margin:0;color:var(--fin-navy);font-size:14px}.pt-fin-milestone__name p{margin:4px 0 0;color:#7e8da0;font-size:11px;line-height:1.4}.pt-fin-milestone__numbers{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.pt-fin-mini{padding:9px 10px;border-radius:9px;background:#f5f8fb}.pt-fin-mini small{display:block;color:#7c8da1;font-size:9px;text-transform:uppercase}.pt-fin-mini strong{display:block;margin-top:4px;color:#213c56;font-size:12px}.pt-fin-milestone__bar{grid-column:1/-1;height:6px;border-radius:999px;background:#e5ecf3;overflow:hidden}.pt-fin-milestone__bar span{display:block;height:100%;background:#16a38d}.pt-fin-milestone__actions{display:flex;align-items:center;justify-content:flex-end;gap:7px}.pt-fin-status{display:inline-flex;align-items:center;justify-content:center;min-height:25px;padding:4px 8px;border-radius:999px;background:#edf3f8;color:#50657a;font-size:9px;font-weight:850;white-space:nowrap}.pt-fin-status--paid,.pt-fin-status--confirmed{background:#e6f7f1;color:#087965}.pt-fin-status--partial,.pt-fin-status--pending{background:#fff5df;color:#986315}.pt-fin-status--overdue,.pt-fin-status--rejected{background:#fff0f1;color:#bb3440}.pt-fin-status--cancelled{background:#eef1f4;color:#687787}.pt-fin-more{position:relative}.pt-fin-more>summary{list-style:none}.pt-fin-more>summary::-webkit-details-marker{display:none}.pt-fin-more[open] .pt-fin-more__menu{display:block}.pt-fin-more__menu{display:none;position:absolute;right:0;top:39px;z-index:30;width:190px;padding:7px;border:1px solid var(--fin-line);border-radius:11px;background:#fff;box-shadow:0 12px 32px rgba(22,42,63,.14)}.pt-fin-more__menu button,.pt-fin-more__menu summary{display:flex;width:100%;align-items:center;gap:8px;min-height:34px;padding:7px 9px;border:0;border-radius:8px;background:transparent;color:#314b64;font-size:11px;text-align:left;cursor:pointer}.pt-fin-more__menu button:hover,.pt-fin-more__menu summary:hover{background:#f2f7fa}.pt-fin-more__menu form{margin:0}.pt-fin-history-filters{display:flex;gap:7px;flex-wrap:wrap}.pt-fin-filter{min-height:31px;padding:5px 10px;border:1px solid var(--fin-line);border-radius:999px;background:#fff;color:#607389;font-size:10px;font-weight:750;cursor:pointer}.pt-fin-filter.is-active{border-color:#89cfc3;background:#ecfaf7;color:#087866}.pt-fin-table-wrap{overflow:auto}.pt-fin-table{width:100%;border-collapse:collapse;min-width:900px}.pt-fin-table th,.pt-fin-table td{padding:11px 12px;border-bottom:1px solid #edf1f5;text-align:left;vertical-align:middle}.pt-fin-table th{background:#f7fafc;color:#76889c;font-size:9px;letter-spacing:.035em;text-transform:uppercase}.pt-fin-table td{color:#29435c;font-size:11px}.pt-fin-table td strong{color:#142f49}.pt-fin-table tr[hidden]{display:none}.pt-fin-proof{display:inline-flex;align-items:center;gap:5px;color:#0b7f70;font-weight:700;text-decoration:none}.pt-fin-pending-note{display:inline-flex;align-items:center;gap:6px;margin-left:8px;padding:5px 8px;border-radius:999px;background:#fff4dc;color:#94600e;font-size:10px;font-weight:750}.pt-fin-private{margin-top:16px;border:1px solid #dfe7f0;border-radius:14px;background:#fff;overflow:hidden}.pt-fin-private>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 16px;cursor:pointer;list-style:none;color:var(--fin-navy);font-size:14px;font-weight:800}.pt-fin-private>summary::-webkit-details-marker{display:none}.pt-fin-private__body{padding:0 16px 16px;border-top:1px solid #edf1f5}.pt-fin-private-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-top:14px}.pt-fin-private-stat{padding:12px;border-radius:10px;background:#f5f8fb}.pt-fin-private-stat small{display:block;color:#7b8ca0;font-size:9px;text-transform:uppercase}.pt-fin-private-stat strong{display:block;margin-top:5px;color:#1d3852;font-size:15px}.pt-fin-profit-state{margin-top:12px;padding:12px;border-radius:10px;background:#f4f8fb;color:#62768b;font-size:12px}.pt-fin-audit{margin-top:14px;padding-top:14px;border-top:1px solid #e8edf3}.pt-fin-audit h4{margin:0 0 9px;color:#213b54;font-size:13px}.pt-fin-audit-item{display:grid;grid-template-columns:120px 1fr;gap:12px;padding:9px 0;border-bottom:1px solid #edf1f5}.pt-fin-audit-item:last-child{border-bottom:0}.pt-fin-audit-item time{color:#8090a2;font-size:10px}.pt-fin-audit-item strong{display:block;color:#29425b;font-size:11px}.pt-fin-audit-item p{margin:3px 0 0;color:#7a8a9d;font-size:10px}.pt-fin-drawer[hidden]{display:none!important}.pt-fin-drawer{position:fixed;inset:0;z-index:1085}.pt-fin-drawer__backdrop{position:absolute;inset:0;border:0;background:rgba(8,23,40,.46);cursor:default}.pt-fin-drawer__panel{position:absolute;top:0;right:0;width:min(520px,96vw);height:100%;overflow:auto;padding:20px;background:#fff;box-shadow:-14px 0 40px rgba(8,27,47,.2)}.pt-fin-drawer__head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding-bottom:14px;border-bottom:1px solid #e6ecf2}.pt-fin-drawer__head h3{margin:0;color:var(--fin-navy);font-size:18px}.pt-fin-drawer__head p{margin:5px 0 0;color:#73859a;font-size:11px}.pt-fin-drawer__close{width:36px;height:36px;border:1px solid var(--fin-line);border-radius:10px;background:#fff;color:#53687e;cursor:pointer}.pt-fin-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;margin-top:16px}.pt-fin-form__full{grid-column:1/-1}.pt-fin-label{display:block;margin-bottom:5px;color:#3c5268;font-size:10px;font-weight:750}.pt-fin-input,.pt-fin-select,.pt-fin-textarea{width:100%;min-height:40px;padding:9px 11px;border:1px solid #d6e0ea;border-radius:10px;background:#fff;color:#19344d;font-size:12px;outline:0}.pt-fin-textarea{min-height:88px;resize:vertical}.pt-fin-input:focus,.pt-fin-select:focus,.pt-fin-textarea:focus{border-color:#48b5a3;box-shadow:0 0 0 3px rgba(11,157,135,.10)}.pt-fin-help{display:block;margin-top:4px;color:#8291a3;font-size:9px;line-height:1.45}.pt-fin-form__actions{display:flex;justify-content:flex-end;gap:8px;margin-top:4px}.pt-fin-reason{padding:10px 12px;border-radius:10px;background:#fff8ec;color:#8c5a1a;font-size:10px}.pt-fin-mobile-card{display:none}.pt-fin-v2.no-scroll{overflow:hidden}@media(max-width:1100px){.pt-fin-milestone{grid-template-columns:1fr}.pt-fin-milestone__actions{justify-content:flex-start}.pt-fin-private-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.pt-fin-v2__header{flex-direction:column}.pt-fin-actions{justify-content:flex-start}.pt-fin-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.pt-fin-stat{min-height:92px;padding:13px}.pt-fin-stat strong{font-size:18px}.pt-fin-section__head{align-items:flex-start;flex-direction:column}.pt-fin-milestone__numbers{grid-template-columns:1fr 1fr}.pt-fin-milestone__actions{align-items:flex-start;flex-wrap:wrap}.pt-fin-table thead{display:none}.pt-fin-table,.pt-fin-table tbody,.pt-fin-table tr,.pt-fin-table td{display:block;min-width:0;width:100%}.pt-fin-table tr{padding:13px;border-bottom:1px solid #e7edf3}.pt-fin-table td{display:grid;grid-template-columns:110px 1fr;gap:10px;padding:5px 0;border:0}.pt-fin-table td::before{content:attr(data-label);color:#7a8b9e;font-size:9px;font-weight:800;text-transform:uppercase}.pt-fin-private-grid{grid-template-columns:1fr 1fr}.pt-fin-form{grid-template-columns:1fr}.pt-fin-form__full{grid-column:auto}.pt-fin-drawer__panel{top:auto;bottom:0;width:100%;height:min(92vh,760px);border-radius:18px 18px 0 0}.pt-fin-audit-item{grid-template-columns:1fr;gap:3px}}@media(max-width:430px){.pt-fin-summary{grid-template-columns:1fr 1fr}.pt-fin-private-grid{grid-template-columns:1fr}.pt-fin-milestone__numbers{grid-template-columns:1fr}.pt-fin-table td{grid-template-columns:90px 1fr}.pt-fin-btn{width:100%}.pt-fin-actions .pt-fin-btn{width:auto}}

.pt-fin-pending-list{display:grid;gap:10px}.pt-fin-pending-card{display:grid;grid-template-columns:minmax(190px,1.1fr) minmax(190px,.9fr) minmax(160px,.8fr) auto;gap:14px;align-items:center;padding:14px;border:1px solid #f0d6a7;border-radius:12px;background:#fffaf2}.pt-fin-pending-card[hidden]{display:none}.pt-fin-pending-main strong{display:block;color:#17334d;font-size:13px}.pt-fin-pending-main small,.pt-fin-pending-meta small{display:block;margin-top:4px;color:#7a8a9c;font-size:10px}.pt-fin-pending-meta strong{display:block;color:#213c56;font-size:12px}.pt-fin-pending-actions{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}.pt-fin-readonly-note{display:inline-flex;align-items:center;gap:6px;min-height:27px;padding:4px 9px;border-radius:999px;background:#eef3f7;color:#5f7082;font-size:9px;font-weight:850}.pt-fin-section--pending{border-color:#efd8ad}.pt-fin-section--pending .pt-fin-section__head{background:#fffdf8}.pt-fin-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}.pt-fin-detail-item{padding:11px 12px;border:1px solid #e1e8ef;border-radius:10px;background:#f8fafc}.pt-fin-detail-item--full{grid-column:1/-1}.pt-fin-detail-item small{display:block;color:#7b8b9e;font-size:9px;font-weight:800;text-transform:uppercase}.pt-fin-detail-item strong,.pt-fin-detail-item span{display:block;margin-top:5px;color:#18344d;font-size:12px;word-break:break-word}.pt-fin-history-empty{padding:24px 16px;text-align:center;color:#8191a4;font-size:12px}.pt-fin-history-empty strong{display:block;margin-bottom:4px;color:#52677c}.pt-fin-history-only .pt-fin-table{min-width:780px}.pt-fin-history-only .pt-fin-milestone__actions{justify-content:flex-start}
@media(max-width:900px){.pt-fin-pending-card{grid-template-columns:1fr 1fr}.pt-fin-pending-actions{justify-content:flex-start;grid-column:1/-1}}
.pt-fin-adjustment-list{display:grid;gap:10px}.pt-fin-adjustment-card{display:grid;grid-template-columns:minmax(190px,1fr) minmax(170px,.8fr) minmax(180px,.9fr) auto;gap:14px;align-items:center;padding:14px;border:1px solid #d9d4f2;border-radius:12px;background:#fbfaff}.pt-fin-adjustment-card strong{display:block;color:#203752;font-size:12px}.pt-fin-adjustment-card small{display:block;margin-top:4px;color:#7e8da0;font-size:10px}.pt-fin-adjustment-delta{font-weight:850}.pt-fin-adjustment-delta.is-negative{color:#b83a46}.pt-fin-adjustment-delta.is-positive{color:#087965}.pt-fin-section--adjustment{border-color:#d9d4f2}.pt-fin-section--adjustment .pt-fin-section__head{background:#fcfbff}.pt-fin-adjustment-history{display:grid;gap:8px}.pt-fin-adjustment-history__item{display:grid;grid-template-columns:130px minmax(180px,1fr) minmax(150px,.8fr) minmax(170px,.9fr);gap:12px;padding:11px 0;border-bottom:1px solid #edf1f5}.pt-fin-adjustment-history__item:last-child{border-bottom:0}.pt-fin-adjustment-history__item small{color:#7d8da0;font-size:10px}.pt-fin-adjustment-history__item strong{color:#203a54;font-size:11px}
@media(max-width:900px){.pt-fin-adjustment-card{grid-template-columns:1fr 1fr}.pt-fin-adjustment-card .pt-fin-pending-actions{grid-column:1/-1}.pt-fin-adjustment-history__item{grid-template-columns:1fr 1fr}}
@media(max-width:760px){.pt-fin-pending-card{grid-template-columns:1fr}.pt-fin-pending-actions{grid-column:auto}.pt-fin-detail-grid{grid-template-columns:1fr}.pt-fin-detail-item--full{grid-column:auto}}

/* EGO_PROJECT_FINANCE_FIRST_PLAN_UX_V3_1 */
.pt-fin-btn--disabled,
.pt-fin-btn--disabled:hover{
    border-color:#dbe3eb!important;
    background:#f1f4f7!important;
    color:#94a1af!important;
    cursor:not-allowed!important;
    box-shadow:none!important;
}
.pt-fin-empty--first-plan{
    display:flex;
    min-height:230px;
    padding:34px 20px;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    border:1px dashed #8fd4c8;
    background:linear-gradient(180deg,#fbfffe 0%,#f1fbf8 100%);
}
.pt-fin-empty--first-plan .pt-fin-empty__icon{
    display:inline-flex;
    width:52px;
    height:52px;
    align-items:center;
    justify-content:center;
    margin-bottom:13px;
    border-radius:15px;
    background:#dff7f1;
    color:#078875;
    font-size:23px;
}
.pt-fin-empty--first-plan strong{
    color:#17354f;
    font-size:16px;
}
.pt-fin-empty--first-plan p{
    max-width:470px;
    margin:7px auto 0;
    color:#708297;
    font-size:12px;
    line-height:1.55;
}
.pt-fin-empty--first-plan .pt-fin-first-plan-btn{
    min-height:44px;
    margin-top:18px;
    padding:10px 18px;
    font-size:12px;
    box-shadow:0 8px 20px rgba(11,157,135,.18);
}
.pt-fin-empty--first-plan small{
    display:block;
    max-width:500px;
    margin-top:10px;
    color:#8291a3;
    font-size:10px;
    line-height:1.5;
}
@media(max-width:760px){
    .pt-fin-empty--first-plan{min-height:210px;padding:28px 16px}
    .pt-fin-empty--first-plan .pt-fin-first-plan-btn{width:100%;max-width:340px}
}
</style>

{{-- EGO_PROJECT_FINANCE_FIRST_PLAN_UX_V3_1 --}}
<section
    class="pt-panel pt-fin-v2"
    data-pt-panel="finance"
    data-finance-root
    data-fin-has-milestones="{{ $paymentMilestones->isNotEmpty() ? '1' : '0' }}"
    data-fin-auto-open-payment="{{ session('finance_auto_open_payment') ? '1' : '0' }}"
    data-fin-auto-milestone-id="{{ session('finance_auto_milestone_id') }}"
>
    <article class="pt-card pt-section">
        <div class="pt-fin-v2__header">
            <div>
                <h2><i class="bi bi-wallet2"></i> Doanh thu & thanh toán công trình</h2>
                <p>Theo dõi kế hoạch thu tiền, giao dịch thanh toán và công nợ của công trình.</p>
            </div>
            <span class="pt-fin-role">{{ $financeRoleLabel }}</span>
        </div>

        <div class="pt-fin-actions">
            @if($canEditRevenue)
                <button class="pt-fin-btn" type="button" data-fin-open="financeRevenueDrawer">
                    <i class="bi bi-pencil-square"></i> Chỉnh sửa giá trị công trình
                </button>
            @endif
            @if($canRecordPayment)
                @if($paymentMilestones->isNotEmpty())
                    <button class="pt-fin-btn pt-fin-btn--primary" type="button" data-fin-open="financePaymentDrawer">
                        <i class="bi bi-cash-coin"></i> Ghi nhận thu tiền
                    </button>
                @else
                    <button
                        class="pt-fin-btn pt-fin-btn--disabled"
                        type="button"
                        disabled
                        title="Hãy tạo đợt thanh toán đầu tiên trước"
                    >
                        <i class="bi bi-lock"></i> Ghi nhận thu tiền
                    </button>
                @endif
            @endif
            @if($canRequestAdjustment && $historyTransactions->where('status', 'confirmed')->isNotEmpty())
                <button class="pt-fin-btn" type="button" data-fin-open="financeAdjustmentDrawer">
                    <i class="bi bi-arrow-left-right"></i> Yêu cầu điều chỉnh
                </button>
            @endif
        </div>

        <div class="pt-fin-summary">
            <div class="pt-fin-stat">
                <small>Giá trị công trình</small>
                <strong>{{ number_format($totalRevenue, 0, ',', '.') }} đ</strong>
            </div>
            <div class="pt-fin-stat pt-fin-stat--paid">
                <small>Đã xác nhận thu</small>
                <strong>{{ number_format($confirmedCollected, 0, ',', '.') }} đ</strong>
            </div>
            <div class="pt-fin-stat pt-fin-stat--due">
                <small>Còn phải thu</small>
                <strong>{{ number_format($receivable, 0, ',', '.') }} đ</strong>
            </div>
            <div class="pt-fin-stat pt-fin-stat--overdue {{ $overdueAmount > 0 ? 'is-active' : '' }}">
                <small>Quá hạn</small>
                <strong>{{ number_format($overdueAmount, 0, ',', '.') }} đ</strong>
            </div>
        </div>

        <div class="pt-fin-progress-card">
            <div class="pt-fin-progress-card__top">
                <span>Tiến độ thu tiền</span>
                <strong>{{ number_format($collectionProgress, 1, ',', '.') }}%</strong>
            </div>
            <div class="pt-fin-progress"><span style="width:{{ $collectionProgress }}%"></span></div>
        </div>

        @if($planTotal > 0 && abs($planTotal - $totalRevenue) > 1)
            <div class="pt-fin-alert">
                Tổng kế hoạch hiện là <strong>{{ number_format($planTotal, 0, ',', '.') }} đ</strong>,
                khác giá trị phải thu <strong>{{ number_format($totalRevenue, 0, ',', '.') }} đ</strong>.
            </div>
        @endif

        <section class="pt-fin-section" id="financeMilestonesSection">
            <div class="pt-fin-section__head">
                <div>
                    <h3>Kế hoạch thanh toán</h3>
                    <p>Mỗi đợt có số tiền, hạn thu, điều kiện và tiến độ riêng.</p>
                </div>
                @if($canManagePaymentPlan && $paymentMilestones->isNotEmpty())
                    <button class="pt-fin-btn pt-fin-btn--sm" type="button" data-fin-open="financeMilestoneDrawer">
                        <i class="bi bi-plus-circle"></i> Thêm đợt
                    </button>
                @endif
            </div>
            <div class="pt-fin-section__body">
                @forelse($paymentMilestones as $milestone)
                    @php
                        $confirmed = (float) $milestone->transactions
                            ->where('status', 'confirmed')
                            ->sum(function ($transaction) use ($approvedAdjustmentByTransaction) {
                                return (float) $transaction->amount
                                    + (float) ($approvedAdjustmentByTransaction[$transaction->id] ?? 0);
                            });
                        $remaining = max(0, (float) $milestone->amount - $confirmed);
                        $progress = (float) $milestone->amount > 0
                            ? min(100, ($confirmed / (float) $milestone->amount) * 100)
                            : 0;
                        $displayStatus = $milestone->status;
                        if (
                            $displayStatus !== 'paid'
                            && $displayStatus !== 'partial'
                            && $milestone->due_date
                            && $milestone->due_date->isPast()
                            && $remaining > 0
                        ) {
                            $displayStatus = 'overdue';
                        }
                    @endphp
                    <article class="pt-fin-milestone">
                        <div class="pt-fin-milestone__name">
                            <span class="pt-fin-milestone__seq">{{ $milestone->sequence }}</span>
                            <div>
                                <h4>{{ $milestone->title }}</h4>
                                <p>{{ $milestone->condition_text ?: 'Chưa có điều kiện thanh toán riêng.' }}</p>
                                <span class="pt-fin-status pt-fin-status--{{ $displayStatus }}">
                                    {{ $milestoneStatusLabels[$displayStatus] ?? $displayStatus }}
                                </span>
                            </div>
                        </div>

                        <div class="pt-fin-milestone__numbers">
                            <div class="pt-fin-mini">
                                <small>Dự kiến</small>
                                <strong>{{ number_format((float) $milestone->amount, 0, ',', '.') }} đ</strong>
                            </div>
                            <div class="pt-fin-mini">
                                <small>Đã thu</small>
                                <strong>{{ number_format($confirmed, 0, ',', '.') }} đ</strong>
                            </div>
                            <div class="pt-fin-mini">
                                <small>Còn thiếu</small>
                                <strong>{{ number_format($remaining, 0, ',', '.') }} đ</strong>
                            </div>
                            <div class="pt-fin-mini">
                                <small>Hạn thanh toán</small>
                                <strong>{{ optional($milestone->due_date)->format('d/m/Y') ?: 'Chưa đặt hạn' }}</strong>
                            </div>
                            <div class="pt-fin-milestone__bar"><span style="width:{{ $progress }}%"></span></div>
                        </div>

                        <div class="pt-fin-milestone__actions">
                            @if($canRecordPayment && $remaining > 0)
                                <button
                                    class="pt-fin-btn pt-fin-btn--primary pt-fin-btn--sm"
                                    type="button"
                                    data-fin-record-payment="{{ $milestone->id }}"
                                >
                                    Ghi nhận thanh toán
                                </button>
                            @endif
                            <button
                                class="pt-fin-btn pt-fin-btn--sm"
                                type="button"
                                data-fin-filter-milestone="{{ $milestone->id }}"
                            >
                                Xem giao dịch
                            </button>
                            @if($canManagePaymentPlan)
                                <details class="pt-fin-more">
                                    <summary class="pt-fin-btn pt-fin-btn--icon pt-fin-btn--sm"><i class="bi bi-three-dots"></i></summary>
                                    <div class="pt-fin-more__menu">
                                        <button
                                            type="button"
                                            data-fin-edit-milestone
                                            data-action="{{ route('project-test.payments.milestones.update', [$project, $milestone]) }}"
                                            data-title="{{ $milestone->title }}"
                                            data-amount="{{ $milestone->amount }}"
                                            data-percentage="{{ $milestone->percentage }}"
                                            data-due-date="{{ optional($milestone->due_date)->format('Y-m-d') }}"
                                            data-condition="{{ $milestone->condition_text }}"
                                            data-note="{{ $milestone->note }}"
                                        >
                                            <i class="bi bi-pencil"></i> Chỉnh sửa đợt
                                        </button>
                                        @if($milestone->transactions->isEmpty())
                                            <form
                                                method="POST"
                                                action="{{ route('project-test.payments.milestones.destroy', [$project, $milestone]) }}"
                                                onsubmit="return confirm('Xóa đợt thanh toán này?')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"><i class="bi bi-trash"></i> Xóa đợt</button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="pt-fin-empty pt-fin-empty--first-plan">
                        <span class="pt-fin-empty__icon"><i class="bi bi-calendar2-plus"></i></span>
                        <strong>Chưa có kế hoạch thanh toán</strong>
                        <p>Cần tạo ít nhất một đợt trước khi ghi nhận khách hàng thanh toán.</p>

                        @if($canManagePaymentPlan)
                            <button
                                class="pt-fin-btn pt-fin-btn--primary pt-fin-first-plan-btn"
                                type="button"
                                data-fin-open="financeMilestoneDrawer"
                            >
                                <i class="bi bi-plus-circle"></i>
                                Tạo đợt thanh toán đầu tiên
                            </button>
                            <small>Sau khi lưu đợt đầu tiên, hệ thống sẽ tự mở màn hình ghi nhận thu tiền.</small>
                        @else
                            <small>Bạn chưa có quyền tạo kế hoạch thanh toán. Vui lòng liên hệ Sales phụ trách hoặc Kế toán.</small>
                        @endif
                    </div>
                @endforelse
            </div>
        </section>

        @if($pendingTransactions->isNotEmpty())
            <section class="pt-fin-section pt-fin-section--pending" id="financePendingSection">
                <div class="pt-fin-section__head">
                    <div>
                        <h3>Giao dịch chờ xác nhận</h3>
                        <p>Sales đã ghi nhận; Kế toán/Admin kiểm tra tiền vào tài khoản trước khi cộng vào công nợ.</p>
                    </div>
                    <span class="pt-fin-pending-note">
                        <i class="bi bi-hourglass-split"></i>
                        {{ $pendingTransactions->count() }} giao dịch ·
                        {{ number_format($pendingCollected, 0, ',', '.') }} đ
                    </span>
                </div>

                <div class="pt-fin-section__body">
                    <div class="pt-fin-pending-list">
                        @foreach($pendingTransactions as $transaction)
                            <article
                                class="pt-fin-pending-card"
                                data-fin-transaction-record
                                data-status="pending"
                                data-milestone="{{ $transaction->milestone_id ?: 'none' }}"
                            >
                                <div class="pt-fin-pending-main">
                                    <strong>{{ $transaction->transaction_code }}</strong>
                                    <small>
                                        {{ optional($transaction->paid_at)->format('d/m/Y') }}
                                        · {{ $transaction->milestone?->title ?: 'Chưa gắn đợt' }}
                                    </small>
                                </div>

                                <div class="pt-fin-pending-meta">
                                    <small>Số tiền chờ xác nhận</small>
                                    <strong>{{ number_format((float) $transaction->amount, 0, ',', '.') }} đ</strong>
                                </div>

                                <div class="pt-fin-pending-meta">
                                    <small>Người ghi nhận</small>
                                    <strong>{{ $transaction->recorder?->name ?: 'Hệ thống' }}</strong>
                                    <small>{{ $paymentMethods[$transaction->payment_method] ?? $transaction->payment_method }}</small>
                                </div>

                                <div class="pt-fin-pending-actions">
                                    <button
                                        class="pt-fin-btn pt-fin-btn--sm"
                                        type="button"
                                        data-fin-view-transaction
                                        data-code="{{ $transaction->transaction_code }}"
                                        data-date="{{ optional($transaction->paid_at)->format('d/m/Y') }}"
                                        data-milestone="{{ $transaction->milestone?->title ?: 'Chưa gắn đợt' }}"
                                        data-amount="{{ number_format((float) $transaction->amount, 0, ',', '.') }} đ"
                                        data-method="{{ $paymentMethods[$transaction->payment_method] ?? $transaction->payment_method }}"
                                        data-account="{{ $transaction->receiving_account ?: '—' }}"
                                        data-reference="{{ $transaction->reference_no ?: '—' }}"
                                        data-payer="{{ $transaction->payer_name ?: '—' }}"
                                        data-status="{{ $paymentStatusLabels[$transaction->status] ?? $transaction->status }}"
                                        data-recorder="{{ $transaction->recorder?->name ?: 'Hệ thống' }}"
                                        data-confirmer="Chưa xác nhận"
                                        data-confirmed-at="—"
                                        data-note="{{ $transaction->note ?: '—' }}"
                                        data-reason="—"
                                        data-proof="{{ $transaction->proof_path ? route('project-test.payments.transactions.proof', [$project, $transaction]) : '' }}"
                                    >
                                        <i class="bi bi-eye"></i> Xem
                                    </button>

                                    @if($canConfirmPayment)
                                        <form method="POST" action="{{ route('project-test.payments.transactions.confirm', [$project, $transaction]) }}">
                                            @csrf
                                            <button class="pt-fin-btn pt-fin-btn--primary pt-fin-btn--sm" type="submit">
                                                <i class="bi bi-check2-circle"></i> Xác nhận
                                            </button>
                                        </form>

                                        <details class="pt-fin-more">
                                            <summary class="pt-fin-btn pt-fin-btn--icon pt-fin-btn--sm"><i class="bi bi-three-dots"></i></summary>
                                            <div class="pt-fin-more__menu">
                                                <details>
                                                    <summary><i class="bi bi-x-circle"></i> Từ chối giao dịch</summary>
                                                    <form method="POST" action="{{ route('project-test.payments.transactions.reject', [$project, $transaction]) }}">
                                                        @csrf
                                                        <textarea class="pt-fin-textarea" name="reason" required placeholder="Nhập lý do từ chối"></textarea>
                                                        <button class="pt-fin-btn pt-fin-btn--danger pt-fin-btn--sm" type="submit">Xác nhận từ chối</button>
                                                    </form>
                                                </details>
                                            </div>
                                        </details>
                                    @elseif((int) $transaction->recorded_by === (int) auth()->id())
                                        <details class="pt-fin-more">
                                            <summary class="pt-fin-btn pt-fin-btn--icon pt-fin-btn--sm"><i class="bi bi-three-dots"></i></summary>
                                            <div class="pt-fin-more__menu">
                                                <details>
                                                    <summary><i class="bi bi-x-circle"></i> Hủy ghi nhận</summary>
                                                    <form method="POST" action="{{ route('project-test.payments.transactions.cancel', [$project, $transaction]) }}">
                                                        @csrf
                                                        <textarea class="pt-fin-textarea" name="reason" required placeholder="Nhập lý do hủy giao dịch chờ xác nhận"></textarea>
                                                        <button class="pt-fin-btn pt-fin-btn--danger pt-fin-btn--sm" type="submit">Hủy ghi nhận</button>
                                                    </form>
                                                </details>
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if($pendingAdjustments->isNotEmpty())
            <section class="pt-fin-section pt-fin-section--adjustment" id="financeAdjustmentsPendingSection">
                <div class="pt-fin-section__head">
                    <div>
                        <h3>Yêu cầu điều chỉnh chờ duyệt</h3>
                        <p>Không sửa giao dịch gốc; hệ thống tạo bút toán điều chỉnh riêng sau khi Kế toán/Admin duyệt.</p>
                    </div>
                    <span class="pt-fin-pending-note">
                        <i class="bi bi-arrow-left-right"></i>
                        {{ $pendingAdjustments->count() }} yêu cầu
                    </span>
                </div>
                <div class="pt-fin-section__body">
                    <div class="pt-fin-adjustment-list">
                        @foreach($pendingAdjustments as $adjustment)
                            <article class="pt-fin-adjustment-card">
                                <div>
                                    <strong>{{ $adjustment->adjustment_code }}</strong>
                                    <small>Giao dịch gốc: {{ $adjustment->transaction?->transaction_code ?: '—' }}</small>
                                </div>
                                <div>
                                    <small>Số tiền gốc → đúng</small>
                                    <strong>
                                        {{ number_format((float) $adjustment->original_amount, 0, ',', '.') }} đ
                                        → {{ number_format((float) $adjustment->correct_amount, 0, ',', '.') }} đ
                                    </strong>
                                </div>
                                <div>
                                    <small>Chênh lệch</small>
                                    <strong class="pt-fin-adjustment-delta {{ (float) $adjustment->delta_amount < 0 ? 'is-negative' : 'is-positive' }}">
                                        {{ (float) $adjustment->delta_amount > 0 ? '+' : '' }}{{ number_format((float) $adjustment->delta_amount, 0, ',', '.') }} đ
                                    </strong>
                                    <small>{{ $adjustment->requester?->name ?: 'Hệ thống' }} · {{ $adjustment->created_at->format('d/m/Y H:i') }}</small>
                                </div>
                                <div class="pt-fin-pending-actions">
                                    @if($canReviewAdjustment)
                                        <form method="POST" action="{{ route('project-test.payments.adjustments.approve', [$project, $adjustment]) }}">
                                            @csrf
                                            <button class="pt-fin-btn pt-fin-btn--primary pt-fin-btn--sm" type="submit">
                                                <i class="bi bi-check2-circle"></i> Duyệt
                                            </button>
                                        </form>
                                        <details class="pt-fin-more">
                                            <summary class="pt-fin-btn pt-fin-btn--icon pt-fin-btn--sm"><i class="bi bi-three-dots"></i></summary>
                                            <div class="pt-fin-more__menu">
                                                <details>
                                                    <summary><i class="bi bi-x-circle"></i> Từ chối</summary>
                                                    <form method="POST" action="{{ route('project-test.payments.adjustments.reject', [$project, $adjustment]) }}">
                                                        @csrf
                                                        <textarea class="pt-fin-textarea" name="review_note" required placeholder="Nhập lý do từ chối"></textarea>
                                                        <button class="pt-fin-btn pt-fin-btn--danger pt-fin-btn--sm" type="submit">Xác nhận từ chối</button>
                                                    </form>
                                                </details>
                                            </div>
                                        </details>
                                    @else
                                        <span class="pt-fin-status pt-fin-status--pending">Chờ Kế toán/Admin</span>
                                    @endif
                                </div>
                                <div style="grid-column:1/-1">
                                    <small><strong>Lý do:</strong> {{ $adjustment->reason }}</small>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="pt-fin-section pt-fin-history-only" id="financeTransactionsSection">
            <div class="pt-fin-section__head">
                <div>
                    <h3>Lịch sử thanh toán</h3>
                    <p>Nhật ký chỉ đọc của các giao dịch đã xử lý; không thêm, sửa hoặc xóa trực tiếp tại đây.</p>
                </div>
                <span class="pt-fin-readonly-note">
                    <i class="bi bi-lock"></i> CHỈ XEM
                </span>
            </div>

            @if($historyTransactions->isNotEmpty())
                <div class="pt-fin-section__body">
                    <div class="pt-fin-history-filters" data-fin-status-filters>
                        <button class="pt-fin-filter is-active" type="button" data-status="all">Tất cả</button>
                        <button class="pt-fin-filter" type="button" data-status="confirmed">Đã xác nhận</button>
                        <button class="pt-fin-filter" type="button" data-status="rejected">Bị từ chối</button>
                        <button class="pt-fin-filter" type="button" data-status="cancelled">Đã hủy</button>
                    </div>
                </div>

                <div class="pt-fin-table-wrap">
                    <table class="pt-fin-table">
                        <thead>
                            <tr>
                                <th>Ngày / mã</th>
                                <th>Đợt thanh toán</th>
                                <th>Số tiền</th>
                                <th>Phương thức</th>
                                <th>Trạng thái</th>
                                <th>Ghi nhận / xác nhận</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody data-fin-transaction-body>
                            @foreach($historyTransactions as $transaction)
                                <tr
                                    data-fin-transaction-record
                                    data-status="{{ $transaction->status }}"
                                    data-milestone="{{ $transaction->milestone_id ?: 'none' }}"
                                >
                                    <td data-label="Ngày / mã">
                                        <strong>{{ optional($transaction->paid_at)->format('d/m/Y') }}</strong><br>
                                        <small>{{ $transaction->transaction_code }}</small>
                                    </td>
                                    <td data-label="Đợt thanh toán">{{ $transaction->milestone?->title ?: 'Chưa gắn đợt' }}</td>
                                    <td data-label="Số tiền">
                                        <strong>{{ number_format((float) $transaction->amount, 0, ',', '.') }} đ</strong><br>
                                        <small>{{ $transaction->reference_no ?: 'Không có mã giao dịch' }}</small>
                                    </td>
                                    <td data-label="Phương thức">
                                        {{ $paymentMethods[$transaction->payment_method] ?? $transaction->payment_method }}<br>
                                        <small>{{ $transaction->receiving_account ?: '—' }}</small>
                                    </td>
                                    <td data-label="Trạng thái">
                                        <span class="pt-fin-status pt-fin-status--{{ $transaction->status }}">
                                            {{ $paymentStatusLabels[$transaction->status] ?? $transaction->status }}
                                        </span>
                                    </td>
                                    <td data-label="Ghi nhận / xác nhận">
                                        {{ $transaction->recorder?->name ?: 'Hệ thống' }}
                                        @if($transaction->confirmer)
                                            <br><small>{{ $transaction->confirmer->name }} · {{ optional($transaction->confirmed_at)->format('d/m/Y H:i') }}</small>
                                        @elseif($transaction->canceller)
                                            <br><small>Hủy bởi {{ $transaction->canceller->name }} · {{ optional($transaction->cancelled_at)->format('d/m/Y H:i') }}</small>
                                        @else
                                            <br><small>Chưa có người xác nhận</small>
                                        @endif
                                    </td>
                                    <td data-label="Chi tiết">
                                        <div class="pt-fin-milestone__actions">
                                            <button
                                                class="pt-fin-btn pt-fin-btn--sm"
                                                type="button"
                                                data-fin-view-transaction
                                                data-code="{{ $transaction->transaction_code }}"
                                                data-transaction-id="{{ $transaction->id }}"
                                                data-raw-amount="{{ (float) $transaction->amount }}"
                                                data-fin-update-amount-url="{{ route('project-test.payments.transactions.update-amount', [$project, $transaction]) }}"
                                                data-date="{{ optional($transaction->paid_at)->format('d/m/Y') }}"
                                                data-milestone="{{ $transaction->milestone?->title ?: 'Chưa gắn đợt' }}"
                                                data-amount="{{ number_format((float) $transaction->amount, 0, ',', '.') }} đ"
                                                data-method="{{ $paymentMethods[$transaction->payment_method] ?? $transaction->payment_method }}"
                                                data-account="{{ $transaction->receiving_account ?: '—' }}"
                                                data-reference="{{ $transaction->reference_no ?: '—' }}"
                                                data-payer="{{ $transaction->payer_name ?: '—' }}"
                                                data-status="{{ $paymentStatusLabels[$transaction->status] ?? $transaction->status }}"
                                                data-recorder="{{ $transaction->recorder?->name ?: 'Hệ thống' }}"
                                                data-confirmer="{{ $transaction->confirmer?->name ?: ($transaction->canceller?->name ?: '—') }}"
                                                data-confirmed-at="{{ optional($transaction->confirmed_at ?: $transaction->cancelled_at)->format('d/m/Y H:i') ?: '—' }}"
                                                data-note="{{ $transaction->note ?: '—' }}"
                                                data-reason="{{ $transaction->rejection_reason ?: ($transaction->cancellation_reason ?: '—') }}"
                                                data-proof="{{ $transaction->proof_path ? route('project-test.payments.transactions.proof', [$project, $transaction]) : '' }}"
                                            >
                                                <i class="bi bi-eye"></i> Xem chi tiết
                                            </button>
                                            @if($transaction->proof_path)
                                                <a
                                                    class="pt-fin-btn pt-fin-btn--sm"
                                                    href="{{ route('project-test.payments.transactions.proof', [$project, $transaction]) }}"
                                                >
                                                    <i class="bi bi-paperclip"></i> Chứng từ
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="pt-fin-history-empty">
                    <strong>Chưa có lịch sử thanh toán.</strong>
                    Các giao dịch sẽ xuất hiện tại đây sau khi được Kế toán/Admin xử lý.
                </div>
            @endif
        </section>

        @if($adjustmentHistory->isNotEmpty())
            <section class="pt-fin-section">
                <div class="pt-fin-section__head">
                    <div>
                        <h3>Lịch sử điều chỉnh thanh toán</h3>
                        <p>Nhật ký chỉ đọc; giao dịch gốc luôn được giữ nguyên để đối soát.</p>
                    </div>
                    <span class="pt-fin-readonly-note"><i class="bi bi-lock"></i> CHỈ XEM</span>
                </div>
                <div class="pt-fin-section__body">
                    <div class="pt-fin-adjustment-history">
                        @foreach($adjustmentHistory as $adjustment)
                            <div class="pt-fin-adjustment-history__item">
                                <div>
                                    <strong>{{ $adjustment->adjustment_code }}</strong><br>
                                    <small>{{ $adjustment->created_at->format('d/m/Y H:i') }}</small>
                                </div>
                                <div>
                                    <strong>{{ $adjustment->transaction?->transaction_code ?: '—' }}</strong><br>
                                    <small>{{ $adjustment->reason }}</small>
                                </div>
                                <div>
                                    <strong class="pt-fin-adjustment-delta {{ (float) $adjustment->delta_amount < 0 ? 'is-negative' : 'is-positive' }}">
                                        {{ (float) $adjustment->delta_amount > 0 ? '+' : '' }}{{ number_format((float) $adjustment->delta_amount, 0, ',', '.') }} đ
                                    </strong><br>
                                    <small>{{ $adjustmentStatusLabels[$adjustment->status] ?? $adjustment->status }}</small>
                                </div>
                                <div>
                                    <strong>{{ $adjustment->reviewer?->name ?: 'Chưa có người duyệt' }}</strong><br>
                                    <small>{{ optional($adjustment->reviewed_at)->format('d/m/Y H:i') ?: '—' }} {{ $adjustment->review_note ? '· '.$adjustment->review_note : '' }}</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if($canViewProfit)
            <details class="pt-fin-private">
                <summary>
                    <span><i class="bi bi-shield-lock"></i> Chi phí & lợi nhuận nội bộ</span>
                    <span class="pt-fin-role">CHỈ ADMIN / GIÁM ĐỐC</span>
                </summary>
                <div class="pt-fin-private__body">
                    @if($financialSummary['has_cost_data'] ?? false)
                        <div class="pt-fin-private-grid">
                            <div class="pt-fin-private-stat"><small>Giá vốn vật tư</small><strong>{{ number_format((float) $financialSummary['material_cost'], 0, ',', '.') }} đ</strong></div>
                            <div class="pt-fin-private-stat"><small>Sổ chi phí đã duyệt</small><strong>{{ number_format((float) ($financialSummary['expense_cost'] ?? 0), 0, ',', '.') }} đ</strong></div>
                            @if((float) ($financialSummary['legacy_manual_cost'] ?? 0) > 0)
                                <div class="pt-fin-private-stat"><small>Chi phí tổng hợp cũ</small><strong>{{ number_format((float) $financialSummary['legacy_manual_cost'], 0, ',', '.') }} đ</strong></div>
                            @endif
                            <div class="pt-fin-private-stat"><small>Tổng chi phí</small><strong>{{ number_format((float) $financialSummary['total_cost'], 0, ',', '.') }} đ</strong></div>
                            <div class="pt-fin-private-stat"><small>Lợi nhuận gộp</small><strong>{{ number_format((float) $financialSummary['gross_profit'], 0, ',', '.') }} đ</strong></div>
                        </div>
                        <div class="pt-fin-profit-state">
                            Tỷ suất lợi nhuận: <strong>{{ number_format((float) $financialSummary['gross_margin'], 1, ',', '.') }}%</strong>
                        </div>
                    @else
                        <div class="pt-fin-profit-state">
                            Chưa có dữ liệu chi phí được xác nhận. <strong>Lợi nhuận: Chưa đủ dữ liệu.</strong>
                        </div>
                    @endif

                    @if($showProjectExpenseLedger ?? false)
                        <div class="pt-fin-actions" style="margin-top:12px;margin-bottom:0">
                            <button class="pt-fin-btn" type="button" data-fin-go-expenses>
                                <i class="bi bi-receipt-cutoff"></i> Mở sổ chi phí công trình
                            </button>
                        </div>
                    @endif

                    <div class="pt-fin-audit">
                        <h4>Lịch sử điều chỉnh tài chính</h4>
                        @forelse($financeHistories as $history)
                            <div class="pt-fin-audit-item">
                                <time>{{ $history->created_at->format('d/m/Y H:i') }}</time>
                                <div>
                                    <strong>{{ $history->action }}</strong>
                                    <p>{{ $history->user?->name ?: 'Hệ thống' }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="pt-fin-empty">Chưa có lịch sử điều chỉnh tài chính.</div>
                        @endforelse
                    </div>
                </div>
            </details>
        @endif
    </article>

    @if($canManagePaymentPlan)
        <div class="pt-fin-drawer" id="financeMilestoneDrawer" hidden aria-hidden="true">
            <button class="pt-fin-drawer__backdrop" type="button" data-fin-close></button>
            <aside class="pt-fin-drawer__panel" role="dialog" aria-modal="true" aria-label="Thêm đợt thanh toán">
                <div class="pt-fin-drawer__head">
                    <div><h3>Thêm đợt thanh toán</h3><p>Lập kế hoạch thu tiền theo hợp đồng công trình.</p></div>
                    <button class="pt-fin-drawer__close" type="button" data-fin-close><i class="bi bi-x-lg"></i></button>
                </div>
                <form
                    method="POST"
                    action="{{ route('project-test.payments.milestones.store', $project) }}"
                    class="pt-fin-form"
                    data-fin-milestone-form
                    data-project-total="{{ $totalRevenue }}"
                    data-existing-plan="{{ $planTotal }}"
                >
                    @csrf
                    <div class="pt-fin-form__full">
                        <label class="pt-fin-label">Tên đợt thanh toán</label>
                        <input class="pt-fin-input" name="title" placeholder="VD: Đợt 1 - Đặt cọc" required>
                    </div>
                    <div>
                        <label class="pt-fin-label">Số tiền dự kiến</label>
                        <input class="pt-fin-input" type="number" name="amount" min="1" step="1" inputmode="numeric" data-fin-amount required>
                    </div>
                    <div>
                        <label class="pt-fin-label">Tỷ lệ (%)</label>
                        <input class="pt-fin-input" type="number" name="percentage" min="0" max="100" step="0.01" data-fin-percentage>
                        <small class="pt-fin-help">Nhập tỷ lệ để hệ thống tự tính số tiền.</small>
                    </div>
                    <div>
                        <label class="pt-fin-label">Hạn thanh toán</label>
                        <input class="pt-fin-input" type="date" name="due_date">
                    </div>
                    <div class="pt-fin-form__full">
                        <label class="pt-fin-label">Điều kiện thanh toán</label>
                        <textarea class="pt-fin-textarea" name="condition_text" placeholder="VD: Sau khi tập kết vật tư"></textarea>
                    </div>
                    <div class="pt-fin-form__full">
                        <label class="pt-fin-label">Ghi chú</label>
                        <textarea class="pt-fin-textarea" name="note"></textarea>
                    </div>
                    <div class="pt-fin-form__full pt-fin-alert" data-fin-plan-warning hidden></div>
                    <div class="pt-fin-form__full pt-fin-form__actions">
                        <button class="pt-fin-btn" type="button" data-fin-close>Hủy</button>
                        <button class="pt-fin-btn pt-fin-btn--primary" type="submit">Lưu đợt thanh toán</button>
                    </div>
                </form>
            </aside>
        </div>

        <div class="pt-fin-drawer" id="financeMilestoneEditDrawer" hidden aria-hidden="true">
            <button class="pt-fin-drawer__backdrop" type="button" data-fin-close></button>
            <aside class="pt-fin-drawer__panel" role="dialog" aria-modal="true" aria-label="Chỉnh sửa đợt thanh toán">
                <div class="pt-fin-drawer__head">
                    <div><h3>Chỉnh sửa đợt thanh toán</h3><p>Cập nhật số tiền, hạn thu và điều kiện của đợt.</p></div>
                    <button class="pt-fin-drawer__close" type="button" data-fin-close><i class="bi bi-x-lg"></i></button>
                </div>
                <form method="POST" action="" class="pt-fin-form" data-fin-edit-milestone-form>
                    @csrf
                    @method('PUT')
                    <div class="pt-fin-form__full"><label class="pt-fin-label">Tên đợt</label><input class="pt-fin-input" name="title" required></div>
                    <div><label class="pt-fin-label">Số tiền</label><input class="pt-fin-input" type="number" name="amount" min="1" step="1" inputmode="numeric" required></div>
                    <div><label class="pt-fin-label">Tỷ lệ (%)</label><input class="pt-fin-input" type="number" name="percentage" min="0" max="100" step="0.01"></div>
                    <div><label class="pt-fin-label">Hạn thanh toán</label><input class="pt-fin-input" type="date" name="due_date"></div>
                    <div class="pt-fin-form__full"><label class="pt-fin-label">Điều kiện</label><textarea class="pt-fin-textarea" name="condition_text"></textarea></div>
                    <div class="pt-fin-form__full"><label class="pt-fin-label">Ghi chú</label><textarea class="pt-fin-textarea" name="note"></textarea></div>
                    <div class="pt-fin-form__full pt-fin-form__actions"><button class="pt-fin-btn" type="button" data-fin-close>Hủy</button><button class="pt-fin-btn pt-fin-btn--primary" type="submit">Lưu thay đổi</button></div>
                </form>
            </aside>
        </div>
    @endif

    @if($canRecordPayment)
        <div class="pt-fin-drawer" id="financePaymentDrawer" hidden aria-hidden="true">
            <button class="pt-fin-drawer__backdrop" type="button" data-fin-close></button>
            <aside class="pt-fin-drawer__panel" role="dialog" aria-modal="true" aria-label="Ghi nhận thu tiền">
                <div class="pt-fin-drawer__head">
                    <div><h3>Ghi nhận thu tiền</h3><p>Mỗi lần nhận tiền được lưu thành một giao dịch riêng.</p></div>
                    <button class="pt-fin-drawer__close" type="button" data-fin-close><i class="bi bi-x-lg"></i></button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.payments.transactions.store', $project) }}" class="pt-fin-form">
                    @csrf
                    <div class="pt-fin-form__full">
                        <label class="pt-fin-label">Đợt thanh toán</label>
                        <select class="pt-fin-select" name="milestone_id" data-fin-payment-milestone>
                            <option value="">-- Chưa gắn đợt --</option>
                            @foreach($paymentMilestones as $milestone)
                                @php
                                    $committedForOption = (float) $milestone->transactions
                                        ->whereIn('status', ['pending', 'confirmed'])
                                        ->sum('amount')
                                        + (float) $milestone->transactions
                                            ->where('status', 'confirmed')
                                            ->sum(fn ($transaction) => (float) ($approvedAdjustmentByTransaction[$transaction->id] ?? 0));
                                    $remainingForOption = max(0, (float) $milestone->amount - $committedForOption);
                                @endphp
                                <option value="{{ $milestone->id }}" data-remaining="{{ $remainingForOption }}">Đợt {{ $milestone->sequence }} · {{ $milestone->title }} · còn {{ number_format($remainingForOption, 0, ',', '.') }} đ</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="pt-fin-label">Ngày nhận tiền</label><input class="pt-fin-input" type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" required></div>
                    <div><label class="pt-fin-label">Số tiền nhận</label><input class="pt-fin-input" type="number" name="amount" min="1" step="1" inputmode="numeric" required data-fin-payment-amount><small class="pt-fin-help">Chọn đợt để hệ thống gợi ý đúng số tiền còn lại.</small></div>
                    <div><label class="pt-fin-label">Phương thức</label><select class="pt-fin-select" name="payment_method" required>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label class="pt-fin-label">Tài khoản nhận</label><input class="pt-fin-input" name="receiving_account" placeholder="Ngân hàng / số tài khoản / quỹ tiền mặt"></div>
                    <div><label class="pt-fin-label">Mã giao dịch / UNC</label><input class="pt-fin-input" name="reference_no"></div>
                    <div><label class="pt-fin-label">Người thanh toán</label><input class="pt-fin-input" name="payer_name"></div>
                    <div class="pt-fin-form__full"><label class="pt-fin-label">Chứng từ</label><input class="pt-fin-input" type="file" name="proof_file" accept="image/*,.pdf"><small class="pt-fin-help">Ảnh hoặc PDF, tối đa 10 MB.</small></div>
                    <div class="pt-fin-form__full"><label class="pt-fin-label">Ghi chú</label><textarea class="pt-fin-textarea" name="note"></textarea></div>
                    @if(!$canConfirmPayment)
                        <div class="pt-fin-form__full pt-fin-reason"><i class="bi bi-info-circle"></i> Giao dịch do Sales ghi nhận sẽ ở trạng thái Chờ Kế toán xác nhận.</div>
                    @endif
                    <div class="pt-fin-form__full pt-fin-form__actions"><button class="pt-fin-btn" type="button" data-fin-close>Hủy</button><button class="pt-fin-btn pt-fin-btn--primary" type="submit">{{ $canConfirmPayment ? 'Ghi nhận & xác nhận thu' : 'Gửi Kế toán xác nhận' }}</button></div>
                </form>
            </aside>
        </div>
    @endif

    @if($canRequestAdjustment && $historyTransactions->where('status', 'confirmed')->isNotEmpty())
        <div class="pt-fin-drawer" id="financeAdjustmentDrawer" hidden aria-hidden="true">
            <button class="pt-fin-drawer__backdrop" type="button" data-fin-close></button>
            <aside class="pt-fin-drawer__panel" role="dialog" aria-modal="true" aria-label="Yêu cầu điều chỉnh giao dịch">
                <div class="pt-fin-drawer__head">
                    <div>
                        <h3>Yêu cầu điều chỉnh giao dịch</h3>
                        <p>Không sửa giao dịch gốc. Hệ thống sẽ tạo bút toán chênh lệch sau khi được duyệt.</p>
                    </div>
                    <button class="pt-fin-drawer__close" type="button" data-fin-close><i class="bi bi-x-lg"></i></button>
                </div>
                <form method="POST" action="{{ route('project-test.payments.adjustments.store', $project) }}" class="pt-fin-form" data-fin-adjustment-form>
                    @csrf
                    <div class="pt-fin-form__full">
                        <label class="pt-fin-label">Giao dịch cần điều chỉnh</label>
                        <select class="pt-fin-select" name="transaction_id" required data-fin-adjustment-transaction>
                            <option value="">-- Chọn giao dịch đã xác nhận --</option>
                            @foreach($historyTransactions->where('status', 'confirmed') as $transaction)
                                @php
                                    $approvedDelta = (float) ($approvedAdjustmentByTransaction[$transaction->id] ?? 0);
                                    $effectiveAmount = max(0, (float) $transaction->amount + $approvedDelta);
                                @endphp
                                <option
                                    value="{{ $transaction->id }}"
                                    data-current-amount="{{ $effectiveAmount }}"
                                >
                                    {{ $transaction->transaction_code }} · {{ number_format($effectiveAmount, 0, ',', '.') }} đ
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="pt-fin-label">Số tiền đúng sau điều chỉnh</label>
                        <input class="pt-fin-input" type="number" name="correct_amount" min="0" step="1" inputmode="numeric" required data-fin-adjustment-correct>
                    </div>
                    <div>
                        <label class="pt-fin-label">Chênh lệch dự kiến</label>
                        <input class="pt-fin-input" type="text" value="0 đ" readonly data-fin-adjustment-delta>
                    </div>
                    <div class="pt-fin-form__full">
                        <label class="pt-fin-label">Lý do điều chỉnh</label>
                        <textarea class="pt-fin-textarea" name="reason" required placeholder="Nêu rõ nguyên nhân và căn cứ điều chỉnh"></textarea>
                    </div>
                    <div class="pt-fin-form__full pt-fin-reason">
                        Giao dịch gốc vẫn được giữ nguyên. Chỉ bút toán điều chỉnh đã duyệt mới làm thay đổi tổng đã thu.
                    </div>
                    <div class="pt-fin-form__full pt-fin-form__actions">
                        <button class="pt-fin-btn" type="button" data-fin-close>Hủy</button>
                        <button class="pt-fin-btn pt-fin-btn--primary" type="submit">Gửi yêu cầu điều chỉnh</button>
                    </div>
                </form>
            </aside>
        </div>
    @endif

    <div class="pt-fin-drawer" id="financeTransactionDetailDrawer" hidden aria-hidden="true">
        <button class="pt-fin-drawer__backdrop" type="button" data-fin-close></button>
        <aside class="pt-fin-drawer__panel" role="dialog" aria-modal="true" aria-label="Chi tiết giao dịch thanh toán">
            <div class="pt-fin-drawer__head">
                <div>
                    <h3>Chi tiết giao dịch thanh toán</h3>
                    <p>Thông tin chỉ đọc phục vụ đối soát và kiểm tra chứng từ.</p>
                </div>
                <button class="pt-fin-drawer__close" type="button" data-fin-close><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="pt-fin-detail-grid">
                <div class="pt-fin-detail-item"><small>Mã giao dịch</small><strong data-fin-detail="code">—</strong></div>
                <div class="pt-fin-detail-item"><small>Ngày nhận tiền</small><strong data-fin-detail="date">—</strong></div>
                <div class="pt-fin-detail-item pt-fin-detail-item--full"><small>Đợt thanh toán</small><strong data-fin-detail="milestone">—</strong></div>
                <div class="pt-fin-detail-item"><small>Số tiền</small><strong data-fin-detail="amount">—</strong></div>
                <div class="pt-fin-detail-item"><small>Trạng thái</small><strong data-fin-detail="status">—</strong></div>
                <div class="pt-fin-detail-item"><small>Phương thức</small><span data-fin-detail="method">—</span></div>
                <div class="pt-fin-detail-item"><small>Tài khoản nhận</small><span data-fin-detail="account">—</span></div>
                <div class="pt-fin-detail-item"><small>Mã tham chiếu / UNC</small><span data-fin-detail="reference">—</span></div>
                <div class="pt-fin-detail-item"><small>Người thanh toán</small><span data-fin-detail="payer">—</span></div>
                <div class="pt-fin-detail-item"><small>Người ghi nhận</small><span data-fin-detail="recorder">—</span></div>
                <div class="pt-fin-detail-item"><small>Người xử lý</small><span data-fin-detail="confirmer">—</span></div>
                <div class="pt-fin-detail-item"><small>Thời gian xử lý</small><span data-fin-detail="confirmedAt">—</span></div>
                <div class="pt-fin-detail-item pt-fin-detail-item--full"><small>Ghi chú</small><span data-fin-detail="note">—</span></div>
                <div class="pt-fin-detail-item pt-fin-detail-item--full"><small>Lý do từ chối / hủy</small><span data-fin-detail="reason">—</span></div>
            </div>

            @if($canConfirmPayment)
                <form
                    method="POST"
                    action=""
                    class="pt-fin-form"
                    data-fin-edit-transaction-amount-form
                    style="margin-top:16px"
                >
                    @csrf
                    @method('PUT')

                    <div class="pt-fin-form__full">
                        <label class="pt-fin-label">
                            Sửa số tiền giao dịch
                        </label>

                        <input
                            class="pt-fin-input"
                            type="number"
                            name="amount"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            data-fin-edit-transaction-amount
                            required
                        >
                    </div>

                    <div class="pt-fin-form__full">
                        <label class="pt-fin-label">
                            Lý do chỉnh sửa
                        </label>

                        <textarea
                            class="pt-fin-textarea"
                            name="reason"
                            required
                            placeholder="Ví dụ: nhập nhầm số tiền, đối soát lại UNC..."
                        ></textarea>
                    </div>

                    <div class="pt-fin-form__full pt-fin-form__actions">
                        <button
                            class="pt-fin-btn pt-fin-btn--primary"
                            type="submit"
                        >
                            <i class="bi bi-pencil-square"></i>
                            Lưu số tiền mới
                        </button>
                    </div>
                </form>
            @endif

            <div class="pt-fin-form__actions" style="margin-top:16px">
                <a class="pt-fin-btn" href="#" data-fin-detail-proof hidden>
                    <i class="bi bi-paperclip"></i> Tải chứng từ
                </a>
                <button class="pt-fin-btn pt-fin-btn--primary" type="button" data-fin-close>Đóng</button>
            </div>
        </aside>
    </div>

    @if($canEditRevenue)
        <div class="pt-fin-drawer" id="financeRevenueDrawer" hidden aria-hidden="true">
            <button class="pt-fin-drawer__backdrop" type="button" data-fin-close></button>
            <aside class="pt-fin-drawer__panel" role="dialog" aria-modal="true" aria-label="Chỉnh sửa giá trị công trình">
                <div class="pt-fin-drawer__head">
                    <div><h3>Chỉnh sửa giá trị công trình</h3><p>Sales chỉ cập nhật doanh thu; không nhìn thấy hoặc chỉnh sửa chi phí nội bộ.</p></div>
                    <button class="pt-fin-drawer__close" type="button" data-fin-close><i class="bi bi-x-lg"></i></button>
                </div>
                <form method="POST" action="{{ route('project-test.finance.update', $project) }}" class="pt-fin-form">
                    @csrf
                    <input type="hidden" name="finance_scope" value="revenue">
                    <div><label class="pt-fin-label">Giá trị hợp đồng / công trình</label><input class="pt-fin-input" type="number" step="1" min="0" name="contract_amount" inputmode="numeric" value="{{ old('contract_amount', $project->contract_amount) }}" required></div>
                    <div><label class="pt-fin-label">Doanh thu phát sinh</label><input class="pt-fin-input" type="number" step="1000" min="0" name="extra_revenue" value="{{ old('extra_revenue', $project->extra_revenue) }}"></div>
                    <div class="pt-fin-form__full"><label class="pt-fin-label">Ghi chú doanh thu</label><textarea class="pt-fin-textarea" name="sales_revenue_note">{{ old('sales_revenue_note', $project->sales_revenue_note) }}</textarea></div>
                    <div class="pt-fin-form__full"><label class="pt-fin-label">Lý do thay đổi</label><textarea class="pt-fin-textarea" name="change_reason" required placeholder="Nêu lý do điều chỉnh giá trị công trình"></textarea></div>
                    <div class="pt-fin-form__full pt-fin-form__actions"><button class="pt-fin-btn" type="button" data-fin-close>Hủy</button><button class="pt-fin-btn pt-fin-btn--primary" type="submit">Lưu giá trị công trình</button></div>
                </form>
            </aside>
        </div>
    @endif

</section>

<script>
(function () {
    const root = document.querySelector('[data-finance-root]');
    if (!root) return;

    root.querySelectorAll('[data-fin-go-expenses]').forEach((button) => {
        button.addEventListener('click', () => {
            const expenseTab = document.querySelector('[data-pt-tab="expenses"]');
            if (expenseTab) expenseTab.click();
        });
    });

    const openDrawer = (id) => {
        const drawer = document.getElementById(id);
        if (!drawer) return;
        drawer.hidden = false;
        drawer.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
    };

    const closeDrawer = (drawer) => {
        if (!drawer) return;
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
    };

    document.querySelectorAll('[data-fin-open]').forEach((button) => {
        button.addEventListener('click', () => {
            let drawerId = button.dataset.finOpen;

            // Bảo vệ UX: chưa có kế hoạch thì không mở form thu tiền trống.
            if (
                drawerId === 'financePaymentDrawer'
                && root.dataset.finHasMilestones !== '1'
            ) {
                drawerId = 'financeMilestoneDrawer';
            }

            openDrawer(drawerId);
        });
    });

    document.querySelectorAll('[data-fin-close]').forEach((button) => {
        button.addEventListener('click', () => closeDrawer(button.closest('.pt-fin-drawer')));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.pt-fin-drawer:not([hidden])').forEach(closeDrawer);
    });

    const paymentMilestoneSelect = document.querySelector('[data-fin-payment-milestone]');
    const paymentAmountInput = document.querySelector('[data-fin-payment-amount]');
    const syncPaymentRemaining = () => {
        if (!paymentMilestoneSelect || !paymentAmountInput) return;
        const option = paymentMilestoneSelect.options[paymentMilestoneSelect.selectedIndex];
        const remaining = Number(option?.dataset?.remaining || 0);
        if (remaining > 0) paymentAmountInput.value = String(Math.round(remaining));
    };
    paymentMilestoneSelect?.addEventListener('change', syncPaymentRemaining);
    document.querySelectorAll('[data-fin-record-payment]').forEach((button) => {
        button.addEventListener('click', () => {
            if (paymentMilestoneSelect) paymentMilestoneSelect.value = button.dataset.finRecordPayment || '';
            syncPaymentRemaining();
            openDrawer('financePaymentDrawer');
        });
    });

    // Sau khi tạo đợt đầu tiên thành công, tự mở Ghi nhận thu tiền
    // và chọn sẵn đợt vừa tạo.
    if (root.dataset.finAutoOpenPayment === '1') {
        const autoMilestoneId = root.dataset.finAutoMilestoneId || '';
        if (paymentMilestoneSelect && autoMilestoneId) {
            paymentMilestoneSelect.value = autoMilestoneId;
            syncPaymentRemaining();
        }
        window.setTimeout(() => openDrawer('financePaymentDrawer'), 120);
    }

    const editForm = document.querySelector('[data-fin-edit-milestone-form]');
    document.querySelectorAll('[data-fin-edit-milestone]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!editForm) return;
            editForm.action = button.dataset.action || '';
            editForm.querySelector('[name="title"]').value = button.dataset.title || '';
            editForm.querySelector('[name="amount"]').value = button.dataset.amount || '';
            editForm.querySelector('[name="percentage"]').value = button.dataset.percentage || '';
            editForm.querySelector('[name="due_date"]').value = button.dataset.dueDate || '';
            editForm.querySelector('[name="condition_text"]').value = button.dataset.condition || '';
            editForm.querySelector('[name="note"]').value = button.dataset.note || '';
            openDrawer('financeMilestoneEditDrawer');
        });
    });

    document.querySelectorAll('[data-fin-milestone-form]').forEach((form) => {
        const amount = form.querySelector('[data-fin-amount]');
        const percentage = form.querySelector('[data-fin-percentage]');
        const warning = form.querySelector('[data-fin-plan-warning]');
        const total = Number(form.dataset.projectTotal || 0);
        const existing = Number(form.dataset.existingPlan || 0);
        let syncing = false;

        const updateWarning = () => {
            if (!warning) return;
            const planned = existing + Number(amount?.value || 0);
            if (total > 0 && planned > total) {
                warning.hidden = false;
                warning.textContent = 'Tổng kế hoạch sẽ vượt giá trị công trình ' + new Intl.NumberFormat('vi-VN').format(total) + ' đ.';
            } else {
                warning.hidden = true;
                warning.textContent = '';
            }
        };

        percentage?.addEventListener('input', () => {
            if (syncing || total <= 0) return;
            syncing = true;
            amount.value = Math.round(total * Number(percentage.value || 0) / 100);
            syncing = false;
            updateWarning();
        });

        amount?.addEventListener('input', () => {
            if (!syncing && total > 0 && amount.value) {
                syncing = true;
                percentage.value = (Number(amount.value) / total * 100).toFixed(2);
                syncing = false;
            }
            updateWarning();
        });
    });

    const adjustmentSelect = document.querySelector('[data-fin-adjustment-transaction]');
    const adjustmentCorrect = document.querySelector('[data-fin-adjustment-correct]');
    const adjustmentDelta = document.querySelector('[data-fin-adjustment-delta]');

    const refreshAdjustmentDelta = () => {
        if (!adjustmentSelect || !adjustmentCorrect || !adjustmentDelta) return;
        const option = adjustmentSelect.options[adjustmentSelect.selectedIndex];
        const current = Number(option?.dataset.currentAmount || 0);
        const correct = Number(adjustmentCorrect.value || 0);
        const delta = correct - current;
        adjustmentDelta.value = (delta > 0 ? '+' : '') + new Intl.NumberFormat('vi-VN').format(delta) + ' đ';
    };

    adjustmentSelect?.addEventListener('change', () => {
        const option = adjustmentSelect.options[adjustmentSelect.selectedIndex];
        adjustmentCorrect.value = option?.dataset.currentAmount || '';
        refreshAdjustmentDelta();
    });
    adjustmentCorrect?.addEventListener('input', refreshAdjustmentDelta);

    document.querySelectorAll('[data-fin-view-transaction]').forEach((button) => {
        button.addEventListener('click', () => {
            const drawer = document.getElementById('financeTransactionDetailDrawer');
            if (!drawer) return;

            const values = {
                code: button.dataset.code || '—',
                date: button.dataset.date || '—',
                milestone: button.dataset.milestone || '—',
                amount: button.dataset.amount || '—',
                method: button.dataset.method || '—',
                account: button.dataset.account || '—',
                reference: button.dataset.reference || '—',
                payer: button.dataset.payer || '—',
                status: button.dataset.status || '—',
                recorder: button.dataset.recorder || '—',
                confirmer: button.dataset.confirmer || '—',
                confirmedAt: button.dataset.confirmedAt || '—',
                note: button.dataset.note || '—',
                reason: button.dataset.reason || '—',
            };

            Object.entries(values).forEach(([key, value]) => {
                const node = drawer.querySelector('[data-fin-detail="' + key + '"]');
                if (node) node.textContent = value;
            });

            // EGO_PAYMENT_DIRECT_EDIT_V1
            const editAmountForm =
                drawer.querySelector(
                    '[data-fin-edit-transaction-amount-form]'
                );

            if (editAmountForm) {

                editAmountForm.action =
                    button.dataset.finUpdateAmountUrl
                    || '';

                const amountInput =
                    editAmountForm.querySelector(
                        '[data-fin-edit-transaction-amount]'
                    );

                if (amountInput) {
                    amountInput.value =
                        button.dataset.rawAmount
                        || '';
                }

                const reasonInput =
                    editAmountForm.querySelector(
                        '[name="reason"]'
                    );

                if (reasonInput) {
                    reasonInput.value = '';
                }
            }

            const proof = drawer.querySelector('[data-fin-detail-proof]');
            const proofUrl = button.dataset.proof || '';
            if (proof) {
                proof.hidden = !proofUrl;
                proof.href = proofUrl || '#';
            }

            openDrawer('financeTransactionDetailDrawer');
        });
    });

    const filterButtons = Array.from(document.querySelectorAll('[data-fin-status-filters] [data-status]'));
    const rows = Array.from(document.querySelectorAll('[data-fin-transaction-record]'));
    let activeStatus = 'all';
    let activeMilestone = 'all';

    const applyFilters = () => {
        rows.forEach((row) => {
            const statusMatches = activeStatus === 'all' || row.dataset.status === activeStatus;
            const milestoneMatches = activeMilestone === 'all' || row.dataset.milestone === activeMilestone;
            row.hidden = !(statusMatches && milestoneMatches);
        });
    };

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            filterButtons.forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            activeStatus = button.dataset.status || 'all';
            activeMilestone = 'all';
            applyFilters();
        });
    });

    document.querySelectorAll('[data-fin-filter-milestone]').forEach((button) => {
        button.addEventListener('click', () => {
            activeMilestone = button.dataset.finFilterMilestone || 'all';
            activeStatus = 'all';
            filterButtons.forEach((item) => item.classList.toggle('is-active', item.dataset.status === 'all'));
            applyFilters();
            const pendingVisible = Array.from(document.querySelectorAll('#financePendingSection [data-fin-transaction-record]'))
                .some((item) => !item.hidden);
            const target = pendingVisible
                ? document.getElementById('financePendingSection')
                : document.getElementById('financeTransactionsSection');
            target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
</script>
