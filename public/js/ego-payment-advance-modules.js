(function(){
  function digits(value){return String(value||'').replace(/\D+/g,'');}
  function money(value){var n=parseInt(digits(value)||'0',10);return n.toLocaleString('vi-VN');}
  function bindMoney(input){
    if(!input||input.dataset.moneyBound)return;
    input.dataset.moneyBound='1';
    input.addEventListener('input',function(){var raw=digits(this.value);this.value=raw?money(raw):'';calcSettlement();});
    if(input.value){input.value=money(input.value);}
  }
  function calcSettlement(){
    var input=document.querySelector('[data-settlement-actual]');
    var out=document.querySelector('[data-settlement-result]');
    if(!input||!out)return;
    var advance=parseInt(out.dataset.advance||'0',10)||0;
    var actual=parseInt(digits(input.value)||'0',10)||0;
    var diff=advance-actual;
    if(diff>0){out.className='ego-adv-balance return';out.innerHTML='Nhân viên hoàn lại: <strong>'+money(diff)+' đ</strong>';}
    else if(diff<0){out.className='ego-adv-balance extra';out.innerHTML='Công ty thanh toán thêm: <strong>'+money(Math.abs(diff))+' đ</strong>';}
    else{out.className='ego-adv-balance done';out.innerHTML='<strong>Đã quyết toán đủ</strong>';}
  }
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('[data-money-input]').forEach(bindMoney);
    calcSettlement();
  });
})();
