{{-- resources/views/chat/widget.blade.php --}}
@if(auth()->check())
@once

<style>
  #chat-float-btn{
    position: fixed;
    right: 20px;
    bottom: 20px;
    width: 54px;
    height: 54px;
    border-radius: 999px;
    border: none;
    background: #0d6efd;
    color: #fff;
    box-shadow: 0 10px 25px rgba(0,0,0,.18);
    z-index: 2147483647;
    cursor: pointer;
  }

  #chat-float-box{
    position: fixed;
    right: 20px;
    bottom: 90px;
    width: 420px;
    height: 560px;
    background: #fff;
    border: 1px solid #e6e6e6;
    border-radius: 12px;
    box-shadow: 0 12px 35px rgba(0,0,0,.18);
    z-index: 2147483647;

    display: none;          /* JS sẽ bật lên */
    flex-direction: column;
    overflow: hidden;
  }

  #chat-float-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    background:#fff;
    flex: 0 0 auto;
  }
  #chat-float-title{
    font-weight: 700;
    font-size: 15px;
  }
  #chat-float-close{
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
  }

  /* layout 2 cột */
  #chat-float-main{
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    overflow: hidden;
  }

  /* Cột trái: danh sách */
  #chat-left{
    width: 180px;
    border-right: 1px solid #eee;
    background: #fff;
    display:flex;
    flex-direction: column;
    min-width: 160px;
  }
  #chat-left-head{
    padding: 10px 10px 6px;
    border-bottom: 1px solid #f0f0f0;
    font-weight: 600;
    font-size: 13px;
  }
  #chatStatus{
    padding: 0 10px 8px;
    font-size: 12px;
    color: #d33;
    border-bottom: 1px solid #eee;
  }
  #chat-list{
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    padding: 8px;
  }

  .chat-item{
    display:flex;
    gap: 8px;
    align-items: center;
    padding: 8px;
    border-radius: 10px;
    cursor: pointer;
  }
  .chat-item:hover{ background:#f5f7ff; }
  .chat-item.active{ background:#eaf1ff; }

  .avatar{
    width: 36px;
    height: 36px;
    border-radius: 999px;
    background: #e9ecef;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    font-size: 12px;
    position: relative;
    overflow: hidden;
    flex: 0 0 auto;
  }
  .avatar img{
    width:100%;
    height:100%;
    border-radius:999px;
    object-fit:cover;
    display:block;
  }
  .badge-unread{
    position:absolute;
    right: -4px;
    top: -4px;
    background:#dc3545;
    color:#fff;
    font-size:10px;
    padding:2px 5px;
    border-radius: 999px;
    border: 2px solid #fff;
    min-width: 18px;
    text-align:center;
    line-height: 12px;
  }

  .chat-meta{ min-width:0; flex: 1; }
  .chat-name{
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .chat-last{
    font-size: 12px;
    color:#666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .chat-time{
    font-size: 10px;
    color:#999;
    margin-left:auto;
  }

  /* Cột phải: khung chat */
  #chat-right{
    flex: 1 1 auto;
    display:flex;
    flex-direction: column;
    min-width: 0;
    background:#fff;
  }
  #chat-right-top{
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    font-weight: 700;
    font-size: 13px;
    flex: 0 0 auto;
  }
  #chat-float-body{
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    padding: 12px;
    background: #fafafa;
  }
  #chat-float-footer{
    padding: 10px 12px;
    border-top: 1px solid #eee;
    background: #fff;
    flex: 0 0 auto;
  }
  #chat-float-form{
    display:flex;
    gap:8px;
    align-items:center;
  }
  #chat-float-input{
    flex:1;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #ddd;
    border-radius: 10px;
    outline: none;
  }
  #chat-float-send{
    height: 40px;
    padding: 0 14px;
    border: none;
    border-radius: 10px;
    background: #0d6efd;
    color: #fff;
    cursor: pointer;
  }

  .msg-row{ display:flex; margin-bottom: 10px; }
  .msg-row.me{ justify-content:flex-end; }
  .msg-bubble{
    max-width: 75%;
    padding: 8px 10px;
    border-radius: 12px;
    background:#fff;
    border: 1px solid #eee;
  }
  .msg-row.me .msg-bubble{
    background:#0d6efd;
    color:#fff;
    border-color:#0d6efd;
  }
  .msg-name{ font-size: 12px; font-weight: 700; margin-bottom: 2px; opacity:.9; }
  .msg-time{ font-size: 11px; opacity:.7; margin-top: 2px; }

  @media (max-width: 480px){
    #chat-float-box{
      right: 10px;
      left: 10px;
      width: auto;
      height: 70vh;
      bottom: 80px;
    }
  }

  #chat-float-box.is-open{
    display:flex !important;
    animation:egoChatPop .22s cubic-bezier(.22,1,.36,1) both;
  }
</style>

<button id="chat-float-btn" type="button" title="Chat" >💬</button>

<div id="chat-float-box"
     data-myid="{{ (int)auth()->id() }}"
     data-baseurl="{{ url('') }}"
     data-csrf="{{ csrf_token() }}">

  <div id="chat-float-header">
    <div id="chat-float-title">Chat</div>
    <button id="chat-float-close" type="button">✕</button>
  </div>

  <div id="chat-float-main">
    <div id="chat-left">
      <div id="chat-left-head">Danh sách</div>
      <div id="chatStatus">STATUS: init</div>
      <div id="chat-list"></div>
    </div>

    <div id="chat-right">
      <div id="chat-right-top">
        <span id="chat-right-title">Chọn người ở cột trái</span>
      </div>

      <div id="chat-float-body"></div>

      <div id="chat-float-footer">
        <form id="chat-float-form">
          <input id="chat-float-input" type="text" placeholder="Nhắn tin..." autocomplete="off" />
          <button id="chat-float-send" type="submit">Gửi</button>
        </form>
      </div>
    </div>
  </div>
</div>

<audio id="tingSound" preload="auto">
  <source src="{{ asset('sounds/ting.mp3') }}" type="audio/mpeg">
</audio>


<script>
/* EGO_CHAT_FORCE_OPEN_START */
(function(){
  function getBox(){ return document.getElementById('chat-float-box'); }
  function getBtn(){ return document.getElementById('chat-float-btn'); }

  document.addEventListener('click', function(e){
    var btn = e.target.closest && e.target.closest('#chat-float-btn');
    var close = e.target.closest && e.target.closest('#chat-float-close');

    if (btn) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      var box = getBox();
      if (!box) return false;

      var willOpen = !box.classList.contains('is-open');
      box.classList.toggle('is-open', willOpen);
      box.style.display = willOpen ? 'flex' : 'none';

      return false;
    }

    if (close) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      var box2 = getBox();
      if (!box2) return false;

      box2.classList.remove('is-open');
      box2.style.display = 'none';

      return false;
    }
  }, true);

  window.egoOpenChat = function(){
    var box = getBox();
    if (!box) return;
    box.classList.add('is-open');
    box.style.display = 'flex';
  };

  window.egoCloseChat = function(){
    var box = getBox();
    if (!box) return;
    box.classList.remove('is-open');
    box.style.display = 'none';
  };
})();
 /* EGO_CHAT_FORCE_OPEN_END */
</script>

<script src="{{ asset('js/chat-widget.js') }}?v={{ time() }}" defer></script>

@endonce
@endif
