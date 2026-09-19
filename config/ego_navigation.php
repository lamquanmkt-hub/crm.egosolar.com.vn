<?php

return array (
  'version' => '3.2.0-dashboard-first',
  'levels' => 
  array (
    'director' => 
    array (
      'label' => 'Ban Giám đốc',
      'rank' => 400,
      'scope' => 'company',
      'icon' => 'bi-person-badge-fill',
    ),
    'department_head' => 
    array (
      'label' => 'Trưởng phòng / Quản lý',
      'rank' => 300,
      'scope' => 'department',
      'icon' => 'bi-person-workspace',
    ),
    'team_lead' => 
    array (
      'label' => 'Trưởng nhóm',
      'rank' => 200,
      'scope' => 'team',
      'icon' => 'bi-people-fill',
    ),
    'staff' => 
    array (
      'label' => 'Nhân viên',
      'rank' => 100,
      'scope' => 'own',
      'icon' => 'bi-person-fill',
    ),
  ),
  'role_levels' => 
  array (
    'admin' => 'director',
    'management' => 'director',
    'technical_manager' => 'department_head',
    'technical_leader' => 'team_lead',
    'sales_manager' => 'department_head',
    'marketing_manager' => 'department_head',
  ),
  'position_rules' => 
  array (
    'director' => 
    array (
      0 => 'giam_doc',
      1 => 'pho_giam_doc',
      2 => 'director',
      3 => 'ceo',
    ),
    'department_head' => 
    array (
      0 => 'truong_phong',
      1 => 'ke_toan_truong',
      2 => 'manager',
      3 => 'quan_ly',
      4 => 'head',
    ),
    'team_lead' => 
    array (
      0 => 'truong_nhom',
      1 => 'team_lead',
      2 => 'leader',
    ),
    'staff' => 
    array (
      0 => 'nhan_vien',
      1 => 'staff',
      2 => 'hoc_viec',
      3 => 'thu_viec',
      4 => 'thuc_tap',
      5 => 'ke_toan_kho',
      6 => 'hanh_chinh_nhan_su',
      7 => 'nhan_vien_sales',
    ),
  ),
  'role_workspaces' => 
  array (
    'admin' => 'admin',
    'management' => 'management',
    'technical_manager' => 'technical',
    'technical_leader' => 'technical',
    'ky_thuat' => 'technical',
    'sales_manager' => 'sales',
    'sales' => 'sales',
    'accounting' => 'accounting',
    'warehouse' => 'warehouse',
    'kho' => 'warehouse',
    'hr' => 'hr',
    'marketing_manager' => 'marketing',
    'marketing' => 'marketing',
    'assistant' => 'general',
  ),
  'shared_apps' => 
  array (
  ),
  'shared_menu_permissions' => 
  array (
  ),
  'workspaces' => 
  array (
    'admin' => 
    array (
      'label' => 'Quản trị viên',
      'apps' => 
      array (
        0 => '*',
      ),
      'menu_permissions' => 
      array (
        0 => '*',
      ),
    ),
    'management' => 
    array (
      'label' => 'Ban Giám đốc',
      'apps' => 
      array (
        0 => '*',
      ),
      'menu_permissions' => 
      array (
        0 => '*',
      ),
    ),
    'technical' => 
    array (
      'label' => 'Kỹ thuật',
      'apps' => 
      array (
        0 => 'technical-workspace',
        1 => 'quotations',
        2 => 'solar',
        3 => 'chat',
        4 => 'technical-projects',
        5 => 'proposals',
        6 => 'payment-requests',
        7 => 'tasks',
        8 => 'company',
        9 => 'document-handovers',
        10 => 'attendance',
        11 => 'leave',
        12 => 'overtime',
      ),
      'featured' => 
      array (
        0 => 'technical-workspace',
        1 => 'technical-projects',
        2 => 'quotations',
      ),
      'menu_permissions' => 
      array (
        0 => 'menu.orders',
        1 => 'menu.technical',
        2 => 'menu.proposals',
        3 => 'menu.payment_requests',
        4 => 'menu.tasks',
        5 => 'menu.company',
      ),
      'level_rules' => 
      array (
        'director' => 
        array (
          'add_apps' => 
          array (
            0 => 'recruitment-request',
            1 => 'payment-requests-approve',
            2 => 'attendance-approve',
            3 => 'leave-approve',
            4 => 'overtime-approve',
          ),
        ),
        'department_head' => 
        array (
          'add_apps' => 
          array (
            0 => 'recruitment-request',
            1 => 'payment-requests-approve',
            2 => 'attendance-approve',
            3 => 'leave-approve',
            4 => 'overtime-approve',
          ),
        ),
        'team_lead' => 
        array (
          'add_apps' => 
          array (
            0 => 'recruitment-request',
            1 => 'payment-requests-approve',
            2 => 'attendance-approve',
            3 => 'leave-approve',
            4 => 'overtime-approve',
          ),
        ),
        'staff' => 
        array (
        ),
      ),
    ),
    'sales' => 
    array (
      'label' => 'Kinh doanh',
      'apps' => 
      array (
        0 => 'sales-workspace',
        1 => 'payment-requests',
        2 => 'orders',
        3 => 'consignments',
        4 => 'sales-projects',
        5 => 'document-handovers',
        6 => 'company',
        7 => 'leave',
        8 => 'attendance',
        9 => 'overtime',
        10 => 'tasks',
        11 => 'proposals',
      ),
      'featured' => 
      array (
        0 => 'sales-workspace',
        1 => 'orders',
        2 => 'sales-projects',
      ),
      'menu_permissions' => 
      array (
        0 => 'menu.sales',
        1 => 'menu.orders',
        2 => 'menu.consignments',
        3 => 'menu.sites',
        4 => 'menu.payment_requests',
        5 => 'menu.tasks',
        6 => 'menu.company',
        7 => 'menu.proposals',
      ),
      'level_rules' => 
      array (
      ),
    ),
    'accounting' => 
    array (
      'label' => 'Kế toán',
      'apps' => 
      array (
        0 => 'finance',
        1 => 'payment-requests',
        2 => 'company',
        3 => 'document-handovers',
        4 => 'leave',
        5 => 'attendance',
        6 => 'overtime',
        7 => 'tasks',
        8 => 'proposals',
      ),
      'featured' => 
      array (
        0 => 'finance',
        1 => 'payment-requests',
        2 => 'company',
      ),
      'menu_permissions' => 
      array (
        0 => 'menu.finance',
        1 => 'menu.payment_requests',
        2 => 'menu.tasks',
        3 => 'menu.company',
        4 => 'menu.proposals',
      ),
      'level_rules' => 
      array (
      ),
    ),
    'warehouse' => 
    array (
      'label' => 'Kho',
      'apps' => 
      array (
        0 => 'warehouse-workspace',
        1 => 'site-assembly',
        2 => 'brands',
        3 => 'payment-requests',
        4 => 'orders',
        5 => 'consignments',
        6 => 'warehouse-projects',
        7 => 'document-handovers',
        8 => 'company',
        9 => 'leave',
        10 => 'attendance',
        11 => 'overtime',
        12 => 'tasks',
        13 => 'proposals',
      ),
      'featured' => 
      array (
        0 => 'warehouse-workspace',
        1 => 'brands',
        2 => 'orders',
        3 => 'warehouse-projects',
      ),
      'menu_permissions' => 
      array (
        0 => 'menu.products',
        1 => 'menu.orders',
        2 => 'menu.consignments',
        3 => 'menu.sites',
        4 => 'menu.payment_requests',
        5 => 'menu.tasks',
        6 => 'menu.company',
        7 => 'menu.proposals',
      ),
      'level_rules' => 
      array (
      ),
    ),
    'hr' => 
    array (
      'label' => 'Nhân sự',
      'apps' => 
      array (
        0 => 'hr',
        1 => 'payment-requests',
        2 => 'administration',
        3 => 'company',
        4 => 'document-handovers',
        5 => 'leave',
        6 => 'attendance',
        7 => 'overtime',
        8 => 'tasks',
        9 => 'proposals',
      ),
      'featured' => 
      array (
        0 => 'hr',
        1 => 'administration',
        2 => 'payment-requests',
      ),
      'menu_permissions' => 
      array (
        0 => 'menu.hr',
        1 => 'menu.payment_requests',
        2 => 'menu.tasks',
        3 => 'menu.company',
        4 => 'menu.proposals',
      ),
      'level_rules' => 
      array (
      ),
    ),
    'marketing' => 
    array (
      'label' => 'Marketing',
      'apps' => 
      array (
        0 => 'marketing',
        1 => 'customers',
        2 => 'proposals',
        3 => 'payment-requests',
        4 => 'attendance',
        5 => 'tasks',
        6 => 'ai',
        7 => 'chat',
        8 => 'company',
      ),
      'featured' => 
      array (
        0 => 'marketing',
        1 => 'tasks',
        2 => 'ai',
      ),
      'menu_permissions' => 
      array (
        0 => 'menu.marketing',
        1 => 'menu.customers',
        2 => 'menu.proposals',
        3 => 'menu.payment_requests',
        4 => 'menu.tasks',
        5 => 'menu.company',
      ),
      'level_rules' => 
      array (
      ),
    ),
    'general' => 
    array (
      'label' => 'Nhân viên',
      'apps' => 
      array (
        0 => 'attendance',
        1 => 'tasks',
        2 => 'chat',
        3 => 'company',
      ),
      'featured' => 
      array (
        0 => 'tasks',
        1 => 'attendance',
        2 => 'chat',
      ),
      'menu_permissions' => 
      array (
        0 => 'menu.tasks',
        1 => 'menu.company',
      ),
      'level_rules' => 
      array (
      ),
    ),
  ),
);
