@php
    $onboardRows = $offers->filter(fn($o) => in_array($o->status ?? '', ['accepted','onboarded'],true) || !empty($o->onboarding_status));
    $archivedRows = $candidates->filter(fn($c) => in_array($c->status ?? '', ['archived','rejected','not_fit','interview_failed'],true));
    $stepMetrics = match($active) {
        'screening'=>[['Ứng viên',$candidates->count()],['Chưa liên hệ',$candidates->filter(fn($c)=>empty($c->contact_channel) && empty($c->contacted_at))->count()],['Phù hợp',$candidates->where('suitability','good')->count()]],
        'interviews'=>[['Lịch phỏng vấn',$interviews->count()],['Chờ phỏng vấn',$interviews->whereIn('status',['scheduled','rescheduled'])->count()],['Đã phỏng vấn',$interviews->where('status','done')->count()]],
        'evaluations'=>[['Hồ sơ đánh giá',$interviews->count()],['Chưa chốt',$interviews->filter(fn($i)=>empty($i->evaluation_result))->count()],['Đạt',$interviews->where('evaluation_result','passed')->count()]],
        'offers'=>[['Thư mời',$offers->count()],['Đã gửi',$offers->where('status','sent')->count()],['Đồng ý / tiếp nhận',$offers->whereIn('status',['accepted','onboarded'])->count()]],
        'onboarding'=>[['Nhân sự tiếp nhận',$onboardRows->count()],['Bổ sung hồ sơ',$onboardRows->where('onboarding_status','collecting_docs')->count()],['Hoàn tất',$onboardRows->where('onboarding_status','completed')->count()]],
        'archives'=>[['Hồ sơ lưu',$archivedRows->count()],['Chưa phù hợp / không đạt',$archivedRows->whereIn('status',['not_fit','interview_failed'])->count()],['Từ chối',$archivedRows->where('status','rejected')->count()]],
    };
@endphp
<div class="rec-stats rec-request-stats">@foreach($stepMetrics as [$label,$value])<div class="rec-stat"><div class="rec-stat-label">{{ $label }}</div><div class="rec-stat-value">{{ $value }}</div></div>@endforeach</div>
