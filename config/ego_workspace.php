<?php

return array (
  'setting_key' => 'workspace.role_profiles.v2',
  'categories' => 
  array (
    'all' => 'Tất cả',
    'operations' => 'Điều hành',
    'sales' => 'Kinh doanh',
    'technical' => 'Kỹ thuật',
    'warehouse' => 'Kho vận',
    'finance' => 'Tài chính',
    'hr' => 'Nhân sự',
    'marketing' => 'Marketing',
    'utilities' => 'Tiện ích',
  ),
  'required_apps' => 
  array (
    0 => 'attendance',
    1 => 'booking',
    2 => 'tasks',
    3 => 'ai',
    4 => 'chat',
    5 => 'company',
    6 => 'proposals',
    7 => 'payment-requests',
  ),
  'profiles' => 
  array (
    'admin' => 
    array (
      'label' => 'Quản trị viên',
      'icon' => 'bi-shield-lock-fill',
      'role_names' => 
      array (
        0 => 'admin',
      ),
      'department_codes' => 
      array (
      ),
      'department_names' => 
      array (
      ),
      'apps' => 
      array (
        0 => '*',
      ),
      'featured' => 
      array (
        0 => 'dashboard',
        1 => 'orders',
        2 => 'ai',
      ),
      'default_category' => 'all',
    ),
    'management' => 
    array (
      'label' => 'Ban Giám đốc',
      'icon' => 'bi-person-badge-fill',
      'role_names' => 
      array (
        0 => 'management',
        1 => 'manager',
        2 => 'director',
        3 => 'ceo',
      ),
      'department_codes' => 
      array (
        0 => 'management',
      ),
      'department_names' => 
      array (
        0 => 'Ban giám đốc',
        1 => 'Trợ Lý',
      ),
      'apps' => 
      array (
        0 => '*',
      ),
      'featured' => 
      array (
        0 => 'dashboard',
        1 => 'orders',
        2 => 'payment-requests',
      ),
      'default_category' => 'all',
    ),
    'technical' => 
    array (
      'label' => 'Kỹ thuật',
      'icon' => 'bi-tools',
      'role_names' => 
      array (
        0 => 'technical_manager',
        1 => 'technical_leader',
        2 => 'technical',
        3 => 'technical_staff',
        4 => 'technician',
        5 => 'ky_thuat',
      ),
      'department_codes' => 
      array (
        0 => 'technical',
        1 => 'ky_thuat',
      ),
      'department_names' => 
      array (
        0 => 'Kỹ Thuật',
        1 => 'Phòng kỹ thuật',
      ),
      'apps' => 
      array (
        0 => 'technical-workspace',
        1 => 'sites',
        2 => 'solar',
        3 => 'products',
        4 => 'warehouses',
        5 => 'proposals',
        6 => 'payment-requests',
        7 => 'attendance',
        8 => 'booking',
        9 => 'tasks',
        10 => 'ai',
        11 => 'chat',
        12 => 'company',
      ),
      'featured' => 
      array (
        0 => 'technical-workspace',
        1 => 'sites',
        2 => 'solar',
      ),
      'default_category' => 'technical',
    ),
    'sales' => 
    array (
      'label' => 'Kinh doanh',
      'icon' => 'bi-graph-up-arrow',
      'role_names' => 
      array (
        0 => 'sales_manager',
        1 => 'sales_head',
        2 => 'sales',
      ),
      'department_codes' => 
      array (
        0 => 'sales',
      ),
      'department_names' => 
      array (
        0 => 'Marketing & Sales',
        1 => 'Kinh doanh',
        2 => 'Sales',
      ),
      'apps' => 
      array (
        0 => 'sales-workspace',
        1 => 'customers',
        2 => 'orders',
        3 => 'consignments',
        4 => 'quotations',
        5 => 'sites',
        6 => 'solar',
        7 => 'proposals',
        8 => 'payment-requests',
        9 => 'attendance',
        10 => 'booking',
        11 => 'tasks',
        12 => 'ai',
        13 => 'chat',
        14 => 'company',
      ),
      'featured' => 
      array (
        0 => 'sales-workspace',
        1 => 'customers',
        2 => 'orders',
      ),
      'default_category' => 'sales',
    ),
    'accounting' => 
    array (
      'label' => 'Kế toán',
      'icon' => 'bi-calculator-fill',
      'role_names' => 
      array (
        0 => 'accounting',
        1 => 'accountant',
      ),
      'department_codes' => 
      array (
        0 => 'accounting',
      ),
      'department_names' => 
      array (
        0 => 'Kế toán',
      ),
      'apps' => 
      array (
        0 => 'finance',
        1 => 'dashboard',
        2 => 'payment-requests',
        3 => 'proposals',
        4 => 'orders',
        5 => 'sites',
        6 => 'warehouses',
        7 => 'products',
        8 => 'attendance',
        9 => 'booking',
        10 => 'tasks',
        11 => 'ai',
        12 => 'chat',
        13 => 'company',
      ),
      'featured' => 
      array (
        0 => 'finance',
        1 => 'payment-requests',
        2 => 'proposals',
      ),
      'default_category' => 'finance',
    ),
    'warehouse' => 
    array (
      'label' => 'Kho',
      'icon' => 'bi-boxes',
      'role_names' => 
      array (
        0 => 'warehouse',
        1 => 'kho',
      ),
      'department_codes' => 
      array (
        0 => 'warehouse',
      ),
      'department_names' => 
      array (
        0 => 'Kế toán & Kho',
        1 => 'Kho',
      ),
      'apps' => 
      array (
        0 => 'warehouse-workspace',
        1 => 'warehouses',
        2 => 'products',
        3 => 'brands',
        4 => 'site-assembly',
        5 => 'orders',
        6 => 'consignments',
        7 => 'sites',
        8 => 'attendance',
        9 => 'booking',
        10 => 'tasks',
        11 => 'ai',
        12 => 'chat',
        13 => 'company',
      ),
      'featured' => 
      array (
        0 => 'warehouse-workspace',
        1 => 'warehouses',
        2 => 'products',
        3 => 'brands',
        4 => 'site-assembly',
      ),
      'default_category' => 'warehouse',
    ),
    'hr' => 
    array (
      'label' => 'Nhân sự',
      'icon' => 'bi-person-vcard-fill',
      'role_names' => 
      array (
        0 => 'hr',
        1 => 'hr_manager',
        2 => 'human_resources',
      ),
      'department_codes' => 
      array (
        0 => 'hr',
      ),
      'department_names' => 
      array (
        0 => 'Hành Chính - Nhân Sự',
        1 => 'Nhân sự',
      ),
      'apps' => 
      array (
        0 => 'hr',
        1 => 'hr-employees',
        2 => 'recruitment',
        3 => 'attendance',
        4 => 'hr-operations',
        5 => 'gifts',
        6 => 'booking',
        7 => 'tasks',
        8 => 'ai',
        9 => 'chat',
        10 => 'company',
        11 => 'proposals',
        12 => 'payment-requests',
      ),
      'featured' => 
      array (
        0 => 'hr',
        1 => 'hr-employees',
        2 => 'recruitment',
      ),
      'default_category' => 'hr',
    ),
    'marketing' => 
    array (
      'label' => 'Marketing',
      'icon' => 'bi-megaphone-fill',
      'role_names' => 
      array (
        0 => 'marketing_manager',
        1 => 'marketing',
      ),
      'department_codes' => 
      array (
        0 => 'marketing',
      ),
      'department_names' => 
      array (
        0 => 'Marketing',
      ),
      'apps' => 
      array (
        0 => 'marketing',
        1 => 'customers',
        2 => 'attendance',
        3 => 'booking',
        4 => 'tasks',
        5 => 'ai',
        6 => 'chat',
        7 => 'company',
        8 => 'proposals',
        9 => 'payment-requests',
      ),
      'featured' => 
      array (
        0 => 'marketing',
        1 => 'tasks',
        2 => 'ai',
      ),
      'default_category' => 'marketing',
    ),
    'general' => 
    array (
      'label' => 'Nhân viên',
      'icon' => 'bi-person-fill',
      'role_names' => 
      array (
      ),
      'department_codes' => 
      array (
      ),
      'department_names' => 
      array (
      ),
      'apps' => 
      array (
        0 => 'attendance',
        1 => 'booking',
        2 => 'tasks',
        3 => 'ai',
        4 => 'chat',
        5 => 'company',
      ),
      'featured' => 
      array (
        0 => 'tasks',
        1 => 'attendance',
        2 => 'chat',
      ),
      'default_category' => 'all',
    ),
  ),
  'profile_priority' => 
  array (
    0 => 'admin',
    1 => 'management',
    2 => 'technical',
    3 => 'sales',
    4 => 'accounting',
    5 => 'warehouse',
    6 => 'hr',
    7 => 'marketing',
    8 => 'general',
  ),
  'apps' => 
  array (
    0 => 
    array (
      'id' => 'dashboard',
      'name' => 'Dashboard Giám đốc',
      'description' => 'Theo dõi chỉ số và cảnh báo điều hành',
      'category' => 'operations',
      'icon' => 'bi-bar-chart-fill',
      'tone' => 'blue',
      'route' => 'dashboard',
      'fallback' => '/',
      'page_permission' => 'page.dashboard',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'dashboard',
        1 => 'giám đốc',
        2 => 'điều hành',
        3 => 'báo cáo',
      ),
    ),
    1 => 
    array (
      'id' => 'customers',
      'name' => 'Khách hàng',
      'description' => 'Quản lý khách hàng và lịch sử tương tác',
      'category' => 'sales',
      'icon' => 'bi-people-fill',
      'tone' => 'cyan',
      'route' => 'customers.index',
      'fallback' => '/customers',
      'page_permission' => 'page.customers',
      'keywords' => 
      array (
        0 => 'khách hàng',
        1 => 'customer',
        2 => 'crm',
        3 => 'hồ sơ',
      ),
    ),
    2 => 
    array (
      'id' => 'orders',
      'name' => 'Đơn hàng',
      'description' => 'Tạo, duyệt và theo dõi đơn hàng',
      'category' => 'sales',
      'icon' => 'bi-cart-check-fill',
      'tone' => 'green',
      'route' => 'orders.index',
      'fallback' => '/orders',
      'page_permission' => 'page.orders',
      'badge_key' => 'orders',
      'keywords' => 
      array (
        0 => 'đơn hàng',
        1 => 'order',
        2 => 'duyệt',
        3 => 'bán hàng',
      ),
    ),
    3 => 
    array (
      'id' => 'consignments',
      'name' => 'Ký gửi hàng hóa',
      'description' => 'Quản lý hàng giữ cho khách và xuất ký gửi',
      'category' => 'sales',
      'icon' => 'bi-box-seam-fill',
      'tone' => 'purple',
      'route' => 'customer-consignments.index',
      'fallback' => '/ky-gui-hang-hoa',
      'page_permission' => 'page.consignments',
      'badge_key' => 'consignments',
      'keywords' => 
      array (
        0 => 'ký gửi',
        1 => 'hàng hóa',
        2 => 'consignment',
        3 => 'giữ hàng',
      ),
    ),
    4 => 
    array (
      'id' => 'sites',
      'name' => 'Công trình',
      'description' => 'Theo dõi khảo sát, thi công và nghiệm thu',
      'category' => 'technical',
      'icon' => 'bi-buildings-fill',
      'tone' => 'indigo',
      'route' => '',
      'fallback' => '/cong-trinh',
      'page_permission' => 'page.sites',
      'keywords' => 
      array (
        0 => 'công trình',
        1 => 'site',
        2 => 'thi công',
        3 => 'khảo sát',
      ),
    ),
    5 => 
    array (
      'id' => 'warehouses',
      'name' => 'Kho hàng',
      'description' => 'Quản lý kho và tồn kho thực tế',
      'category' => 'warehouse',
      'icon' => 'bi-house-gear-fill',
      'tone' => 'orange',
      'route' => 'warehouses.index',
      'fallback' => '/warehouses',
      'page_permission' => 'page.warehouses',
      'keywords' => 
      array (
        0 => 'kho hàng',
        1 => 'warehouse',
        2 => 'tồn kho',
      ),
    ),
    6 => 
    array (
      'id' => 'products',
      'name' => 'Sản phẩm',
      'description' => 'Danh mục sản phẩm, serial và lịch sử kho',
      'category' => 'warehouse',
      'icon' => 'bi-box-fill',
      'tone' => 'royal',
      'route' => 'products.index',
      'fallback' => '/products',
      'page_permission' => 'page.products',
      'keywords' => 
      array (
        0 => 'sản phẩm',
        1 => 'product',
        2 => 'serial',
        3 => 'sku',
      ),
    ),
    7 => 
    array (
      'id' => 'brands',
      'name' => 'THƯƠNG HIỆU SẢN PHẨM',
      'description' => 'Tạo và quản lý thương hiệu dùng cho danh mục sản phẩm',
      'category' => 'warehouse',
      'icon' => 'bi-tags-fill',
      'tone' => 'indigo',
      'route' => 'brands.index',
      'fallback' => '/brands',
      'page_permission' => 'page.products',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'thương hiệu',
        1 => 'brand',
        2 => 'hãng',
        3 => 'nhãn hiệu',
        4 => 'sản phẩm',
      ),
    ),
    8 => 
    array (
      'id' => 'site-assembly',
      'name' => 'LẮP RÁP / SẢN XUẤT',
      'description' => 'Xuất vật tư, lắp ráp thành phẩm và cập nhật tồn kho',
      'category' => 'warehouse',
      'icon' => 'bi-tools',
      'tone' => 'orange',
      'route' => 'site-assemblies.index',
      'fallback' => '/cong-trinh/lap-rap-san-xuat',
      'page_permission' => 'page.sites',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'lắp ráp',
        1 => 'sản xuất',
        2 => 'vật tư',
        3 => 'thành phẩm',
        4 => 'assembly',
        5 => 'production',
      ),
    ),
    9 => 
    array (
      'id' => 'proposals',
      'name' => 'Đề xuất',
      'description' => 'Tạo và theo dõi đề xuất nội bộ',
      'category' => 'finance',
      'icon' => 'bi-lightbulb-fill',
      'tone' => 'amber',
      'route' => 'de-xuat.index',
      'fallback' => '/de-xuat',
      'page_permission' => 'page.proposals',
      'badge_key' => 'proposals',
      'keywords' => 
      array (
        0 => 'đề xuất',
        1 => 'proposal',
        2 => 'trình duyệt',
      ),
    ),
    10 => 
    array (
      'id' => 'payment-requests',
      'name' => 'Đề nghị thanh toán',
      'description' => 'Theo dõi và phê duyệt thanh toán',
      'category' => 'finance',
      'icon' => 'bi-file-earmark-check-fill',
      'tone' => 'teal',
      'route' => 'payment_requests.index',
      'fallback' => '/payment-requests',
      'page_permission' => 'page.payment_requests',
      'badge_key' => 'payment_requests',
      'keywords' => 
      array (
        0 => 'đề nghị thanh toán',
        1 => 'dntt',
        2 => 'payment request',
        3 => 'tài chính',
      ),
    ),
    11 => 
    array (
      'id' => 'hr',
      'name' => 'TỔNG QUAN NHÂN SỰ',
      'description' => 'Dashboard nhân sự, chấm công, nghỉ phép và tuyển dụng',
      'category' => 'hr',
      'icon' => 'bi-speedometer2',
      'tone' => 'cyan',
      'route' => 'hr.dashboard',
      'fallback' => '/nhan-su',
      'page_permission' => 'page.hr',
      'keywords' => 
      array (
        0 => 'dashboard',
        1 => 'tổng quan',
        2 => 'tổng quan nhân sự',
        3 => 'dashboard hr',
        4 => 'nhân sự',
        5 => 'hr',
      ),
    ),
    12 => 
    array (
      'id' => 'hr-employees',
      'name' => 'Nhân sự',
      'description' => 'Nhân viên, hồ sơ, phòng ban, chức vụ và sơ đồ tổ chức',
      'category' => 'hr',
      'icon' => 'bi-people-fill',
      'tone' => 'blue',
      'route' => 'hr.employees.index',
      'fallback' => '/nhan-su/employees',
      'page_permission' => 'page.hr',
      'keywords' => 
      array (
        0 => 'nhân viên',
        1 => 'hồ sơ nhân sự',
        2 => 'phòng ban',
        3 => 'chức vụ',
        4 => 'sơ đồ tổ chức',
      ),
    ),
    13 => 
    array (
      'id' => 'recruitment',
      'name' => 'Tuyển dụng',
      'description' => 'Quản lý yêu cầu tuyển và ứng viên',
      'category' => 'hr',
      'icon' => 'bi-person-plus-fill',
      'tone' => 'aqua',
      'route' => 'hr.recruitment.index',
      'fallback' => '/nhan-su/tuyen-dung',
      'page_permission' => 'page.hr',
      'badge_key' => 'recruitment',
      'keywords' => 
      array (
        0 => 'tuyển dụng',
        1 => 'ứng viên',
        2 => 'recruitment',
        3 => 'phỏng vấn',
      ),
    ),
    14 => 
    array (
      'id' => 'attendance',
      'name' => 'Chấm công',
      'description' => 'Chấm công và xem lịch sử cá nhân',
      'category' => 'hr',
      'icon' => 'bi-check-circle-fill',
      'tone' => 'lime',
      'route' => 'hr.attendance.my',
      'fallback' => '/nhan-su/cham-cong-cua-toi',
      'page_permission' => NULL,
      'keywords' => 
      array (
        0 => 'chấm công',
        1 => 'attendance',
        2 => 'check in',
        3 => 'check out',
      ),
    ),
    15 => 
    array (
      'id' => 'hr-operations',
      'name' => 'HÀNH CHÁNH',
      'description' => 'HC vận hành, tài sản, nhà cung cấp, văn phòng phẩm và chi phí',
      'category' => 'hr',
      'icon' => 'bi-briefcase-fill',
      'tone' => 'orange',
      'route' => 'hr.operations.index',
      'fallback' => '/nhan-su/hc-van-hanh',
      'page_permission' => 'page.hr',
      'keywords' => 
      array (
        0 => 'hành chính',
        1 => 'hc vận hành',
        2 => 'tài sản',
        3 => 'nhà cung cấp',
        4 => 'văn phòng phẩm',
        5 => 'chi phí văn phòng',
      ),
    ),
    16 => 
    array (
      'id' => 'gifts',
      'name' => 'Quà tặng',
      'description' => 'Kho quà, nhập quà, yêu cầu tặng và theo dõi giao quà',
      'category' => 'hr',
      'icon' => 'bi-gift-fill',
      'tone' => 'cyan',
      'route' => 'hr.gifts.index',
      'fallback' => '/nhan-su/qua-tang',
      'page_permission' => 'page.hr',
      'badge_key' => 'gifts',
      'keywords' => 
      array (
        0 => 'quà tặng',
        1 => 'kho quà',
        2 => 'nhập quà',
        3 => 'xuất quà',
        4 => 'yêu cầu tặng',
      ),
    ),
    17 => 
    array (
      'id' => 'finance',
      'name' => 'TỔNG QUAN TÀI CHÍNH',
      'description' => 'Dashboard doanh thu, thu chi, công nợ và lợi nhuận',
      'category' => 'finance',
      'icon' => 'bi-speedometer2',
      'tone' => 'royal',
      'route' => 'finance.index',
      'fallback' => '/finance',
      'page_permission' => 'page.finance',
      'keywords' => 
      array (
        0 => 'dashboard',
        1 => 'tổng quan',
        2 => 'tài chính',
        3 => 'kế toán',
        4 => 'công nợ',
        5 => 'thu chi',
        6 => 'báo cáo',
      ),
    ),
    18 => 
    array (
      'id' => 'serial-warranty',
      'name' => 'Serial & Bảo hành',
      'description' => 'Tra cứu serial, theo dõi hạn bảo hành và lịch sử thiết bị',
      'category' => 'warehouse',
      'icon' => 'bi-upc-scan',
      'tone' => 'teal',
      'route' => 'serial-warranty.index',
      'fallback' => '/serial-warranty',
      'page_permission' => 'page.technical',
      'keywords' => 
      array (
        0 => 'serial',
        1 => 'seri',
        2 => 'bảo hành',
        3 => 'warranty',
        4 => 'thiết bị',
      ),
    ),
    19 => 
    array (
      'id' => 'booking',
      'name' => 'Booking phòng họp',
      'description' => 'Đặt phòng và theo dõi lịch sử dụng',
      'category' => 'utilities',
      'icon' => 'bi-calendar2-week-fill',
      'tone' => 'coral',
      'route' => 'meeting-room-bookings.index',
      'fallback' => '/booking-phong-hop',
      'page_permission' => 'page.booking',
      'keywords' => 
      array (
        0 => 'booking',
        1 => 'phòng họp',
        2 => 'lịch họp',
      ),
    ),
    20 => 
    array (
      'id' => 'tasks',
      'name' => 'Công việc',
      'description' => 'Theo dõi công việc được giao và tiến độ',
      'category' => 'operations',
      'icon' => 'bi-briefcase-fill',
      'tone' => 'sky',
      'route' => 'tasks.index',
      'fallback' => '/chat/tasks',
      'page_permission' => 'page.tasks',
      'badge_key' => 'tasks',
      'keywords' => 
      array (
        0 => 'công việc',
        1 => 'task',
        2 => 'tiến độ',
        3 => 'giao việc',
      ),
    ),
    21 => 
    array (
      'id' => 'ai',
      'name' => 'EGO AI Copilot',
      'description' => 'Hỏi đáp AI theo phạm vi quyền của bạn',
      'category' => 'utilities',
      'icon' => 'bi-stars',
      'tone' => 'blue',
      'route' => 'ai.index',
      'fallback' => '/ai',
      'ability' => 'ai.use',
      'keywords' => 
      array (
        0 => 'ai',
        1 => 'copilot',
        2 => 'hỏi đáp',
        3 => 'trợ lý',
      ),
    ),
    22 => 
    array (
      'id' => 'marketing',
      'name' => 'TỔNG QUAN MARKETING',
      'description' => 'Dashboard chiến dịch, nội dung, lead và hiệu quả Marketing',
      'category' => 'marketing',
      'icon' => 'bi-speedometer2',
      'tone' => 'purple',
      'route' => 'marketing.dashboard',
      'fallback' => '/marketing/dashboard',
      'page_permission' => 'page.marketing',
      'keywords' => 
      array (
        0 => 'dashboard',
        1 => 'tổng quan',
        2 => 'marketing',
        3 => 'quảng cáo',
        4 => 'content',
        5 => 'leads',
      ),
    ),
    23 => 
    array (
      'id' => 'quotations',
      'name' => 'Báo giá',
      'description' => 'Tạo và quản lý báo giá bán hàng',
      'category' => 'sales',
      'icon' => 'bi-file-earmark-text-fill',
      'tone' => 'blue',
      'route' => 'sales-quotations.index',
      'fallback' => '/bao-gia',
      'page_permission' => 'page.orders',
      'keywords' => 
      array (
        0 => 'báo giá',
        1 => 'quotation',
        2 => 'quote',
        3 => 'sales',
      ),
    ),
    24 => 
    array (
      'id' => 'solar',
      'name' => 'Công cụ Solar',
      'description' => 'Tính toán cấu hình điện mặt trời',
      'category' => 'technical',
      'icon' => 'bi-sun-fill',
      'tone' => 'turquoise',
      'route' => 'solar.calculator',
      'fallback' => '/solar/calculator',
      'page_permission' => 'page.solar',
      'keywords' => 
      array (
        0 => 'solar',
        1 => 'điện mặt trời',
        2 => 'tính toán',
        3 => 'inverter',
        4 => 'pin',
      ),
    ),
    25 => 
    array (
      'id' => 'chat',
      'name' => 'Tin nhắn',
      'description' => 'Trao đổi nội bộ theo hội thoại',
      'category' => 'utilities',
      'icon' => 'bi-chat-dots-fill',
      'tone' => 'azure',
      'route' => 'chat.inbox',
      'fallback' => '/chat',
      'page_permission' => 'page.chat',
      'keywords' => 
      array (
        0 => 'tin nhắn',
        1 => 'chat',
        2 => 'trao đổi',
      ),
    ),
    26 => 
    array (
      'id' => 'company',
      'name' => 'Hồ sơ công ty',
      'description' => 'Tài liệu, biểu mẫu và hồ sơ pháp nhân',
      'category' => 'utilities',
      'icon' => 'bi-building-fill',
      'tone' => 'steel',
      'route' => 'company-documents.index',
      'fallback' => '/company-documents',
      'page_permission' => 'page.company',
      'keywords' => 
      array (
        0 => 'hồ sơ công ty',
        1 => 'company',
        2 => 'tài liệu',
        3 => 'pháp nhân',
      ),
    ),
    27 => 
    array (
      'id' => 'technical-projects',
      'name' => 'DỰ ÁN',
      'description' => 'Dự án và công trình kỹ thuật',
      'category' => 'technical',
      'icon' => 'bi-buildings-fill',
      'tone' => 'indigo',
      'route' => 'project-test.index',
      'fallback' => '/cong-trinh',
      'page_permission' => 'page.sites',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'dự án',
        1 => 'công trình',
        2 => 'kỹ thuật',
      ),
    ),
    28 => 
    array (
      'id' => 'sales-projects',
      'name' => 'CÔNG TRÌNH',
      'description' => 'Công trình của bộ phận Sale',
      'category' => 'sales',
      'icon' => 'bi-buildings-fill',
      'tone' => 'indigo',
      'route' => 'sales-projects.index',
      'fallback' => '/sales/cong-trinh',
      'page_permission' => 'page.sites',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'công trình',
        1 => 'sale',
      ),
    ),
    29 => 
    array (
      'id' => 'warehouse-projects',
      'name' => 'CÔNG TRÌNH',
      'description' => 'Công trình cần Kho phối hợp',
      'category' => 'warehouse',
      'icon' => 'bi-buildings-fill',
      'tone' => 'indigo',
      'route' => 'project-test.index',
      'fallback' => '/cong-trinh',
      'page_permission' => 'page.sites',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'công trình',
        1 => 'kho',
      ),
    ),
    30 => 
    array (
      'id' => 'technical-workspace',
      'name' => 'TỔNG QUAN KỸ THUẬT',
      'description' => 'Dashboard điều hành, công trình, kế hoạch và KPI kỹ thuật',
      'category' => 'technical',
      'icon' => 'bi-speedometer2',
      'tone' => 'cyan',
      'route' => 'technical-workspace.overview',
      'fallback' => '/ky-thuat',
      'page_permission' => 'page.technical',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'dashboard',
        1 => 'tổng quan',
        2 => 'kỹ thuật',
        3 => 'phân công',
        4 => 'kpi',
      ),
    ),
    31 => 
    array (
      'id' => 'recruitment-request',
      'name' => 'YÊU CẦU TUYỂN DỤNG',
      'description' => 'Yêu cầu tuyển dụng của phòng ban',
      'category' => 'hr',
      'icon' => 'bi-person-plus-fill',
      'tone' => 'cyan',
      'route' => 'hr.recruitment.requests',
      'fallback' => '/nhan-su/tuyen-dung/yeu-cau',
      'page_permission' => 'page.hr',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'tuyển dụng',
        1 => 'yêu cầu',
      ),
    ),
    32 => 
    array (
      'id' => 'document-handovers',
      'name' => 'GIAO NHẬN HỒ SƠ',
      'description' => 'Quy trình giao nhận hồ sơ',
      'category' => 'utilities',
      'icon' => 'bi-folder-symlink-fill',
      'tone' => 'royal',
      'route' => 'hr.document-handovers.index',
      'fallback' => '/nhan-su/quy-trinh-giao-nhan-ho-so',
      'page_permission' => 'page.hr',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'giao nhận',
        1 => 'hồ sơ',
      ),
    ),
    33 => 
    array (
      'id' => 'leave',
      'name' => 'ĐƠN NGHỈ PHÉP',
      'description' => 'Đăng ký và theo dõi nghỉ phép',
      'category' => 'hr',
      'icon' => 'bi-calendar-x-fill',
      'tone' => 'purple',
      'route' => 'hr.leave.index',
      'fallback' => '/nhan-su/leave-requests',
      'page_permission' => 'page.hr',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'nghỉ phép',
      ),
    ),
    34 => 
    array (
      'id' => 'leave-approve',
      'name' => 'DUYỆT ĐƠN NGHỈ PHÉP',
      'description' => 'Duyệt nghỉ phép của phòng Kỹ thuật',
      'category' => 'hr',
      'icon' => 'bi-calendar-check-fill',
      'tone' => 'purple',
      'route' => 'hr.leave.index',
      'fallback' => '/nhan-su/leave-requests',
      'page_permission' => 'page.hr',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'duyệt nghỉ phép',
      ),
    ),
    35 => 
    array (
      'id' => 'overtime',
      'name' => 'ĐĂNG KÝ TĂNG CA',
      'description' => 'Đăng ký và theo dõi tăng ca',
      'category' => 'hr',
      'icon' => 'bi-clock-history',
      'tone' => 'orange',
      'route' => 'hr.overtime.index',
      'fallback' => '/nhan-su/tang-ca',
      'page_permission' => 'page.hr',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'tăng ca',
      ),
    ),
    36 => 
    array (
      'id' => 'overtime-approve',
      'name' => 'DUYỆT ĐĂNG KÝ TĂNG CA',
      'description' => 'Duyệt đăng ký tăng ca của phòng Kỹ thuật',
      'category' => 'hr',
      'icon' => 'bi-clock-fill',
      'tone' => 'orange',
      'route' => 'hr.overtime.index',
      'fallback' => '/nhan-su/tang-ca',
      'page_permission' => 'page.hr',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'duyệt tăng ca',
      ),
    ),
    37 => 
    array (
      'id' => 'attendance-approve',
      'name' => 'CHẤM CÔNG',
      'description' => 'Theo dõi chấm công phòng Kỹ thuật',
      'category' => 'hr',
      'icon' => 'bi-calendar-check-fill',
      'tone' => 'green',
      'route' => 'hr.attendance.index',
      'fallback' => '/nhan-su/cham-cong',
      'page_permission' => 'page.hr',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'chấm công',
      ),
    ),
    38 => 
    array (
      'id' => 'payment-requests-approve',
      'name' => 'DUYỆT ĐỀ NGHỊ THANH TOÁN',
      'description' => 'Duyệt đề nghị thanh toán',
      'category' => 'finance',
      'icon' => 'bi-receipt-cutoff',
      'tone' => 'green',
      'route' => 'payment_requests.index',
      'fallback' => '/payment-requests',
      'page_permission' => 'page.payment_requests',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'duyệt',
        1 => 'đề nghị thanh toán',
      ),
    ),
    39 => 
    array (
      'id' => 'administration',
      'name' => 'HÀNH CHÁNH',
      'description' => 'Chi phí, tài sản, văn phòng phẩm, quà tặng và văn thư',
      'category' => 'hr',
      'icon' => 'bi-building-gear',
      'tone' => 'cyan',
      'route' => 'hr.operations.index',
      'fallback' => '/nhan-su/hc-van-hanh',
      'page_permission' => 'page.hr',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'hành chánh',
        1 => 'tài sản',
        2 => 'văn phòng phẩm',
      ),
    ),
    40 => 
    array (
      'id' => 'warehouse-workspace',
      'name' => 'TỔNG QUAN KHO',
      'description' => 'Dashboard hàng hóa, nhập xuất, tồn kho và serial',
      'category' => 'warehouse',
      'icon' => 'bi-speedometer2',
      'tone' => 'orange',
      'route' => 'products.index',
      'fallback' => '/products',
      'page_permission' => 'page.products',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'dashboard',
        1 => 'tổng quan',
        2 => 'kho',
        3 => 'hàng nhập',
        4 => 'hàng xuất',
        5 => 'serial',
      ),
    ),
    41 => 
    array (
      'id' => 'sales-workspace',
      'name' => 'TỔNG QUAN KINH DOANH',
      'description' => 'Dashboard doanh thu, đơn hàng, khách hàng và công việc Sales',
      'category' => 'sales',
      'icon' => 'bi-speedometer2',
      'tone' => 'cyan',
      'route' => 'dashboard',
      'fallback' => '/dashboard',
      'page_permission' => 'page.sales',
      'badge_key' => NULL,
      'keywords' => 
      array (
        0 => 'dashboard',
        1 => 'tổng quan',
        2 => 'sale',
        3 => 'kinh doanh',
      ),
    ),
  ),
);
