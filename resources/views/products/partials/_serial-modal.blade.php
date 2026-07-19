<div class="modal fade" id="serialModal" tabindex="-1" aria-labelledby="serialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="serialModalLabel">
                    <i class="bi bi-upc-scan"></i>
                    Nhập Serial/IMEI - <span id="serial-modal-warehouse-name"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-start">
                    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                    <div>
                        <strong>Hướng dẫn:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Nhập mỗi serial trên một dòng</li>
                            <li>Serial sẽ tự động chuyển thành <strong>IN HOA</strong></li>
                            <li>Chỉ chấp nhận: A-Z, 0-9, dấu gạch (- _ /)</li>
                            <li>Độ dài: 6 - 50 ký tự</li>
                        </ul>
                    </div>
                </div>

                <div id="serial-errors" style="display:none;"></div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0">
                        <strong>Đã nhập: <span id="serial-entered-count" class="text-primary">0</span> serial</strong>
                    </label>
                </div>

                <textarea id="serial-textarea"
                          class="form-control font-monospace"
                          rows="8"
                          placeholder="56000NAW258L1292&#10;56000NAW258L1293&#10;..."></textarea>

                <div id="serial-list-preview" class="mt-3"></div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-serial-paste">
                        <i class="bi bi-clipboard"></i> Paste từ clipboard
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-serial-clear">
                        <i class="bi bi-trash"></i> Xóa tất cả
                    </button>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x"></i> Hủy
                </button>
                <button type="button" class="btn btn-primary" id="btn-serial-save">
                    <i class="bi bi-check-lg"></i> Lưu Serial
                </button>
            </div>
        </div>
    </div>
</div>
