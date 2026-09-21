{{-- Nạp CSS module Kỹ thuật (file tĩnh trong public/, layout không dùng @vite). --}}
<link rel="stylesheet"
      href="{{ asset('css/ego-technical-work.css') }}?v={{ file_exists(public_path('css/ego-technical-work.css')) ? filemtime(public_path('css/ego-technical-work.css')) : '1' }}">
<link rel="stylesheet"
      href="{{ asset('css/ego-technical-plan.css') }}?v={{ file_exists(public_path('css/ego-technical-plan.css')) ? filemtime(public_path('css/ego-technical-plan.css')) : '1' }}">
