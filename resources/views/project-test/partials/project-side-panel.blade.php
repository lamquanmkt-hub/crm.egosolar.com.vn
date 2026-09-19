{{-- EGO_PROJECT_SIDE_PANEL_V4: dùng chung cho layout chuẩn và layout vật tư --}}
<article class="pt-card pt-section">
    <div class="pt-section__head">
        <div>
            <h2>Việc tiếp theo</h2>
            <p>Ai đang giữ việc và cần làm gì.</p>
        </div>
    </div>
    <span class="pt-status">{{ $statusInfo['label'] }}</span>
    <div class="pt-summary" style="margin-top:12px">
        <div><small>Chủ việc</small><strong>{{ str_replace('_',' ',mb_strtoupper($project->current_owner_role)) }}</strong></div>
        <div><small>Tiến độ</small><strong>{{ $project->progress }}%</strong></div>
        <div><small>Khảo sát</small><strong>{{ optional($project->proposed_survey_at)->format('d/m H:i') ?: '—' }}</strong></div>
        <div><small>Thi công</small><strong>{{ optional($project->proposed_installation_at)->format('d/m H:i') ?: '—' }}</strong></div>
    </div>
</article>

<article class="pt-card pt-section">
    <div class="pt-section__head">
        <div>
            <h2>Liên kết phòng ban</h2>
            <p>Phòng Kỹ thuật chủ trì; các phòng khác chỉ phối hợp theo đúng điểm giao.</p>
        </div>
    </div>
    <div class="pt-timeline">
        <div class="pt-history">
            <span class="pt-history__dot"><i class="bi bi-tools"></i></span>
            <div><strong>Phòng Kỹ thuật · Chủ trì</strong><p>Tiếp nhận, phân loại, khảo sát, phương án, điều phối thi công, nghiệm thu và bảo hành.</p></div>
        </div>
        <div class="pt-history">
            <span class="pt-history__dot"><i class="bi bi-person-lines-fill"></i></span>
            <div><strong>Sales / CSKH · Phối hợp khi cần</strong><p>Chuyển đầu vào và hỗ trợ xác nhận khách hàng; không phải bước bắt buộc của mọi hồ sơ.</p></div>
        </div>
        <div class="pt-history">
            <span class="pt-history__dot"><i class="bi bi-shield-check"></i></span>
            <div><strong>Admin · Phê duyệt</strong><p>Duyệt vật tư hoặc phát sinh vượt thẩm quyền trước khi chuyển Kho.</p></div>
        </div>
        <div class="pt-history">
            <span class="pt-history__dot"><i class="bi bi-box-arrow-up-right"></i></span>
            <div><strong>Kho · Cấp vật tư</strong><p>Ghép sản phẩm/serial thực tế, giữ hàng, xuất và bàn giao cho Kỹ thuật.</p></div>
        </div>
    </div>
</article>
