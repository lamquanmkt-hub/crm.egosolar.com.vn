<!-- resources/views/media/modal.blade.php -->
<div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Media Library</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#media-upload">Tải lên</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#media-library">Thư viện</button></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="media-upload">
                        <div class="mb-3">
                            <input type="file" id="media_file_input" class="form-control">
                        </div>
                        <div id="upload_result"></div>
                    </div>

                    <div class="tab-pane fade" id="media-library">
                        <div class="row" id="media_list" style="max-height:60vh; overflow:auto"></div>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" id="media_select_btn" class="btn btn-primary">Chọn</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function(){
        let currentTarget = null; // 'main_image' or 'gallery'
        let selectedIds = [];

        window.openMediaModal = function(target, allowMultiple = false) {
            currentTarget = {name: target, multiple: !!allowMultiple};
            selectedIds = [];
            loadMediaLibrary();
            const m = new bootstrap.Modal(document.getElementById('mediaModal'));
            m.show();
        };

        function loadMediaLibrary() {
            document.getElementById('media_list').innerHTML = 'Đang tải...';
            fetch('{{ route("media.list") }}')
                .then(r=>r.json())
                .then(data=>{
                    let html = '';
                    data.forEach(m=>{
                        html += `<div class="col-2 p-2">
             <div class="border p-1 text-center selectable" data-id="${m.id}" onclick="toggleMediaItem(this)">
               <img src="${m.url}" class="img-fluid" style="max-height:100px; object-fit:cover;">
               <div class="small text-truncate">${m.file_name}</div>
             </div>
          </div>`;
                    });
                    document.getElementById('media_list').innerHTML = html;
                });
        }

        window.toggleMediaItem = function(el) {
            const id = parseInt(el.getAttribute('data-id'));
            if (!currentTarget) return;
            if (!currentTarget.multiple) {
                // unselect others
                document.querySelectorAll('#media_list .selectable.selected').forEach(e=>e.classList.remove('selected','border-primary'));
                selectedIds = [id];
                el.classList.add('selected','border-primary');
            } else {
                if (selectedIds.includes(id)) {
                    selectedIds = selectedIds.filter(x=>x!==id);
                    el.classList.remove('selected','border-primary');
                } else {
                    selectedIds.push(id);
                    el.classList.add('selected','border-primary');
                }
            }
        };

        // Upload handler
        document.getElementById('media_file_input').addEventListener('change', function(e){
            const f = this.files[0];
            if (!f) return;
            const form = new FormData();
            form.append('file', f);
            fetch('{{ route("media.upload") }}', {
                method:'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: form
            })
                .then(r=>r.json())
                .then(json=>{
                    // reload library so uploaded appears
                    loadMediaLibrary();
                    document.getElementById('upload_result').innerHTML = `<div class="alert alert-success small">Upload thành công</div>`;
                })
                .catch(err=>{
                    document.getElementById('upload_result').innerHTML = `<div class="alert alert-danger small">Upload lỗi</div>`;
                });
        });

        // Select button
        document.getElementById('media_select_btn').addEventListener('click', function(){
            if (!currentTarget) return;
            const name = currentTarget.name;
            const multiple = currentTarget.multiple;

            if (name === 'main_image') {
                const id = selectedIds[0] ?? null;
                document.getElementById('main_image_id').value = id ?? '';
                // render preview
                if (id) {
                    const el = document.querySelector(`#media_list .selectable[data-id="${id}"] img`);
                    if (el) {
                        document.getElementById('preview_main_image').innerHTML = `<img src="${el.src}" width="150" class="border">`;
                    }
                } else {
                    document.getElementById('preview_main_image').innerHTML = '';
                }
            } else if (name === 'gallery') {
                document.getElementById('gallery_ids').value = selectedIds.join(',');
                // render previews
                let html='';
                selectedIds.forEach(id=>{
                    const el = document.querySelector(`#media_list .selectable[data-id="${id}"] img`);
                    if (el) html += `<img src="${el.src}" width="80" class="m-1 border rounded">`;
                });
                document.getElementById('preview_gallery').innerHTML = html;
            }

            bootstrap.Modal.getInstance(document.getElementById('mediaModal')).hide();
        });

    })();
</script>

<style>
    #media_list .selectable { cursor:pointer; border-radius:4px; }
    #media_list .selectable.selected { box-shadow:0 0 0 3px rgba(13,110,253,.2); }
</style>
