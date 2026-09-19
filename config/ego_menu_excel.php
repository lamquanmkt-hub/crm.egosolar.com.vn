<?php

return array (
  'source' => 'PHÂN QUYỀN THEO CẤP BẬC(4).xlsx + Dashboard đầu App Center V3.2',
  'supported_workspaces' => 
  array (
    0 => 'technical',
    1 => 'accounting',
    2 => 'hr',
    3 => 'warehouse',
    4 => 'sales',
  ),
  'technical_head_roles' => 
  array (
    0 => 'admin',
    1 => 'management',
    2 => 'technical_manager',
    3 => 'technical_leader',
  ),
  'technical_head_positions' => 
  array (
    0 => 'giam doc',
    1 => 'pho giam doc',
    2 => 'manager',
    3 => 'truong phong',
    4 => 'truong nhom',
    5 => 'quan ly',
  ),
  'workspace_apps' => 
  array (
    'technical' => 
    array (
      'staff' => 
      array (
        0 => 'technical-workspace',
        1 => 'quotations',
        2 => 'solar',
        3 => 'chat',
        4 => 'sites',
        5 => 'proposals',
        6 => 'payment-requests',
        7 => 'tasks',
        8 => 'company',
        9 => 'attendance',
        10 => 'hr',
      ),
      'head' => 
      array (
        0 => 'technical-workspace',
        1 => 'quotations',
        2 => 'solar',
        3 => 'chat',
        4 => 'sites',
        5 => 'proposals',
        6 => 'payment-requests',
        7 => 'tasks',
        8 => 'company',
        9 => 'attendance',
        10 => 'hr',
        11 => 'recruitment',
      ),
    ),
    'accounting' => 
    array (
      'default' => 
      array (
        0 => 'finance',
        1 => 'payment-requests',
        2 => 'company',
        3 => 'hr',
        4 => 'attendance',
        5 => 'tasks',
        6 => 'proposals',
      ),
    ),
    'hr' => 
    array (
      'default' => 
      array (
        0 => 'hr',
        1 => 'payment-requests',
        2 => 'hr-operations',
        3 => 'hr-employees',
        4 => 'recruitment',
        5 => 'gifts',
        6 => 'company',
        7 => 'attendance',
        8 => 'tasks',
        9 => 'proposals',
      ),
    ),
    'warehouse' => 
    array (
      'default' => 
      array (
        0 => 'warehouse-workspace',
        1 => 'payment-requests',
        2 => 'orders',
        3 => 'consignments',
        4 => 'sites',
        5 => 'products',
        6 => 'warehouses',
        7 => 'serial-warranty',
        8 => 'company',
        9 => 'hr',
        10 => 'attendance',
        11 => 'tasks',
        12 => 'proposals',
      ),
    ),
    'sales' => 
    array (
      'default' => 
      array (
        0 => 'sales-workspace',
        1 => 'orders',
        2 => 'consignments',
        3 => 'sites',
        4 => 'payment-requests',
        5 => 'company',
        6 => 'hr',
        7 => 'attendance',
        8 => 'tasks',
        9 => 'proposals',
      ),
    ),
  ),
  'menus' => 
  array (
    'technical_head' => 
    array (
      0 => 
      array (
        'label' => 'TỔNG QUAN KỸ THUẬT',
        'icon' => 'bi-speedometer2',
        'route' => 'technical-workspace.overview',
        'fallback' => '/ky-thuat',
        'patterns' => 
        array (
          0 => 'technical-workspace.overview',
        ),
      ),
      1 => 
      array (
        'label' => 'BÁO GIÁ',
        'icon' => 'bi-file-earmark-text',
        'route' => 'sales-quotations.index',
        'fallback' => '/bao-gia',
        'patterns' => 
        array (
          0 => 'sales-quotations.*',
        ),
      ),
      2 => 
      array (
        'label' => 'CÔNG CỤ SOLAR',
        'icon' => 'bi-sun',
        'route' => 'solar.calculator',
        'fallback' => '/solar/calculator',
        'patterns' => 
        array (
          0 => 'solar.*',
        ),
      ),
      3 => 
      array (
        'label' => 'TIN NHẮN',
        'icon' => 'bi-chat-dots',
        'route' => 'chat.inbox',
        'fallback' => '/chat',
        'patterns' => 
        array (
          0 => 'chat.*',
        ),
      ),
      4 => 
      array (
        'label' => 'DỰ ÁN',
        'icon' => 'bi-buildings',
        'route' => 'project-test.index',
        'fallback' => '/cong-trinh',
        'patterns' => 
        array (
          0 => 'project-test.*',
          1 => 'technical-projects.*',
          2 => 'technical-workspace.projects.*',
        ),
      ),
      5 => 
      array (
        'label' => 'ĐỀ XUẤT',
        'icon' => 'bi-lightbulb',
        'route' => 'de-xuat.index',
        'fallback' => '/de-xuat',
        'patterns' => 
        array (
          0 => 'de-xuat.*',
        ),
      ),
      6 => 
      array (
        'label' => 'KỸ THUẬT:',
        'icon' => 'bi-tools',
        'children' => 
        array (
          0 => 
          array (
            'label' => 'PHÂN CÔNG CÔNG VIỆC',
            'icon' => 'bi-person-check',
            'route' => 'technical-workspace.coordination.assignments',
            'fallback' => '/ky-thuat/dieu-phoi/phan-cong-nhan-su',
            'patterns' => 
            array (
              0 => 'technical-workspace.coordination.assignments',
            ),
          ),
          1 => 
          array (
            'label' => 'GIAO VIỆC',
            'icon' => 'bi-send-check',
            'route' => 'tasks.create',
            'fallback' => '/chat/tasks/create',
            'patterns' => 
            array (
              0 => 'tasks.create',
            ),
          ),
          2 => 
          array (
            'label' => 'KẾ HOẠCH CÔNG VIỆC',
            'icon' => 'bi-calendar-week',
            'route' => 'technical-workspace.operations.weekly-plan',
            'fallback' => '/ky-thuat/dieu-hanh/ke-hoach-tuan',
            'patterns' => 
            array (
              0 => 'technical-workspace.operations.weekly-plan',
            ),
          ),
          3 => 
          array (
            'label' => 'DUYỆT KPI',
            'icon' => 'bi-check2-square',
            'route' => 'ky-thuat.luong.index',
            'fallback' => '/ky-thuat/luong',
            'patterns' => 
            array (
              0 => 'ky-thuat.luong.*',
            ),
          ),          4 => 
          array (
            'label' => 'BẢO HÀNH & O&M',
            'icon' => 'bi-calendar2-heart',
            'route' => 'ky-thuat.maintenance.index',
            'fallback' => '/ky-thuat/bao-tri-bao-hanh',
            'patterns' => 
            array (
              0 => 'ky-thuat.maintenance.*',
            ),
          ),
        ),
      ),
      7 => 
      array (
        'label' => 'DUYỆT ĐỀ NGHỊ THANH TOÁN',
        'icon' => 'bi-receipt',
        'route' => 'payment_requests.index',
        'fallback' => '/payment-requests',
        'patterns' => 
        array (
          0 => 'payment_requests.*',
        ),
      ),
      8 => 
      array (
        'label' => 'CÔNG VIỆC',
        'icon' => 'bi-briefcase',
        'route' => 'tasks.index',
        'fallback' => '/chat/tasks',
        'patterns' => 
        array (
          0 => 'tasks.*',
        ),
      ),
      9 => 
      array (
        'label' => 'HỒ SƠ CÔNG TY',
        'icon' => 'bi-building',
        'route' => 'company-documents.index',
        'fallback' => '/company-documents',
        'patterns' => 
        array (
          0 => 'company-documents.*',
        ),
      ),
      10 => 
      array (
        'label' => 'YÊU CẦU TUYỂN DỤNG',
        'icon' => 'bi-person-plus',
        'route' => 'hr.recruitment.requests',
        'fallback' => '/nhan-su/tuyen-dung/yeu-cau',
        'patterns' => 
        array (
          0 => 'hr.recruitment.requests*',
        ),
      ),
      11 => 
      array (
        'label' => 'GIAO NHẬN HỒ SƠ',
        'icon' => 'bi-folder-symlink',
        'route' => 'hr.document-handovers.index',
        'fallback' => '/nhan-su/quy-trinh-giao-nhan-ho-so',
        'patterns' => 
        array (
          0 => 'hr.document-handovers.*',
        ),
      ),
      12 => 
      array (
        'label' => 'CHẤM CÔNG',
        'icon' => 'bi-calendar-check',
        'route' => 'hr.attendance.index',
        'fallback' => '/nhan-su/cham-cong',
        'patterns' => 
        array (
          0 => 'hr.attendance.*',
        ),
      ),
      13 => 
      array (
        'label' => 'DUYỆT ĐƠN NGHỈ PHÉP',
        'icon' => 'bi-calendar-x',
        'route' => 'hr.leave.index',
        'fallback' => '/nhan-su/leave-requests',
        'patterns' => 
        array (
          0 => 'hr.leave.*',
        ),
      ),
      14 => 
      array (
        'label' => 'DUYỆT ĐĂNG KÝ TĂNG CA',
        'icon' => 'bi-clock-history',
        'route' => 'hr.overtime.index',
        'fallback' => '/nhan-su/tang-ca',
        'patterns' => 
        array (
          0 => 'hr.overtime.*',
        ),
      ),
    ),
    'technical_staff' => 
    array (
      0 => 
      array (
        'label' => 'TỔNG QUAN KỸ THUẬT',
        'icon' => 'bi-speedometer2',
        'route' => 'technical-workspace.overview',
        'fallback' => '/ky-thuat',
        'patterns' => 
        array (
          0 => 'technical-workspace.overview',
        ),
      ),
      1 => 
      array (
        'label' => 'BÁO GIÁ',
        'icon' => 'bi-file-earmark-text',
        'route' => 'sales-quotations.index',
        'fallback' => '/bao-gia',
        'patterns' => 
        array (
          0 => 'sales-quotations.*',
        ),
      ),
      2 => 
      array (
        'label' => 'CÔNG CỤ SOLAR',
        'icon' => 'bi-sun',
        'route' => 'solar.calculator',
        'fallback' => '/solar/calculator',
        'patterns' => 
        array (
          0 => 'solar.*',
        ),
      ),
      3 => 
      array (
        'label' => 'TIN NHẮN',
        'icon' => 'bi-chat-dots',
        'route' => 'chat.inbox',
        'fallback' => '/chat',
        'patterns' => 
        array (
          0 => 'chat.*',
        ),
      ),
      4 => 
      array (
        'label' => 'DỰ ÁN',
        'icon' => 'bi-buildings',
        'route' => 'project-test.index',
        'fallback' => '/cong-trinh',
        'patterns' => 
        array (
          0 => 'project-test.*',
          1 => 'technical-projects.*',
          2 => 'technical-workspace.projects.*',
        ),
      ),
      5 => 
      array (
        'label' => 'ĐỀ XUẤT',
        'icon' => 'bi-lightbulb',
        'route' => 'de-xuat.index',
        'fallback' => '/de-xuat',
        'patterns' => 
        array (
          0 => 'de-xuat.*',
        ),
      ),
      6 => 
      array (
        'label' => 'KỸ THUẬT',
        'icon' => 'bi-tools',
        'children' => 
        array (
          0 => 
          array (
            'label' => 'BÁO CÁO CÔNG VIỆC',
            'icon' => 'bi-clipboard-data',
            'route' => 'technical-workspace.report',
            'fallback' => '/ky-thuat/bao-cao',
            'patterns' => 
            array (
              0 => 'technical-workspace.report',
              1 => 'technical-workspace.reports.*',
            ),
          ),
          1 => 
          array (
            'label' => 'KIP CÁ NHÂN',
            'icon' => 'bi-bullseye',
            'route' => 'ky-thuat.luong.index',
            'fallback' => '/ky-thuat/luong',
            'patterns' => 
            array (
              0 => 'ky-thuat.luong.*',
            ),
          ),          2 => 
          array (
            'label' => 'BẢO HÀNH & O&M',
            'icon' => 'bi-calendar2-heart',
            'route' => 'ky-thuat.maintenance.index',
            'fallback' => '/ky-thuat/bao-tri-bao-hanh',
            'patterns' => 
            array (
              0 => 'ky-thuat.maintenance.*',
            ),
          ),
        ),
      ),
      7 => 
      array (
        'label' => 'ĐỀ NGHỊ THANH TOÁN',
        'icon' => 'bi-receipt',
        'route' => 'payment_requests.index',
        'fallback' => '/payment-requests',
        'patterns' => 
        array (
          0 => 'payment_requests.*',
        ),
      ),
      8 => 
      array (
        'label' => 'CÔNG VIỆC',
        'icon' => 'bi-briefcase',
        'route' => 'tasks.my',
        'fallback' => '/chat/tasks/my',
        'patterns' => 
        array (
          0 => 'tasks.*',
        ),
      ),
      9 => 
      array (
        'label' => 'HỒ SƠ CÔNG TY',
        'icon' => 'bi-building',
        'route' => 'company-documents.index',
        'fallback' => '/company-documents',
        'patterns' => 
        array (
          0 => 'company-documents.*',
        ),
      ),
      10 => 
      array (
        'label' => 'GIAO NHẬN HỒ SƠ',
        'icon' => 'bi-folder-symlink',
        'route' => 'hr.document-handovers.index',
        'fallback' => '/nhan-su/quy-trinh-giao-nhan-ho-so',
        'patterns' => 
        array (
          0 => 'hr.document-handovers.*',
        ),
      ),
      11 => 
      array (
        'label' => 'CHẤM CÔNG',
        'icon' => 'bi-calendar-check',
        'route' => 'hr.attendance.my',
        'fallback' => '/nhan-su/cham-cong-cua-toi',
        'patterns' => 
        array (
          0 => 'hr.attendance.*',
        ),
      ),
      12 => 
      array (
        'label' => 'ĐƠN NGHỈ PHÉP',
        'icon' => 'bi-calendar-x',
        'route' => 'hr.leave.index',
        'fallback' => '/nhan-su/leave-requests',
        'patterns' => 
        array (
          0 => 'hr.leave.*',
        ),
      ),
      13 => 
      array (
        'label' => 'ĐĂNG KÝ TĂNG CA',
        'icon' => 'bi-clock-history',
        'route' => 'hr.overtime.index',
        'fallback' => '/nhan-su/tang-ca',
        'patterns' => 
        array (
          0 => 'hr.overtime.*',
        ),
      ),
    ),
    'accounting' => 
    array (
      0 => 
      array (
        'label' => 'ĐỀ NGHỊ THANH TOÁN',
        'icon' => 'bi-receipt',
        'route' => 'payment_requests.index',
        'fallback' => '/payment-requests',
        'patterns' => 
        array (
          0 => 'payment_requests.*',
        ),
      ),
      1 => 
      array (
        'label' => 'KẾ TOÁN TÀI CHÍNH',
        'icon' => 'bi-calculator',
        'children' => 
        array (
          0 => 
          array (
            'label' => '1.  BẢNG TIỀN LƯƠNG',
            'icon' => 'bi-cash-stack',
            'route' => 'finance.salary',
            'fallback' => '/finance/salary',
            'patterns' => 
            array (
              0 => 'finance.salary*',
            ),
          ),
          1 => 
          array (
            'label' => '2. BÁO CÁO TÀI CHÍNH/THUẾ',
            'icon' => 'bi-file-earmark-bar-graph',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'BÁO CÁO QUÝ',
                'icon' => 'bi-calendar3',
                'route' => 'finance.reports',
                'fallback' => '/finance/reports?period=quarter',
                'query' => 
                array (
                  'period' => 'quarter',
                ),
                'patterns' => 
                array (
                  0 => 'finance.reports',
                ),
              ),
              1 => 
              array (
                'label' => 'BÁO CÁO NĂM',
                'icon' => 'bi-calendar4-range',
                'route' => 'finance.reports',
                'fallback' => '/finance/reports?period=year',
                'query' => 
                array (
                  'period' => 'year',
                ),
                'patterns' => 
                array (
                  0 => 'finance.reports',
                ),
              ),
              2 => 
              array (
                'label' => 'QUYẾT TOÁN',
                'icon' => 'bi-check2-circle',
                'route' => 'finance.reports',
                'fallback' => '/finance/reports?tab=settlement',
                'query' => 
                array (
                  'tab' => 'settlement',
                ),
                'patterns' => 
                array (
                  0 => 'finance.reports',
                ),
              ),
              3 => 
              array (
                'label' => 'KIỂM TOÁN',
                'icon' => 'bi-search',
                'route' => 'finance.reports',
                'fallback' => '/finance/reports?tab=audit',
                'query' => 
                array (
                  'tab' => 'audit',
                ),
                'patterns' => 
                array (
                  0 => 'finance.reports',
                ),
              ),
              4 => 
              array (
                'label' => 'BÁO CÁO TỒN QUỸ: QUỸ NGÂN HÀNG, QUỸ TIỀN MẶT',
                'icon' => 'bi-bank',
                'route' => 'finance.accounts.index',
                'fallback' => '/finance/accounts',
                'patterns' => 
                array (
                  0 => 'finance.accounts.*',
                ),
              ),
            ),
          ),
          2 => 
          array (
            'label' => '3. NỢ PHẢI THU',
            'icon' => 'bi-arrow-down-left-circle',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'PHẢI THU CÔNG TRÌNH',
                'icon' => 'bi-buildings',
                'route' => 'finance.customer-debts.index',
                'fallback' => '/finance/customer-debts?type=project',
                'query' => 
                array (
                  'type' => 'project',
                ),
                'patterns' => 
                array (
                  0 => 'finance.customer-debts.*',
                ),
              ),
              1 => 
              array (
                'label' => 'PHẢI THU ĐẠI LÝ',
                'icon' => 'bi-person-badge',
                'route' => 'finance.customer-debts.index',
                'fallback' => '/finance/customer-debts?type=dealer',
                'query' => 
                array (
                  'type' => 'dealer',
                ),
                'patterns' => 
                array (
                  0 => 'finance.customer-debts.*',
                ),
              ),
              2 => 
              array (
                'label' => 'PHẢI THU DỰ ÁN ĐẦU TƯ',
                'icon' => 'bi-graph-up',
                'route' => 'finance.customer-debts.index',
                'fallback' => '/finance/customer-debts?type=investment',
                'query' => 
                array (
                  'type' => 'investment',
                ),
                'patterns' => 
                array (
                  0 => 'finance.customer-debts.*',
                ),
              ),
            ),
          ),
          3 => 
          array (
            'label' => '4. NỢ PHẢI TRẢ',
            'icon' => 'bi-arrow-up-right-circle',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'PHẢI TRẢ NHÀ CUNG CẤP',
                'icon' => 'bi-truck',
                'route' => 'finance.supplier-debts.index',
                'fallback' => '/finance/supplier-debts?type=supplier',
                'query' => 
                array (
                  'type' => 'supplier',
                ),
                'patterns' => 
                array (
                  0 => 'finance.supplier-debts.*',
                ),
              ),
              1 => 
              array (
                'label' => 'PHẢI TRẢ HÀNG NHẬP KHẨU',
                'icon' => 'bi-box-arrow-in-down',
                'route' => 'finance.supplier-debts.index',
                'fallback' => '/finance/supplier-debts?type=import',
                'query' => 
                array (
                  'type' => 'import',
                ),
                'patterns' => 
                array (
                  0 => 'finance.supplier-debts.*',
                ),
              ),
              2 => 
              array (
                'label' => 'PHẢI TRẢ NGÂN HÀNG',
                'icon' => 'bi-bank2',
                'route' => 'finance.supplier-debts.index',
                'fallback' => '/finance/supplier-debts?type=bank',
                'query' => 
                array (
                  'type' => 'bank',
                ),
                'patterns' => 
                array (
                  0 => 'finance.supplier-debts.*',
                ),
              ),
              3 => 
              array (
                'label' => 'PHẢI TRẢ NỢ VAY ',
                'icon' => 'bi-currency-dollar',
                'route' => 'finance.supplier-debts.index',
                'fallback' => '/finance/supplier-debts?type=loan',
                'query' => 
                array (
                  'type' => 'loan',
                ),
                'patterns' => 
                array (
                  0 => 'finance.supplier-debts.*',
                ),
              ),
              4 => 
              array (
                'label' => 'PHẢI TRẢ CỔ ĐÔNG',
                'icon' => 'bi-people',
                'route' => 'finance.supplier-debts.index',
                'fallback' => '/finance/supplier-debts?type=shareholder',
                'query' => 
                array (
                  'type' => 'shareholder',
                ),
                'patterns' => 
                array (
                  0 => 'finance.supplier-debts.*',
                ),
              ),
            ),
          ),
        ),
      ),
      2 => 
      array (
        'label' => 'HỒ SƠ CÔNG TY',
        'icon' => 'bi-building',
        'route' => 'company-documents.index',
        'fallback' => '/company-documents',
        'patterns' => 
        array (
          0 => 'company-documents.*',
        ),
      ),
      3 => 
      array (
        'label' => 'GIAO NHẬN HỒ SƠ',
        'icon' => 'bi-folder-symlink',
        'route' => 'hr.document-handovers.index',
        'fallback' => '/nhan-su/quy-trinh-giao-nhan-ho-so',
        'patterns' => 
        array (
          0 => 'hr.document-handovers.*',
        ),
      ),
      4 => 
      array (
        'label' => 'ĐƠN NGHỈ PHÉP',
        'icon' => 'bi-calendar-x',
        'route' => 'hr.leave.index',
        'fallback' => '/nhan-su/leave-requests',
        'patterns' => 
        array (
          0 => 'hr.leave.*',
        ),
      ),
      5 => 
      array (
        'label' => 'CHẤM CÔNG',
        'icon' => 'bi-calendar-check',
        'route' => 'hr.attendance.my',
        'fallback' => '/nhan-su/cham-cong-cua-toi',
        'patterns' => 
        array (
          0 => 'hr.attendance.*',
        ),
      ),
      6 => 
      array (
        'label' => 'ĐĂNG KÝ TĂNG CA',
        'icon' => 'bi-clock-history',
        'route' => 'hr.overtime.index',
        'fallback' => '/nhan-su/tang-ca',
        'patterns' => 
        array (
          0 => 'hr.overtime.*',
        ),
      ),
      7 => 
      array (
        'label' => 'CÔNG VIỆC',
        'icon' => 'bi-briefcase',
        'route' => 'tasks.index',
        'fallback' => '/chat/tasks',
        'patterns' => 
        array (
          0 => 'tasks.*',
        ),
      ),
      8 => 
      array (
        'label' => 'ĐỀ XUẤT',
        'icon' => 'bi-lightbulb',
        'route' => 'de-xuat.index',
        'fallback' => '/de-xuat',
        'patterns' => 
        array (
          0 => 'de-xuat.*',
        ),
      ),
    ),
    'hr' => 
    array (
      0 => 
      array (
        'label' => 'ĐỀ NGHỊ THANH TOÁN',
        'icon' => 'bi-receipt',
        'route' => 'payment_requests.index',
        'fallback' => '/payment-requests',
        'patterns' => 
        array (
          0 => 'payment_requests.*',
        ),
      ),
      1 => 
      array (
        'label' => 'HÀNH CHÁNH',
        'icon' => 'bi-building-gear',
        'children' => 
        array (
          0 => 
          array (
            'label' => '1. CHI PHÍ',
            'icon' => 'bi-cash-coin',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'CHI PHÍ CỐ ĐỊNH',
                'icon' => 'bi-pin-angle',
                'route' => 'hr.office-expenses.index',
                'fallback' => '/nhan-su/chi-phi-vp?type=fixed',
                'query' => 
                array (
                  'type' => 'fixed',
                ),
                'patterns' => 
                array (
                  0 => 'hr.office-expenses.*',
                ),
              ),
              1 => 
              array (
                'label' => 'CHI PHÍ KHÔNG CỐ ĐỊNH',
                'icon' => 'bi-arrow-repeat',
                'route' => 'hr.office-expenses.index',
                'fallback' => '/nhan-su/chi-phi-vp?type=variable',
                'query' => 
                array (
                  'type' => 'variable',
                ),
                'patterns' => 
                array (
                  0 => 'hr.office-expenses.*',
                ),
              ),
            ),
          ),
          1 => 
          array (
            'label' => '2. TÀI SẢN',
            'icon' => 'bi-pc-display',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'TÀI SẢN CỐ ĐỊNH',
                'icon' => 'bi-building',
                'route' => 'finance.assets.index',
                'fallback' => '/finance/assets?type=fixed',
                'query' => 
                array (
                  'type' => 'fixed',
                ),
                'patterns' => 
                array (
                  0 => 'finance.assets.*',
                ),
              ),
              1 => 
              array (
                'label' => 'MÁY MÓC KỸ THUẬT',
                'icon' => 'bi-tools',
                'route' => 'hr.operations.index',
                'fallback' => '/nhan-su/hc-van-hanh?tab=assets&type=technical',
                'query' => 
                array (
                  'tab' => 'assets',
                  'type' => 'technical',
                ),
                'patterns' => 
                array (
                  0 => 'hr.operations.*',
                ),
              ),
            ),
          ),
          2 => 
          array (
            'label' => '3. VĂN PHÒNG PHẨM',
            'icon' => 'bi-box2',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'DS, NHẬP, XUẤT, TỒN',
                'icon' => 'bi-list-check',
                'route' => 'hr.office-supply-process.index',
                'fallback' => '/nhan-su/quy-trinh-phan-bo-vpp',
                'patterns' => 
                array (
                  0 => 'hr.office-supply-process.*',
                ),
              ),
            ),
          ),
          3 => 
          array (
            'label' => '4. QUÀ TẶNG',
            'icon' => 'bi-gift',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'DS, NHẬP, XUẤT, TỒN',
                'icon' => 'bi-list-check',
                'route' => 'hr.gifts.index',
                'fallback' => '/nhan-su/qua-tang',
                'patterns' => 
                array (
                  0 => 'hr.gifts.*',
                ),
              ),
            ),
          ),
          4 => 
          array (
            'label' => '5.NHÀ CUNG CẤP',
            'icon' => 'bi-truck',
            'route' => 'hr.operations.index',
            'fallback' => '/nhan-su/hc-van-hanh?tab=suppliers',
            'query' => 
            array (
              'tab' => 'suppliers',
            ),
            'patterns' => 
            array (
              0 => 'hr.operations.*',
            ),
          ),
          5 => 
          array (
            'label' => '6. VĂN THƯ LƯU TRỮ',
            'icon' => 'bi-archive',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'TÀU LIỆU NỘI BỘ',
                'icon' => 'bi-folder2-open',
                'route' => 'company-documents.index',
                'fallback' => '/company-documents?type=internal',
                'query' => 
                array (
                  'type' => 'internal',
                ),
                'patterns' => 
                array (
                  0 => 'company-documents.*',
                ),
              ),
              1 => 
              array (
                'label' => 'MẪU VĂN BẢN',
                'icon' => 'bi-file-earmark-text',
                'route' => 'company-documents.index',
                'fallback' => '/company-documents?type=template',
                'query' => 
                array (
                  'type' => 'template',
                ),
                'patterns' => 
                array (
                  0 => 'company-documents.*',
                ),
              ),
            ),
          ),
        ),
      ),
      2 => 
      array (
        'label' => 'NHÂN SỰ',
        'icon' => 'bi-person-workspace',
        'children' => 
        array (
          0 => 
          array (
            'label' => '1. DANH SÁCH NHÂN VIÊN',
            'description' => 'họ tên, chức vụ, phòng ban, số LH, ngày vào',
            'icon' => 'bi-people',
            'route' => 'hr.employees.index',
            'fallback' => '/nhan-su/employees',
            'patterns' => 
            array (
              0 => 'hr.employees.*',
            ),
          ),
          1 => 
          array (
            'label' => '2. HỒ SƠ NHÂN VIÊN',
            'icon' => 'bi-folder2-open',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'ĐƠN XIN VIỆC',
                'icon' => 'bi-file-person',
                'route' => 'hr.recruitment.candidates',
                'fallback' => '/nhan-su/tuyen-dung/kho-ung-vien',
                'patterns' => 
                array (
                  0 => 'hr.recruitment.candidates*',
                ),
              ),
              1 => 
              array (
                'label' => 'THƯ MỜI PV',
                'icon' => 'bi-envelope-paper',
                'route' => 'hr.recruitment.interviews',
                'fallback' => '/nhan-su/tuyen-dung/lich-phong-van',
                'patterns' => 
                array (
                  0 => 'hr.recruitment.interviews*',
                ),
              ),
              2 => 
              array (
                'label' => 'THƯ MỜI NHẬN VIỆC',
                'icon' => 'bi-envelope-check',
                'route' => 'hr.recruitment.offers',
                'fallback' => '/nhan-su/tuyen-dung/de-nghi-nhan-viec',
                'patterns' => 
                array (
                  0 => 'hr.recruitment.offers*',
                ),
              ),
              3 => 
              array (
                'label' => 'HĐ THỬ VIỆC',
                'icon' => 'bi-file-earmark-check',
                'route' => 'hr.records.index',
                'fallback' => '/nhan-su/ho-so-nhan-su?type=probation',
                'query' => 
                array (
                  'type' => 'probation',
                ),
                'patterns' => 
                array (
                  0 => 'hr.records.*',
                ),
              ),
              4 => 
              array (
                'label' => 'HĐ LAO ĐỘNG',
                'icon' => 'bi-file-earmark-lock',
                'route' => 'hr.records.index',
                'fallback' => '/nhan-su/ho-so-nhan-su?type=labor',
                'query' => 
                array (
                  'type' => 'labor',
                ),
                'patterns' => 
                array (
                  0 => 'hr.records.*',
                ),
              ),
              5 => 
              array (
                'label' => 'BẰNG CẤP',
                'icon' => 'bi-mortarboard',
                'route' => 'hr.records.index',
                'fallback' => '/nhan-su/ho-so-nhan-su?type=degree',
                'query' => 
                array (
                  'type' => 'degree',
                ),
                'patterns' => 
                array (
                  0 => 'hr.records.*',
                ),
              ),
            ),
          ),
          2 => 
          array (
            'label' => '3. CHẤM CÔNG',
            'icon' => 'bi-calendar-check',
            'route' => 'hr.attendance.index',
            'fallback' => '/nhan-su/cham-cong',
            'patterns' => 
            array (
              0 => 'hr.attendance.*',
            ),
          ),
          3 => 
          array (
            'label' => '4. TĂNG CA',
            'icon' => 'bi-clock-history',
            'route' => 'hr.overtime.index',
            'fallback' => '/nhan-su/tang-ca',
            'patterns' => 
            array (
              0 => 'hr.overtime.*',
            ),
          ),
          4 => 
          array (
            'label' => '5. ĐƠN NGHỈ PHÉP',
            'icon' => 'bi-calendar-x',
            'route' => 'hr.leave.index',
            'fallback' => '/nhan-su/leave-requests',
            'patterns' => 
            array (
              0 => 'hr.leave.*',
            ),
          ),
        ),
      ),
      3 => 
      array (
        'label' => 'HS CÔNG TY',
        'icon' => 'bi-building',
        'route' => 'company-documents.index',
        'fallback' => '/company-documents',
        'patterns' => 
        array (
          0 => 'company-documents.*',
        ),
      ),
      4 => 
      array (
        'label' => 'GIAO NHẬN HỒ SƠ',
        'icon' => 'bi-folder-symlink',
        'route' => 'hr.document-handovers.index',
        'fallback' => '/nhan-su/quy-trinh-giao-nhan-ho-so',
        'patterns' => 
        array (
          0 => 'hr.document-handovers.*',
        ),
      ),
      5 => 
      array (
        'label' => 'CÔNG VIỆC',
        'icon' => 'bi-briefcase',
        'route' => 'tasks.index',
        'fallback' => '/chat/tasks',
        'patterns' => 
        array (
          0 => 'tasks.*',
        ),
      ),
      6 => 
      array (
        'label' => 'ĐỀ XUẤT',
        'icon' => 'bi-lightbulb',
        'route' => 'de-xuat.index',
        'fallback' => '/de-xuat',
        'patterns' => 
        array (
          0 => 'de-xuat.*',
        ),
      ),
    ),
    'warehouse' => 
    array (
      0 => 
      array (
        'label' => 'ĐỀ NGHỊ THANH TOÁN',
        'icon' => 'bi-receipt',
        'route' => 'payment_requests.index',
        'fallback' => '/payment-requests',
        'patterns' => 
        array (
          0 => 'payment_requests.*',
        ),
      ),
      1 => 
      array (
        'label' => 'ĐƠN HÀNG',
        'icon' => 'bi-receipt-cutoff',
        'route' => 'orders.index',
        'fallback' => '/orders',
        'patterns' => 
        array (
          0 => 'orders.*',
        ),
      ),
      2 => 
      array (
        'label' => 'HÀNG KÝ GỬI',
        'icon' => 'bi-box-seam',
        'route' => 'customer-consignments.index',
        'fallback' => '/ky-gui-hang-hoa',
        'patterns' => 
        array (
          0 => 'customer-consignments.*',
        ),
      ),
      3 => 
      array (
        'label' => 'CÔNG TRÌNH',
        'icon' => 'bi-buildings',
        'route' => 'project-test.index',
        'fallback' => '/cong-trinh',
        'patterns' => 
        array (
          0 => 'project-test.*',
          1 => 'project-test.warehouse.*',
        ),
      ),
      4 => 
      array (
        'label' => 'KHO',
        'icon' => 'bi-boxes',
        'children' => 
        array (
          0 => 
          array (
            'label' => '1. DANH MỤC SẢN PHẨM',
            'icon' => 'bi-box',
            'route' => 'products.index',
            'fallback' => '/products',
            'patterns' => 
            array (
              0 => 'products.*',
            ),
          ),
          1 => 
          array (
            'label' => '2. DANH MỤC NHÀ CUNG CẤP',
            'icon' => 'bi-truck',
            'route' => 'product-goods-receipts.index',
            'fallback' => '/products/goods-receipts?tab=suppliers',
            'query' => 
            array (
              'tab' => 'suppliers',
            ),
            'patterns' => 
            array (
              0 => 'product-goods-receipts.*',
            ),
          ),
          2 => 
          array (
            'label' => '3. HÀNG NHẬP',
            'icon' => 'bi-box-arrow-in-down',
            'route' => 'product-goods-receipts.index',
            'fallback' => '/products/goods-receipts',
            'patterns' => 
            array (
              0 => 'product-goods-receipts.*',
              1 => 'products.input',
            ),
          ),
          3 => 
          array (
            'label' => '4. HÀNG XUẤT',
            'icon' => 'bi-box-arrow-up',
            'route' => 'products.output',
            'fallback' => '/products/output',
            'patterns' => 
            array (
              0 => 'products.output',
            ),
          ),
          4 => 
          array (
            'label' => '5. HÀNG TỒN',
            'icon' => 'bi-boxes',
            'route' => 'warehouses.index',
            'fallback' => '/warehouses',
            'patterns' => 
            array (
              0 => 'warehouses.*',
            ),
          ),
          5 => 
          array (
            'label' => '6. DANH MỤC SỐ SERIA',
            'icon' => 'bi-upc-scan',
            'children' => 
            array (
              0 => 
              array (
                'label' => 'SỐ SERIAL BIẾN TẦN - GOODWE',
                'icon' => 'bi-upc',
                'route' => 'serial-warranty.index',
                'fallback' => '/serial-warranty?brand=GOODWE',
                'query' => 
                array (
                  'brand' => 'GOODWE',
                ),
                'patterns' => 
                array (
                  0 => 'serial-warranty.*',
                ),
              ),
              1 => 
              array (
                'label' => 'SỐ SERIAL BIẾN TẦN - UNC',
                'icon' => 'bi-upc',
                'route' => 'serial-warranty.index',
                'fallback' => '/serial-warranty?brand=UNC',
                'query' => 
                array (
                  'brand' => 'UNC',
                ),
                'patterns' => 
                array (
                  0 => 'serial-warranty.*',
                ),
              ),
              2 => 
              array (
                'label' => 'SỐ SERIAL PIN LƯU TRỮ',
                'icon' => 'bi-battery-charging',
                'route' => 'serial-warranty.index',
                'fallback' => '/serial-warranty?type=storage',
                'query' => 
                array (
                  'type' => 'storage',
                ),
                'patterns' => 
                array (
                  0 => 'serial-warranty.*',
                ),
              ),
              3 => 
              array (
                'label' => 'SỐ SERIAL HÀNG HOÁ KHÁC',
                'icon' => 'bi-box-seam',
                'route' => 'serial-warranty.index',
                'fallback' => '/serial-warranty?type=other',
                'query' => 
                array (
                  'type' => 'other',
                ),
                'patterns' => 
                array (
                  0 => 'serial-warranty.*',
                ),
              ),
            ),
          ),
          6 => 
          array (
            'label' => '7. CHI PHÍ THUÊ KHO',
            'icon' => 'bi-cash-coin',
            'route' => 'payment_requests.index',
            'fallback' => '/payment-requests?category=warehouse-rent',
            'query' => 
            array (
              'category' => 'warehouse-rent',
            ),
            'patterns' => 
            array (
              0 => 'payment_requests.*',
            ),
          ),
        ),
      ),
      5 => 
      array (
        'label' => 'GIAO NHẬN HỒ SƠ',
        'icon' => 'bi-folder-symlink',
        'route' => 'hr.document-handovers.index',
        'fallback' => '/nhan-su/quy-trinh-giao-nhan-ho-so',
        'patterns' => 
        array (
          0 => 'hr.document-handovers.*',
        ),
      ),
      6 => 
      array (
        'label' => 'HỒ SƠ CÔNG TY',
        'icon' => 'bi-building',
        'route' => 'company-documents.index',
        'fallback' => '/company-documents',
        'patterns' => 
        array (
          0 => 'company-documents.*',
        ),
      ),
      7 => 
      array (
        'label' => 'ĐƠN NGHỈ PHÉP',
        'icon' => 'bi-calendar-x',
        'route' => 'hr.leave.index',
        'fallback' => '/nhan-su/leave-requests',
        'patterns' => 
        array (
          0 => 'hr.leave.*',
        ),
      ),
      8 => 
      array (
        'label' => 'CHẤM CÔNG',
        'icon' => 'bi-calendar-check',
        'route' => 'hr.attendance.my',
        'fallback' => '/nhan-su/cham-cong-cua-toi',
        'patterns' => 
        array (
          0 => 'hr.attendance.*',
        ),
      ),
      9 => 
      array (
        'label' => 'ĐĂNG KÝ TĂNG CA',
        'icon' => 'bi-clock-history',
        'route' => 'hr.overtime.index',
        'fallback' => '/nhan-su/tang-ca',
        'patterns' => 
        array (
          0 => 'hr.overtime.*',
        ),
      ),
      10 => 
      array (
        'label' => 'CÔNG VIỆC',
        'icon' => 'bi-briefcase',
        'route' => 'tasks.index',
        'fallback' => '/chat/tasks',
        'patterns' => 
        array (
          0 => 'tasks.*',
        ),
      ),
      11 => 
      array (
        'label' => 'ĐỀ XUẤT',
        'icon' => 'bi-lightbulb',
        'route' => 'de-xuat.index',
        'fallback' => '/de-xuat',
        'patterns' => 
        array (
          0 => 'de-xuat.*',
        ),
      ),
    ),
    'sales' => 
    array (
      0 => 
      array (
        'label' => 'SALE',
        'icon' => 'bi-graph-up-arrow',
        'route' => 'dashboard',
        'fallback' => '/dashboard',
        'patterns' => 
        array (
          0 => 'dashboard',
          1 => 'sales.*',
        ),
      ),
      1 => 
      array (
        'label' => 'ĐỀ NGHỊ THANH TOÁN',
        'icon' => 'bi-receipt',
        'route' => 'payment_requests.index',
        'fallback' => '/payment-requests',
        'patterns' => 
        array (
          0 => 'payment_requests.*',
        ),
      ),
      2 => 
      array (
        'label' => 'ĐƠN HÀNG',
        'icon' => 'bi-receipt-cutoff',
        'route' => 'orders.index',
        'fallback' => '/orders',
        'patterns' => 
        array (
          0 => 'orders.*',
        ),
      ),
      3 => 
      array (
        'label' => 'HÀNG KÝ GỬI',
        'icon' => 'bi-box-seam',
        'route' => 'customer-consignments.index',
        'fallback' => '/ky-gui-hang-hoa',
        'patterns' => 
        array (
          0 => 'customer-consignments.*',
        ),
      ),
      4 => 
      array (
        'label' => 'CÔNG TRÌNH',
        'icon' => 'bi-buildings',
        'route' => 'sales-projects.index',
        'fallback' => '/sales/cong-trinh',
        'patterns' => 
        array (
          0 => 'sales-projects.*',
          1 => 'project-test.*',
        ),
      ),
      5 => 
      array (
        'label' => 'GIAO NHẬN HỒ SƠ',
        'icon' => 'bi-folder-symlink',
        'route' => 'hr.document-handovers.index',
        'fallback' => '/nhan-su/quy-trinh-giao-nhan-ho-so',
        'patterns' => 
        array (
          0 => 'hr.document-handovers.*',
        ),
      ),
      6 => 
      array (
        'label' => 'HỒ SƠ CÔNG TY',
        'icon' => 'bi-building',
        'route' => 'company-documents.index',
        'fallback' => '/company-documents',
        'patterns' => 
        array (
          0 => 'company-documents.*',
        ),
      ),
      7 => 
      array (
        'label' => 'ĐƠN NGHỈ PHÉP',
        'icon' => 'bi-calendar-x',
        'route' => 'hr.leave.index',
        'fallback' => '/nhan-su/leave-requests',
        'patterns' => 
        array (
          0 => 'hr.leave.*',
        ),
      ),
      8 => 
      array (
        'label' => 'CHẤM CÔNG',
        'icon' => 'bi-calendar-check',
        'route' => 'hr.attendance.my',
        'fallback' => '/nhan-su/cham-cong-cua-toi',
        'patterns' => 
        array (
          0 => 'hr.attendance.*',
        ),
      ),
      9 => 
      array (
        'label' => 'ĐĂNG KÝ TĂNG CA',
        'icon' => 'bi-clock-history',
        'route' => 'hr.overtime.index',
        'fallback' => '/nhan-su/tang-ca',
        'patterns' => 
        array (
          0 => 'hr.overtime.*',
        ),
      ),
      10 => 
      array (
        'label' => 'CÔNG VIỆC',
        'icon' => 'bi-briefcase',
        'route' => 'tasks.index',
        'fallback' => '/chat/tasks',
        'patterns' => 
        array (
          0 => 'tasks.*',
        ),
      ),
      11 => 
      array (
        'label' => 'ĐỀ XUẤT',
        'icon' => 'bi-lightbulb',
        'route' => 'de-xuat.index',
        'fallback' => '/de-xuat',
        'patterns' => 
        array (
          0 => 'de-xuat.*',
        ),
      ),
    ),
  ),
);
