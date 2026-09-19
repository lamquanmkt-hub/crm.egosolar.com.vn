(function(){
  'use strict';
  const qs=(s,r=document)=>r.querySelector(s), qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));
  document.addEventListener('DOMContentLoaded',()=>{
    qsa('[data-om10-toggle]').forEach(btn=>btn.addEventListener('click',()=>{
      const el=document.getElementById(btn.dataset.om10Toggle); if(!el)return;
      const open=el.hasAttribute('hidden'); if(open)el.removeAttribute('hidden'); else el.setAttribute('hidden','hidden');
      btn.setAttribute('aria-expanded',open?'true':'false');
    }));

    const planModalEl=qs('#om10PlanModal');
    const planModal=planModalEl&&window.bootstrap?bootstrap.Modal.getOrCreateInstance(planModalEl):null;
    const incidentModalEl=qs('#om10IncidentModal');
    const incidentModal=incidentModalEl&&window.bootstrap?bootstrap.Modal.getOrCreateInstance(incidentModalEl):null;
    qsa('[data-om10-open-plan]').forEach(b=>b.addEventListener('click',()=>{resetPlan(); planModal&&planModal.show();}));
    qsa('[data-om10-open-incident]').forEach(b=>b.addEventListener('click',()=>incidentModal&&incidentModal.show()));
    qsa('[data-om10-plan-profile]').forEach(b=>b.addEventListener('click',()=>{
      resetPlan();
      setVal('#om10ProfileId',b.dataset.profileId); setVal('#om10SiteSelect',b.dataset.siteId); setVal('#om10SiteName',b.dataset.siteName);
      setVal('#om10Customer',b.dataset.customer); setVal('#om10Address',b.dataset.address); setVal('#om10Kwp',b.dataset.kwp);
      const title=qs('#om10PlanTitle'); if(title) title.textContent='Lập kế hoạch · '+(b.dataset.siteName||'Công trình');
      planModal&&planModal.show();
    }));
    const siteSelect=qs('#om10SiteSelect'); if(siteSelect)siteSelect.addEventListener('change',()=>{
      const o=siteSelect.selectedOptions[0]; if(!o)return;
      setVal('#om10ProfileId',o.dataset.profileId||'');
      setVal('#om10SiteName',o.dataset.name||''); setVal('#om10Customer',o.dataset.customer||''); setVal('#om10Address',o.dataset.address||''); setVal('#om10Kwp',o.dataset.kwp||'');
    });
    const type=qs('#om10PlanType'); if(type)type.addEventListener('change',()=>{
      const incident=type.value==='incident'; setVal('#om10Rounds',incident?'1':'5'); const r=qs('#om10Rounds'); const i=qs('#om10Interval'); if(r)r.disabled=incident;if(i)i.disabled=incident;
    });

    const picker=qs('[data-om10-team-picker]'); const search=qs('[data-om10-team-search]');
    const count=qs('[data-om10-team-count]');
    function syncTeam(){if(!picker)return; const checks=qsa('input[type=checkbox]:checked',picker); if(count)count.textContent=checks.length; qsa('.om10-team-option',picker).forEach(row=>{const c=qs('input[type=checkbox]',row),r=qs('input[type=radio]',row);if(r)r.disabled=!c.checked;if(r&&r.checked&&!c.checked)r.checked=false;}); const checkedLeader=qs('input[type=radio]:checked',picker); if(!checkedLeader&&checks.length){const row=checks[0].closest('.om10-team-option');const r=qs('input[type=radio]',row);if(r)r.checked=true;}}
    if(picker){qsa('input[type=checkbox]',picker).forEach(c=>c.addEventListener('change',syncTeam));syncTeam();}
    if(search&&picker)search.addEventListener('input',()=>{const term=search.value.trim().toLowerCase();qsa('.om10-team-option',picker).forEach(row=>row.hidden=term&&!row.dataset.name.includes(term));});

    qsa('.om10-checklist input[type=checkbox]').forEach(c=>c.addEventListener('change',()=>{const l=c.closest('label');if(l)l.classList.toggle('checked',c.checked);}));
    qsa('[data-om12-check]').forEach(c=>c.addEventListener('change',()=>{
      if(c.checked&&c.dataset.evidenceReady!=='1'){
        c.checked=false;
        window.alert('Mục này chưa đủ file minh chứng. Vui lòng thêm file trước khi đánh dấu hoàn thành.');
        return;
      }
      const row=c.closest('.om12-check-item,.om13-check-task');if(row)row.classList.toggle('is-done',c.checked);
      const text=c.closest('.om12-complete-toggle')?.querySelector('b');if(text)text.textContent=c.checked?'Đã hoàn thành':'Đánh dấu xong';
    }));
    // V14.5 — chọn file là tải ngay, không cần nút xác nhận thứ hai.
    qsa('.om12-item-upload input[type=file],.om13-item-upload input[type=file]').forEach(input=>input.addEventListener('change',()=>{
      const label=input.closest('label')?.querySelector('span');
      const count=input.files?.length||0;
      const autoForm=input.closest('[data-om15-auto-upload]');
      if(label)label.textContent=count?(autoForm?`Đang thêm ${count} file...`:`${count} file đã chọn`):(autoForm?'Thêm ảnh hoặc file':'Chọn file');
      if(!count||!autoForm||autoForm.dataset.uploading==='1')return;
      autoForm.dataset.uploading='1';autoForm.classList.add('is-uploading');
      const picker=input.closest('label');if(picker)picker.setAttribute('aria-busy','true');
      window.setTimeout(()=>{if(typeof autoForm.requestSubmit==='function')autoForm.requestSubmit();else autoForm.submit();},0);
    }));
    qsa('[data-om12-evidence-switch]').forEach(toggle=>{
      const syncEvidence=()=>{
        const scope=toggle.closest('.om12-template-item')||toggle.closest('form')||toggle.closest('.om12-add-template')||toggle.closest('.om13-property-panel');
        const minimum=scope&&qs('[data-om12-min-evidence]',scope);if(minimum)minimum.disabled=!toggle.checked;
      };
      toggle.addEventListener('change',syncEvidence);syncEvidence();
    });
    qsa('[data-om13-check-open]').forEach(button=>button.addEventListener('click',()=>{
      const task=button.closest('[data-om13-check-task]');if(!task)return;
      const detail=qs('.om13-check-detail',task);const open=detail&&detail.hasAttribute('hidden');
      qsa('[data-om13-check-task]').forEach(other=>{const body=qs('.om13-check-detail',other),head=qs('[data-om13-check-open]',other);other.classList.remove('active');if(body)body.setAttribute('hidden','hidden');if(head)head.setAttribute('aria-expanded','false');});
      if(open&&detail){task.classList.add('active');detail.removeAttribute('hidden');button.setAttribute('aria-expanded','true');}
    }));

    // V14.6 — xem nhanh từng file trong popup, không rời khỏi checklist.
    const previewModalEl=qs('#om16PreviewModal');
    const previewModal=previewModalEl&&window.bootstrap?bootstrap.Modal.getOrCreateInstance(previewModalEl):null;
    const previewTitle=previewModalEl&&qs('[data-om16-preview-title]',previewModalEl);
    const previewNote=previewModalEl&&qs('[data-om16-preview-note]',previewModalEl);
    const previewLoading=previewModalEl&&qs('[data-om16-preview-loading]',previewModalEl);
    const previewImage=previewModalEl&&qs('[data-om16-preview-image]',previewModalEl);
    const previewFrame=previewModalEl&&qs('[data-om16-preview-frame]',previewModalEl);
    const previewVideo=previewModalEl&&qs('[data-om16-preview-video]',previewModalEl);
    const previewFallback=previewModalEl&&qs('[data-om16-preview-fallback]',previewModalEl);
    const previewNewTab=previewModalEl&&qs('[data-om16-preview-newtab]',previewModalEl);
    const previewDownload=previewModalEl&&qs('[data-om16-preview-download]',previewModalEl);
    const previewDelete=previewModalEl&&qs('[data-om17-preview-delete]',previewModalEl);
    function resetFilePreview(){
      if(previewLoading)previewLoading.hidden=false;
      if(previewImage){previewImage.hidden=true;previewImage.removeAttribute('src');}
      if(previewFrame){previewFrame.hidden=true;previewFrame.removeAttribute('src');}
      if(previewVideo){previewVideo.pause();previewVideo.hidden=true;previewVideo.removeAttribute('src');previewVideo.load();}
      if(previewFallback)previewFallback.hidden=true;
      if(previewDelete){previewDelete.hidden=true;previewDelete.action='#';}
    }
    function showPreviewFallback(){
      if(previewLoading)previewLoading.hidden=true;
      if(previewImage)previewImage.hidden=true;if(previewFrame)previewFrame.hidden=true;if(previewVideo)previewVideo.hidden=true;
      if(previewFallback)previewFallback.hidden=false;
    }
    qsa('[data-om16-preview]').forEach(button=>button.addEventListener('click',()=>{
      if(!previewModalEl||!previewModal)return;
      const kind=button.dataset.kind||'file';const url=button.dataset.previewUrl||'';const download=button.dataset.downloadUrl||url;const deleteUrl=button.dataset.deleteUrl||'';const name=button.dataset.name||'File minh chứng';
      resetFilePreview();if(previewTitle)previewTitle.textContent=name;
      if(previewNewTab)previewNewTab.href=kind==='file'?download:url;if(previewDownload)previewDownload.href=download;
      if(previewDelete&&deleteUrl){previewDelete.action=deleteUrl;previewDelete.hidden=false;}
      if(previewNote)previewNote.textContent=kind==='spreadsheet'?'Xem nhanh trang tính đầu tiên, tối đa 200 dòng và 30 cột.':(kind==='file'?'Định dạng này cần mở bằng ứng dụng trên thiết bị.':'Có thể phóng to hoặc mở sang tab mới.');
      previewModal.show();
      if(kind==='image'&&previewImage){previewImage.hidden=false;previewImage.onload=()=>{if(previewLoading)previewLoading.hidden=true;};previewImage.onerror=showPreviewFallback;previewImage.src=url;return;}
      if((kind==='pdf'||kind==='spreadsheet')&&previewFrame){previewFrame.hidden=false;previewFrame.onload=()=>{if(previewLoading)previewLoading.hidden=true;};previewFrame.src=url;return;}
      if(kind==='video'&&previewVideo){previewVideo.hidden=false;previewVideo.onloadeddata=()=>{if(previewLoading)previewLoading.hidden=true;};previewVideo.onerror=showPreviewFallback;previewVideo.src=url;previewVideo.load();return;}
      showPreviewFallback();
    }));
    if(previewModalEl)previewModalEl.addEventListener('hidden.bs.modal',resetFilePreview);

    const editor=qs('[data-om13-template-editor]');
    const deleteForm=qs('[data-om13-delete-form]');
    const editorTitle=qs('[data-om13-editor-title]');
    const editorMethod=editor&&qs('[data-om13-method]',editor);
    const editorLabel=editor&&qs('[data-om13-label]',editor);
    const editorDescription=editor&&qs('[data-om13-description]',editor);
    const editorSort=editor&&qs('[data-om13-sort]',editor);
    const editorRequired=editor&&qs('[data-om13-required]',editor);
    const editorEvidence=editor&&qs('[data-om13-evidence]',editor);
    const editorMinimum=editor&&qs('[data-om13-min]',editor);
    const saveLabel=editor&&qs('[data-om13-save-label]',editor);
    function selectTemplate(block){
      if(!editor||!block)return;
      qsa('[data-om13-template]').forEach(item=>item.classList.toggle('selected',item===block));
      editor.action=block.dataset.updateUrl||editor.action;if(editorMethod)editorMethod.disabled=false;
      if(editorLabel)editorLabel.value=block.dataset.label||'';if(editorDescription)editorDescription.value=block.dataset.description||'';if(editorSort)editorSort.value=block.dataset.sort||'0';
      if(editorRequired)editorRequired.checked=block.dataset.required==='1';if(editorEvidence)editorEvidence.checked=block.dataset.evidence==='1';if(editorMinimum){editorMinimum.value=block.dataset.min||'1';editorMinimum.disabled=!editorEvidence.checked;}
      if(editorTitle)editorTitle.textContent='Chỉnh sửa mục';if(saveLabel)saveLabel.innerHTML='<i class="bi bi-check2-circle"></i> Lưu thay đổi';
      if(deleteForm){deleteForm.hidden=false;deleteForm.action=block.dataset.deleteUrl||'#';}
    }
    qsa('[data-om13-select-template]').forEach(button=>button.addEventListener('click',()=>selectTemplate(button.closest('[data-om13-template]'))));
    qsa('[data-om13-new-template]').forEach(button=>button.addEventListener('click',()=>{
      if(!editor)return;qsa('[data-om13-template]').forEach(item=>item.classList.remove('selected'));editor.action=editor.dataset.storeUrl||editor.action;if(editorMethod)editorMethod.disabled=true;
      if(editorLabel){editorLabel.value='';editorLabel.focus();}if(editorDescription)editorDescription.value='';if(editorSort)editorSort.value=editor.dataset.nextSort||'10';if(editorRequired)editorRequired.checked=true;if(editorEvidence)editorEvidence.checked=true;if(editorMinimum){editorMinimum.value='1';editorMinimum.disabled=false;}
      if(editorTitle)editorTitle.textContent='Thêm mục mới';if(saveLabel)saveLabel.innerHTML='<i class="bi bi-plus-circle"></i> Thêm vào checklist';if(deleteForm)deleteForm.hidden=true;
      if(window.innerWidth<=1050)editor.closest('.om13-property-panel')?.scrollIntoView({behavior:'smooth',block:'start'});
    }));
    qsa('[data-om14-delete-template]').forEach(button=>button.addEventListener('click',event=>{
      event.preventDefault();event.stopPropagation();
      const block=button.closest('[data-om13-template]');if(!block||!deleteForm)return;
      selectTemplate(block);deleteForm.action=block.dataset.deleteUrl||'#';
      if(typeof deleteForm.requestSubmit==='function')deleteForm.requestSubmit();else if(window.confirm(deleteForm.dataset.om11Confirm||'Xóa mục này?'))deleteForm.submit();
    }));

    const builderList=qs('[data-om13-builder-list]');
    const orderStatus=qs('[data-om13-order-status]');
    let draggedBlock=null;
    if(builderList){
      qsa('[data-om13-template]',builderList).forEach(block=>{
        block.addEventListener('dragstart',event=>{draggedBlock=block;block.classList.add('dragging');if(event.dataTransfer)event.dataTransfer.effectAllowed='move';});
        block.addEventListener('dragover',event=>{event.preventDefault();if(block!==draggedBlock)block.classList.add('drag-over');});
        block.addEventListener('dragleave',()=>block.classList.remove('drag-over'));
        block.addEventListener('drop',event=>{event.preventDefault();block.classList.remove('drag-over');if(!draggedBlock||draggedBlock===block)return;const box=block.getBoundingClientRect();const after=event.clientY>box.top+box.height/2;builderList.insertBefore(draggedBlock,after?block.nextSibling:block);syncBuilderOrder();});
        block.addEventListener('dragend',()=>{block.classList.remove('dragging');qsa('.drag-over',builderList).forEach(item=>item.classList.remove('drag-over'));draggedBlock=null;});
      });
    }
    function syncBuilderOrder(){
      qsa('[data-om13-template]',builderList).forEach((block,index)=>{const number=qs('.om13-flow-index',block);if(number)number.textContent=String(index+1).padStart(2,'0');block.dataset.sort=String((index+1)*10);});
      if(orderStatus){orderStatus.textContent='Thứ tự đã thay đổi — hãy bấm Lưu thứ tự';orderStatus.classList.add('danger');}
    }

    const orderForm=qs('[data-om13-order-form][data-om13-order-mode="sequential"]');
    if(orderForm)orderForm.addEventListener('submit',async event=>{
      event.preventDefault();
      const blocks=qsa('[data-om13-template]',builderList);
      const submit=qs('button[type=submit]',orderForm);
      const token=qs('input[name="_token"]',orderForm)?.value||'';
      const maintenanceType=qs('input[name="maintenance_type"]',orderForm)?.value||'periodic';
      if(!blocks.length)return;
      if(submit)submit.disabled=true;
      if(orderStatus){orderStatus.textContent='Đang lưu thứ tự...';orderStatus.classList.remove('danger');}
      try{
        for(const block of blocks){
          const payload=new FormData();
          payload.append('_token',token);payload.append('_method','PUT');payload.append('maintenance_type',maintenanceType);
          payload.append('label',block.dataset.label||'Mục kiểm tra');payload.append('description',block.dataset.description||'');payload.append('sort_order',block.dataset.sort||'0');
          if(block.dataset.required==='1')payload.append('is_required','1');
          if(block.dataset.evidence==='1')payload.append('requires_evidence','1');
          payload.append('min_evidence',block.dataset.evidence==='1'?(block.dataset.min||'1'):'0');
          const response=await fetch(block.dataset.updateUrl,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:payload,credentials:'same-origin'});
          if(!response.ok)throw new Error('Không thể cập nhật bước '+(block.dataset.label||block.dataset.id));
        }
        if(orderStatus)orderStatus.textContent='Đã lưu thứ tự thành công';
        window.setTimeout(()=>window.location.reload(),350);
      }catch(error){
        if(orderStatus){orderStatus.textContent=error.message||'Không thể lưu thứ tự';orderStatus.classList.add('danger');}
        if(submit)submit.disabled=false;
      }
    });

    qsa('[data-om11-confirm]').forEach(element=>{
      const eventName=element.tagName==='FORM'?'submit':'click';
      element.addEventListener(eventName,event=>{
        if(!window.confirm(element.dataset.om11Confirm||'Xác nhận thao tác này?')){event.preventDefault();return;}
        const button=element.tagName==='FORM'?qs('button[type=submit]',element):element;if(button){button.disabled=true;button.setAttribute('aria-busy','true');}
      });
    });
  });
  function setVal(sel,val){const el=qs(sel);if(el)el.value=val??'';}
  function resetPlan(){setVal('#om10ProfileId','');setVal('#om10SiteSelect','');setVal('#om10SiteName','');setVal('#om10Customer','');setVal('#om10Address','');setVal('#om10Kwp','');setVal('#om10Rounds','5');const t=qs('#om10PlanTitle');if(t)t.textContent='Kế hoạch bảo trì mới';}
})();

/* EGO_QUICK_MAINTENANCE_PROJECT_V1 */
document.addEventListener('DOMContentLoaded', function () {

    const incidentModalEl =
        document.getElementById(
            'om10IncidentModal'
        );

    const quickModalEl =
        document.getElementById(
            'omQuickProjectModal'
        );

    const quickForm =
        document.getElementById(
            'omQuickProjectForm'
        );

    if (
        !incidentModalEl
        || !quickModalEl
        || !quickForm
    ) {
        return;
    }

    const quickModal =
        bootstrap.Modal.getOrCreateInstance(
            quickModalEl
        );

    const incidentModal =
        bootstrap.Modal.getOrCreateInstance(
            incidentModalEl
        );

    const siteSelect =
        document.getElementById(
            'omIncidentSiteSelect'
        );

    const profileInput =
        document.getElementById(
            'omIncidentProfileId'
        );

    const siteNameInput =
        document.getElementById(
            'omIncidentSiteName'
        );

    const customerInput =
        document.getElementById(
            'omIncidentCustomer'
        );

    const addressInput =
        document.getElementById(
            'omIncidentAddress'
        );

    const kwpInput =
        document.getElementById(
            'omIncidentKwp'
        );

    const resultBox =
        document.getElementById(
            'omIncidentQuickProjectResult'
        );

    const errorBox =
        document.getElementById(
            'omQuickProjectError'
        );

    const submitButton =
        document.getElementById(
            'omQuickProjectSubmit'
        );

    document
        .querySelectorAll(
            '[data-om-quick-project-open]'
        )
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    /*
                     * Giữ popup sự cố phía sau,
                     * mở popup tạo công trình lên trên.
                     */
                    quickForm.reset();

                    if (errorBox) {
                        errorBox.hidden = true;
                        errorBox.textContent = '';
                    }

                    quickModal.show();
                }
            );
        });

    /*
     * Nếu user chọn công trình có sẵn,
     * bỏ profile tạo nhanh.
     */
    if (siteSelect) {
        siteSelect.addEventListener(
            'change',
            function () {

                const option = siteSelect.selectedOptions[0];

                if (profileInput) {
                    profileInput.value = option?.dataset?.profileId || '';
                }
                if (siteNameInput) siteNameInput.value = option?.dataset?.name || '';
                if (customerInput) customerInput.value = option?.dataset?.customer || '';
                if (addressInput) addressInput.value = option?.dataset?.address || '';
                if (kwpInput) kwpInput.value = option?.dataset?.kwp || '';

                if (resultBox) {
                    resultBox.hidden = true;
                }
            }
        );
    }

    quickForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();

            if (errorBox) {
                errorBox.hidden = true;
                errorBox.textContent = '';
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML =
                    'Đang tạo...';
            }

            try {

                const formData =
                    new FormData(
                        quickForm
                    );

                const csrf =
                    quickForm.querySelector(
                        '[name="_token"]'
                    )?.value;

                const response =
                    await fetch(
                        quickForm.dataset.submitUrl,
                        {
                            method: 'POST',
                            headers: {
                                'Accept':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    csrf || '',
                            },
                            body: formData,
                        }
                    );

                const json =
                    await response.json();

                if (
                    !response.ok
                    || !json.success
                ) {
                    let message =
                        json.message
                        || 'Không thể tạo công trình.';

                    if (json.errors) {
                        message =
                            Object.values(
                                json.errors
                            )
                                .flat()
                                .join('\n');
                    }

                    throw new Error(
                        message
                    );
                }

                const project =
                    json.project;

                /*
                 * Công trình mới không cần site_id.
                 * Dùng profile + dữ liệu hidden.
                 */
                if (siteSelect) {
                    siteSelect.value = '';
                    siteSelect.required = false;
                }

                if (profileInput) {
                    profileInput.value =
                        project
                            .maintenance_profile_id
                        || '';
                }

                if (siteNameInput) {
                    siteNameInput.value =
                        project.name
                        || '';
                }

                if (customerInput) {
                    customerInput.value =
                        project.customer_name
                        || '';
                }

                if (addressInput) {
                    addressInput.value =
                        project.address
                        || '';
                }

                if (kwpInput) {
                    kwpInput.value =
                        project.system_kwp
                        || '';
                }

                if (resultBox) {
                    resultBox.hidden = false;
                    resultBox.innerHTML =
                        '<i class="bi bi-check-circle"></i> '
                        +'Đã chọn công trình mới: <strong>'
                        +escapeHtml(
                            project.name
                        )
                        +'</strong>';
                }

                quickModal.hide();

                /*
                 * Quay lại popup sự cố.
                 */
                setTimeout(
                    function () {
                        incidentModal.show();
                    },
                    200
                );

            } catch (error) {

                if (errorBox) {
                    errorBox.hidden = false;
                    errorBox.textContent =
                        error.message
                        || 'Có lỗi xảy ra.';
                }

            } finally {

                if (submitButton) {
                    submitButton.disabled =
                        false;

                    submitButton.innerHTML =
                        '<i class="bi bi-check2"></i> Tạo & chọn công trình';
                }
            }
        }
    );

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(
                /&/g,
                '&amp;'
            )
            .replace(
                /</g,
                '&lt;'
            )
            .replace(
                />/g,
                '&gt;'
            )
            .replace(
                /"/g,
                '&quot;'
            )
            .replace(
                /'/g,
                '&#039;'
            );
    }
});


/* EGO_PLAN_QUICK_PROJECT_V1 */
document.addEventListener('DOMContentLoaded', function () {

    const openButtons =
        document.querySelectorAll(
            '[data-om-plan-quick-project-open]'
        );

    if (!openButtons.length) return;

    const quickModalEl =
        document.getElementById('omQuickProjectModal');

    const quickForm =
        document.getElementById('omQuickProjectForm');

    const planSiteSelect =
        document.getElementById('om10SiteSelect');

    const planProfile =
        document.getElementById('om10ProfileId');

    const resultBox =
        document.getElementById('omPlanQuickProjectResult');

    const errorBox =
        document.getElementById('omQuickProjectError');

    const submitButton =
        document.getElementById('omQuickProjectSubmit');

    if (!quickModalEl || !quickForm) return;

    const quickModal =
        bootstrap.Modal.getOrCreateInstance(
            quickModalEl
        );

    let target = 'plan';

    openButtons.forEach(function (button) {
        button.addEventListener(
            'click',
            function () {
                target = 'plan';

                quickForm.reset();

                if (errorBox) {
                    errorBox.hidden = true;
                    errorBox.textContent = '';
                }

                quickModal.show();
            }
        );
    });

    /*
     * Tránh bind submit 2 lần nếu JS sự cố trước đó đã bind.
     */
    if (
        quickForm.dataset.planBound === '1'
    ) {
        return;
    }

    quickForm.dataset.planBound = '1';

    quickForm.addEventListener(
        'submit',
        async function (event) {

            if (target !== 'plan') return;

            event.preventDefault();
            event.stopImmediatePropagation();

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Đang tạo...';
            }

            try {

                const response = await fetch(
                    quickForm.dataset.submitUrl,
                    {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN':
                                quickForm.querySelector(
                                    '[name="_token"]'
                                )?.value || ''
                        },
                        body: new FormData(quickForm)
                    }
                );

                const json = await response.json();

                if (!response.ok || !json.success) {
                    throw new Error(
                        json.message ||
                        'Không thể tạo công trình.'
                    );
                }

                const p = json.project;

                /*
                 * Thêm option mới vào dropdown ngay lập tức.
                 */
                if (planSiteSelect) {

                    const option =
                        document.createElement('option');

                    /*
                     * Nếu công trình mới không có site_id,
                     * dùng project id dạng đặc biệt và profile hidden
                     * cho backend phân biệt.
                     */
                    option.value = '';
                    option.dataset.profileId = p.maintenance_profile_id || '';
                    option.dataset.name = p.name || '';
                    option.dataset.customer = p.customer_name || '';
                    option.dataset.address = p.address || '';
                    option.dataset.kwp = p.system_kwp || '';

                    option.textContent =
                        p.name
                        + (
                            p.customer_name
                            ? ' — ' + p.customer_name
                            : ''
                        );

                    option.selected = true;

                    planSiteSelect.appendChild(option);
                    planSiteSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (planProfile) {
                    planProfile.value =
                        p.maintenance_profile_id || '';
                }

                if (resultBox) {
                    resultBox.hidden = false;
                    resultBox.textContent =
                        'Đã tạo & chọn: ' + p.name;
                }

                quickModal.hide();

            } catch (error) {

                if (errorBox) {
                    errorBox.hidden = false;
                    errorBox.textContent =
                        error.message;
                }

            } finally {

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent =
                        'Tạo & chọn công trình';
                }
            }
        },
        true
    );

});

/* EGO_PLAN_QUICK_PROJECT_MODAL_SWITCH_V2 */
document.addEventListener('DOMContentLoaded', function () {

    const planModalEl =
        document.getElementById('om10PlanModal');

    const quickModalEl =
        document.getElementById('omQuickProjectModal');

    const quickForm =
        document.getElementById('omQuickProjectForm');

    const openButtons =
        document.querySelectorAll(
            '[data-om-plan-quick-project-open]'
        );

    if (
        !planModalEl ||
        !quickModalEl ||
        !quickForm ||
        !openButtons.length
    ) {
        return;
    }

    /*
     * Bảo đảm modal tạo nhanh nằm trực tiếp trong body,
     * tránh bị kẹt z-index trong modal cha.
     */
    if (quickModalEl.parentElement !== document.body) {
        document.body.appendChild(quickModalEl);
    }

    const planModal =
        bootstrap.Modal.getOrCreateInstance(
            planModalEl
        );

    const quickModal =
        bootstrap.Modal.getOrCreateInstance(
            quickModalEl
        );

    let reopenPlan = false;

    openButtons.forEach(function (button) {

        /*
         * Clone button để loại listener cũ đã bind trước đó.
         */
        const freshButton =
            button.cloneNode(true);

        button.replaceWith(
            freshButton
        );

        freshButton.addEventListener(
            'click',
            function (event) {

                event.preventDefault();
                event.stopPropagation();

                reopenPlan = true;

                quickForm.reset();

                const errorBox =
                    document.getElementById(
                        'omQuickProjectError'
                    );

                if (errorBox) {
                    errorBox.hidden = true;
                    errorBox.textContent = '';
                }

                /*
                 * Ẩn kế hoạch trước.
                 * Chỉ sau khi hidden hoàn toàn mới mở modal tạo công trình.
                 */
                const openQuick = function () {

                    planModalEl.removeEventListener(
                        'hidden.bs.modal',
                        openQuick
                    );

                    /*
                     * Dọn backdrop cũ nếu Bootstrap còn giữ.
                     */
                    document
                        .querySelectorAll(
                            '.modal-backdrop'
                        )
                        .forEach(function (backdrop) {
                            backdrop.remove();
                        });

                    document.body.classList.remove(
                        'modal-open'
                    );

                    document.body.style.removeProperty(
                        'padding-right'
                    );

                    setTimeout(
                        function () {
                            quickModal.show();
                        },
                        80
                    );
                };

                planModalEl.addEventListener(
                    'hidden.bs.modal',
                    openQuick
                );

                planModal.hide();
            }
        );
    });

    /*
     * Nếu bấm X hoặc Hủy ở modal tạo công trình,
     * quay lại modal Kế hoạch.
     */
    quickModalEl.addEventListener(
        'hidden.bs.modal',
        function () {

            if (!reopenPlan) {
                return;
            }

            reopenPlan = false;

            document
                .querySelectorAll(
                    '.modal-backdrop'
                )
                .forEach(function (backdrop) {
                    backdrop.remove();
                });

            document.body.classList.remove(
                'modal-open'
            );

            document.body.style.removeProperty(
                'padding-right'
            );

            setTimeout(
                function () {
                    planModal.show();
                },
                100
            );
        }
    );

});

/* O&M V13 — bật/tắt form nhân công ngoài trong modal Phân công */
(function () {
  function initExternalLaborToggle() {
    var toggle = document.querySelector('[data-om18-external-toggle]');
    var fields = document.querySelector('[data-om18-external-fields]');
    if (!toggle || !fields) return;

    function sync() {
      fields.hidden = !toggle.checked;
      fields.querySelectorAll('input,textarea,select').forEach(function (el) {
        if (el.name === 'external_labor_headcount' || el.name === 'external_labor_total_cost') {
          el.required = !!toggle.checked;
        }
      });
    }

    toggle.addEventListener('change', sync);
    sync();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExternalLaborToggle);
  } else {
    initExternalLaborToggle();
  }
})();
