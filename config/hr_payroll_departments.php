<?php

$manage = ['admin', 'super_admin', 'director', 'ceo', 'management', 'hr', 'accounting'];

return [
    'hcns' => ['label' => 'HCNS', 'route' => 'hr.kpi.index', 'query' => ['template' => 'hcns'], 'roles' => $manage],
    'ke_toan_kho' => ['label' => 'Kế toán kho', 'route' => 'hr.kpi.index', 'query' => ['template' => 'ke_toan_kho'], 'roles' => $manage],
    'technical' => ['label' => 'Kỹ thuật', 'route' => 'ky-thuat.kpis.config', 'roles' => array_merge($manage, ['technical_manager', 'technical_leader', 'truong_phong_ky_thuat'])],
    'sales' => ['label' => 'Sales', 'route' => 'sales.kpi.settings', 'roles' => ['admin', 'sales_manager', 'accounting']],
    'marketing' => ['label' => 'Marketing', 'route' => 'marketing.kpi-payroll.settings', 'roles' => ['admin', 'marketing_manager']],
];
