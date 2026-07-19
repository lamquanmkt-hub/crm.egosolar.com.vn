(function(){
'use strict';
const $=(s,c=document)=>c.querySelector(s);const $$=(s,c=document)=>Array.from(c.querySelectorAll(s));
const page=$('[data-order-page]');
function setTab(name,scroll=true){
 if(!page)return;
 $$('[data-op-tab]',page).forEach(b=>b.classList.toggle('active',b.dataset.opTab===name));
 $$('[data-op-panel]',page).forEach(p=>p.classList.toggle('active',p.dataset.opPanel===name));
 const url=new URL(location.href);url.searchParams.set('tab',name);history.replaceState({},'',url);
 try{sessionStorage.setItem('order-tab:'+location.pathname,name)}catch(e){}
 if(scroll){$('.op-shell')?.scrollIntoView({behavior:'smooth',block:'start'});}
}
function openOverlay(id){const el=document.getElementById(id);if(!el)return;el.classList.add('open');document.body.style.overflow='hidden';if(id==='warehouseModal')loadSerials();}
function closeOverlay(el){if(!el)return;el.classList.remove('open');if(!$('.op-modal-backdrop.open,.op-drawer-backdrop.open'))document.body.style.overflow='';}
document.addEventListener('click',e=>{
 const tab=e.target.closest('[data-op-tab]');if(tab){e.preventDefault();setTab(tab.dataset.opTab);return;}
 const tabTarget=e.target.closest('[data-op-tab-target]');if(tabTarget){e.preventDefault();const parentOverlay=tabTarget.closest('[data-op-overlay]');if(parentOverlay)closeOverlay(parentOverlay);setTab(tabTarget.dataset.opTabTarget);return;}
 const open=e.target.closest('[data-op-open]');if(open){e.preventDefault();openOverlay(open.dataset.opOpen);return;}
 const close=e.target.closest('[data-op-close]');if(close){e.preventDefault();closeOverlay(close.closest('[data-op-overlay]'));return;}
 const overlay=e.target.matches('[data-op-overlay]')?e.target:null;if(overlay)closeOverlay(overlay);
});
document.addEventListener('keydown',e=>{if(e.key==='Escape')$$('[data-op-overlay].open').forEach(closeOverlay);});
$$('[data-op-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.opConfirm||'Xác nhận thao tác này?'))e.preventDefault();}));
$$('[data-op-approval-form]').forEach(form=>{
 const radios=$$('input[name="action"]',form),box=$('[data-op-reject-reason]',form),reason=$('textarea[name="rejection_reason"]',form);
 const sync=()=>{const reject=$('input[name="action"]:checked',form)?.value==='reject';if(box)box.hidden=!reject;if(reason)reason.required=reject;};radios.forEach(r=>r.addEventListener('change',sync));sync();
 form.addEventListener('submit',e=>{const action=$('input[name="action"]:checked',form)?.value||'approve';if(!confirm(action==='reject'?'Từ chối đơn hàng này?':'Phê duyệt đơn hàng này?'))e.preventDefault();});
});
if(page){let initial=page.dataset.activeTab||'';try{initial=new URL(location.href).searchParams.get('tab')||sessionStorage.getItem('order-tab:'+location.pathname)||initial;}catch(e){}if(initial)setTab(initial,false);}
let serialLoaded=false;
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
function renderSerials(items){const box=$('#ship-serials-container');if(!box)return;if(!items?.length){box.innerHTML='<div class="op-empty">Đơn này không có sản phẩm cần chọn serial.</div>';return;}box.innerHTML=items.map(item=>{const need=Number(item.quantity||0),list=item.available_serials||[];return `<article class="op-return-item op-serial-card" data-item-id="${esc(item.order_item_id)}"><div class="op-return-item-head"><div><strong>${esc(item.product_name)}</strong><small>SKU ${esc(item.sku||'-')} · Cần chọn ${need} serial</small></div><span class="op-badge info" id="serial-count-${esc(item.order_item_id)}">0/${need}</span></div><div class="op-serial-checks">${list.length?list.map(s=>`<label data-serial-search="${esc((s.code||'')+' '+(s.warehouse_name||'')+' '+(item.product_name||''))}"><input class="op-ship-serial" type="checkbox" data-item-id="${esc(item.order_item_id)}" data-need="${need}" name="serials[${esc(item.order_item_id)}][]" value="${esc(s.id)}"> ${esc(s.code)} <small>${esc(s.warehouse_name||'Trong kho')}</small></label>`).join(''):'<div class="op-alert danger">Không có serial trong kho cho sản phẩm này.</div>'}</div></article>`;}).join('');
 $$('.op-ship-serial',box).forEach(input=>input.addEventListener('change',()=>{const id=input.dataset.itemId,need=Number(input.dataset.need||0),checked=$$(`.op-ship-serial[data-item-id="${CSS.escape(id)}"]:checked`,box);if(checked.length>need){input.checked=false;alert('Sản phẩm này chỉ cần chọn '+need+' serial.');}const count=$('#serial-count-'+CSS.escape(id),box);if(count)count.textContent=$$(`.op-ship-serial[data-item-id="${CSS.escape(id)}"]:checked`,box).length+'/'+need;}));
}
function loadSerials(){if(serialLoaded)return;const cfg=window.ORDER_ONE_PAGE||{},box=$('#ship-serials-container');if(!cfg.serialUrl||!box)return;box.innerHTML='<div class="op-empty">Đang tải serial...</div>';fetch(cfg.serialUrl,{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():Promise.reject()).then(json=>{const data=json.serials||{},items=Array.isArray(data)?data:(data.items||[]);renderSerials(items);serialLoaded=true;}).catch(()=>{box.innerHTML='<div class="op-alert danger">Không tải được danh sách serial. Vui lòng đóng và mở lại.</div>';});}
$('#serialSearch')?.addEventListener('input',function(){const term=this.value.trim().toLowerCase();$$('[data-serial-search]').forEach(el=>el.style.display=!term||el.dataset.serialSearch.toLowerCase().includes(term)?'':'none');});
$('#shipOrderForm')?.addEventListener('submit',e=>{let ok=true,msg='';$$('.op-serial-card').forEach(card=>{const first=$('.op-ship-serial',card);if(!first){ok=false;msg='Có sản phẩm cần serial nhưng không có serial trong kho.';return;}const need=Number(first.dataset.need||0),selected=$$('.op-ship-serial:checked',card).length;if(selected!==need){ok=false;msg=($('strong',card)?.textContent||'Sản phẩm')+' cần chọn đúng '+need+' serial. Hiện chọn '+selected+'.';}});if(!ok){e.preventDefault();const box=$('#ship-serials-error');if(box){box.hidden=false;box.innerHTML='<i class="bi bi-exclamation-triangle"></i><div>'+esc(msg)+'</div>';}else alert(msg);}else if(!confirm('Xác nhận xuất kho và trừ tồn?'))e.preventDefault();});

const productToggle=$('[data-op-toggle-products]');
if(productToggle){
 productToggle.addEventListener('click',()=>{
  const rows=$$('.op-main-product-extra');
  const expanding=rows.some(row=>row.hidden);
  rows.forEach(row=>row.hidden=!expanding);
  productToggle.classList.toggle('expanded',expanding);
  const label=$('span',productToggle);
  if(label)label.textContent=expanding?(productToggle.dataset.lessLabel||'Thu gọn sản phẩm'):(productToggle.dataset.moreLabel||'Xem toàn bộ sản phẩm');
 });
}
})();
