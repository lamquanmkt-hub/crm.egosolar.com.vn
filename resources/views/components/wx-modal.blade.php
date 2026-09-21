@props(['id', 'title', 'action', 'size' => 'lg', 'submit' => 'Lưu', 'danger' => false, 'files' => false, 'chain' => null, 'chainLabel' => null, 'submitId' => null])
<div class="modal fade wx-modal-v2" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-{{ $size }} modal-dialog-scrollable modal-dialog-centered">
        <form class="modal-content wx-ajax" method="POST" action="{{ $action }}" @if($files) enctype="multipart/form-data" @endif>
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="wx-form-errors" role="alert" hidden></div>
                {{ $slot }}
            </div>
            <div class="modal-footer">
                <button type="button" class="wx-btn ghost" data-bs-dismiss="modal">Đóng</button>
                @if($chain)
                    <button type="submit" class="wx-btn secondary">{{ $submit }}</button>
                    <button type="button" class="wx-btn primary" data-wx-chain-btn="{{ $chain }}">{{ $chainLabel }}</button>
                @else
                    <button type="submit" @if($submitId) id="{{ $submitId }}" @endif class="wx-btn {{ $danger ? 'danger' : 'primary' }}">{{ $submit }}</button>
                @endif
            </div>
        </form>
    </div>
</div>
