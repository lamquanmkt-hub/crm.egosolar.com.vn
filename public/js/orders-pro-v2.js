(function(){
  'use strict';
  const qa=(s,c=document)=>Array.from(c.querySelectorAll(s));
  const q=(s,c=document)=>c.querySelector(s);

  function closeMenus(except){
    qa('.erp-menu.open').forEach(m=>{if(m!==except)m.classList.remove('open');});
  }

  document.addEventListener('click',function(e){
    const menuBtn=e.target.closest('[data-erp-menu]');
    if(menuBtn){
      e.preventDefault();e.stopPropagation();
      const menu=menuBtn.closest('.erp-menu');
      const willOpen=!menu.classList.contains('open');
      closeMenus(menu);
      menu.classList.toggle('open',willOpen);
      return;
    }
    if(!e.target.closest('.erp-menu'))closeMenus();

    const adv=e.target.closest('[data-erp-advanced]');
    if(adv){
      e.preventDefault();
      const target=document.getElementById(adv.getAttribute('data-erp-advanced'));
      if(target)target.classList.toggle('open');
      return;
    }

    const tab=e.target.closest('[data-erp-tab]');
    if(tab){
      e.preventDefault();
      const root=tab.closest('[data-erp-tabs-root]');
      if(!root)return;
      const name=tab.getAttribute('data-erp-tab');
      qa('[data-erp-tab]',root).forEach(b=>b.classList.toggle('active',b===tab));
      qa('[data-erp-panel]',root).forEach(p=>p.classList.toggle('active',p.getAttribute('data-erp-panel')===name));
      try{sessionStorage.setItem('erp-tab:'+location.pathname,name);}catch(_e){}
      return;
    }

    const modalOpen=e.target.closest('[data-erp-modal-open]');
    if(modalOpen){
      e.preventDefault();
      const modal=document.getElementById(modalOpen.getAttribute('data-erp-modal-open'));
      if(modal)modal.classList.add('open');
      return;
    }
    const modalClose=e.target.closest('[data-erp-modal-close]');
    if(modalClose){
      e.preventDefault();
      const modal=modalClose.closest('.erp-modal-backdrop');
      if(modal)modal.classList.remove('open');
      return;
    }
    if(e.target.classList.contains('erp-modal-backdrop'))e.target.classList.remove('open');

    const copy=e.target.closest('[data-copy]');
    if(copy){
      e.preventDefault();
      const value=copy.getAttribute('data-copy')||'';
      navigator.clipboard?.writeText(value).then(()=>{
        const old=copy.innerHTML;copy.innerHTML='<i class="bi bi-check2"></i>';
        setTimeout(()=>copy.innerHTML=old,1100);
      }).catch(()=>{});
    }
  });

  document.addEventListener('DOMContentLoaded',function(){
    qa('[data-erp-tabs-root]').forEach(root=>{
      let saved='';
      try{saved=sessionStorage.getItem('erp-tab:'+location.pathname)||'';}catch(_e){}
      const button=saved?q('[data-erp-tab="'+CSS.escape(saved)+'"]',root):null;
      if(button)button.click();
    });
    qa('[data-confirm]').forEach(el=>{
      el.addEventListener('submit',function(e){
        const message=el.getAttribute('data-confirm')||'Xác nhận thao tác này?';
        if(!window.confirm(message))e.preventDefault();
      });
    });
  });

  window.erpOpenTab=function(name){
    const button=q('[data-erp-tab="'+CSS.escape(name)+'"]');
    if(button){button.click();button.scrollIntoView({behavior:'smooth',block:'nearest'});}
  };
})();
