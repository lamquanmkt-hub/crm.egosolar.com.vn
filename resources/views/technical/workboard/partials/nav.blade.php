{{--
    Giai đoạn 2: dãy tab điều hướng ở đây ĐÃ ĐƯỢC GỠ BỎ.

    Lý do: nó lặp lại đúng các mục đã có trong sidebar (config/ego_menu_v4.php →
    partials/workspace-menu-excel-v3 → partials/sidebar), khiến người dùng thấy
    cùng một menu hai lần trên mỗi màn hình.

    File được GIỮ LẠI (không xoá) để mọi `@include` cũ vẫn hợp lệ; các nút
    chuyển màn hình thực sự cần thiết nằm ở `.tw-head__actions` của từng trang.
--}}
