(function(){
  'use strict';
  var root=document.querySelector('[data-emd9-root]');
  if(!root){return;}
  var tabButtons=Array.prototype.slice.call(root.querySelectorAll('[data-emd9-tab]'));
  var panels=Array.prototype.slice.call(root.querySelectorAll('[data-emd9-panel]'));
  function openTab(name){
    tabButtons.forEach(function(btn){btn.classList.toggle('active',btn.getAttribute('data-emd9-tab')===name);});
    panels.forEach(function(panel){panel.classList.toggle('active',panel.getAttribute('data-emd9-panel')===name);});
    try{window.sessionStorage.setItem('emd9_active_tab',name);}catch(e){}
  }
  tabButtons.forEach(function(btn){btn.addEventListener('click',function(){openTab(btn.getAttribute('data-emd9-tab'));});});
  var saved='';try{saved=window.sessionStorage.getItem('emd9_active_tab')||'';}catch(e){}
  if(saved && root.querySelector('[data-emd9-panel="'+saved+'"]')){openTab(saved);}

  function setEdit(active){
    var read=root.querySelector('[data-emd9-read]');
    var form=root.querySelector('[data-emd9-form]');
    if(!form){return;}
    if(read){read.classList.toggle('hidden',active);}
    form.classList.toggle('active',active);
    if(active){openTab('overview');var first=form.querySelector('input,select,textarea');if(first){setTimeout(function(){first.focus();},60);}}
  }
  root.querySelectorAll('[data-emd9-edit]').forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();setEdit(true);});});
  root.querySelectorAll('[data-emd9-cancel-edit]').forEach(function(btn){btn.addEventListener('click',function(){setEdit(false);});});

  root.querySelectorAll('[data-emd9-toggle]').forEach(function(btn){btn.addEventListener('click',function(){var target=root.querySelector(btn.getAttribute('data-emd9-toggle'));if(target){target.classList.toggle('hidden');}});});
  root.querySelectorAll('[data-emd9-go-tab]').forEach(function(btn){btn.addEventListener('click',function(){openTab(btn.getAttribute('data-emd9-go-tab'));var selector=btn.getAttribute('data-emd9-scroll');if(selector){setTimeout(function(){var el=root.querySelector(selector);if(el){el.scrollIntoView({behavior:'smooth',block:'center'});}},80);}});});

  root.querySelectorAll('form').forEach(function(form){form.addEventListener('submit',function(){var submit=form.querySelector('button[type="submit"]');if(submit){submit.disabled=true;submit.dataset.oldText=submit.innerHTML;submit.innerHTML='<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Đang xử lý...';setTimeout(function(){submit.disabled=false;submit.innerHTML=submit.dataset.oldText||'Lưu';},8000);}});});
})();
