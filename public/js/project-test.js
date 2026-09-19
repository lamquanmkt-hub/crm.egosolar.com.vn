(function(){
    'use strict';
    const q=(s,c=document)=>c.querySelector(s), qa=(s,c=document)=>Array.from(c.querySelectorAll(s));

    qa('[data-pt-tab]').forEach(btn=>btn.addEventListener('click',()=>{
        qa('[data-pt-tab]').forEach(x=>x.classList.toggle('active',x===btn));
        qa('[data-pt-panel]').forEach(x=>x.classList.toggle('active',x.dataset.ptPanel===btn.dataset.ptTab));
        try{localStorage.setItem('pt-active-tab',btn.dataset.ptTab)}catch(e){}
    }));
    const saved=(()=>{try{return localStorage.getItem('pt-active-tab')}catch(e){return null}})();
    if(saved&&q(`[data-pt-tab="${saved}"]`)) q(`[data-pt-tab="${saved}"]`).click();

    qa('[data-pt-counter]').forEach(el=>{
        const end=Number(el.textContent.replace(/\D/g,''))||0; let start=0; const t=performance.now();
        const tick=now=>{const p=Math.min(1,(now-t)/650); start=Math.round(end*(1-Math.pow(1-p,3))); el.textContent=start.toLocaleString('vi-VN'); if(p<1)requestAnimationFrame(tick)}; requestAnimationFrame(tick);
    });

    const wizard=q('[data-pt-wizard]');
    if(wizard){
        let step=1; const max=3;
        const render=()=>{
            qa('[data-wizard-step]',wizard).forEach(x=>x.classList.toggle('active',Number(x.dataset.wizardStep)===step));
            qa('[data-wizard-panel]',wizard).forEach(x=>x.classList.toggle('active',Number(x.dataset.wizardPanel)===step));
            const prev=q('[data-wizard-prev]',wizard), next=q('[data-wizard-next]',wizard), submit=q('[data-wizard-submit]',wizard);
            if(prev)prev.style.display=step===1?'none':'inline-flex'; if(next)next.style.display=step===max?'none':'inline-flex'; if(submit)submit.style.display=step===max?'inline-flex':'none';
            if(step===max) updateReview(wizard);
        };
        q('[data-wizard-next]',wizard)?.addEventListener('click',()=>{const panel=q(`[data-wizard-panel="${step}"]`,wizard); const required=qa('[required]',panel); if(required.some(i=>!i.reportValidity()))return; step=Math.min(max,step+1);render()});
        q('[data-wizard-prev]',wizard)?.addEventListener('click',()=>{step=Math.max(1,step-1);render()}); render();
    }

    function updateReview(root){
        qa('[data-review-field]',root).forEach(el=>{const input=q(`[name="${el.dataset.reviewField}"]`,root); if(!input)return; let value=input.value||'—'; if(input.tagName==='SELECT') value=input.options[input.selectedIndex]?.text||'—'; el.textContent=value});
    }

    const customer=q('[data-customer-select]');
    if(customer){customer.addEventListener('change',()=>{const o=customer.options[customer.selectedIndex]; if(!o)return; const name=q('[name="contact_name"]'),phone=q('[name="contact_phone"]'),address=q('[name="address"]'); if(name&&!name.value)name.value=o.dataset.name||''; if(phone&&!phone.value)phone.value=o.dataset.phone||''; if(address&&!address.value)address.value=o.dataset.address||''})}

    qa('[data-add-material]').forEach(btn=>btn.addEventListener('click',()=>{
        const tbody=q(btn.dataset.addMaterial);
        const tpl=q(btn.dataset.materialTemplate||'[data-material-template]');
        if(!tbody||!tpl)return;
        const node=tpl.content.cloneNode(true);
        tbody.appendChild(node);
        const rows=qa('[data-material-line]',tbody);
        rows[rows.length-1]?.querySelector('[name="item_name[]"]')?.focus();
    }));

    document.addEventListener('click',e=>{
        const materialButton=e.target.closest('[data-remove-material-line]');
        if(materialButton){
            const row=materialButton.closest('[data-material-line]');
            const form=materialButton.closest('[data-material-edit-form]');
            const rows=qa('[data-material-line]',form||document);
            if(rows.length<=1){
                alert('Phiếu vật tư phải còn ít nhất 1 dòng.');
                return;
            }
            row?.remove();
            return;
        }

        const b=e.target.closest('[data-remove-line]');
        if(b)b.closest('tr')?.remove();
    });

    qa('[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm||'Xác nhận thực hiện thao tác này?'))e.preventDefault()}));

    const search=q('[data-live-filter]');
    if(search){search.addEventListener('input',()=>{const term=search.value.trim().toLowerCase(); qa('[data-filter-row]').forEach(row=>row.hidden=!row.dataset.filterRow.toLowerCase().includes(term))})}
})();
