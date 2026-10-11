<?php

$manage = ['admin', 'super_admin', 'director', 'ceo', 'management', 'hr', 'accounting'];

return [
    'label' => 'Bảng lương',
    'icon' => 'bi-cash-stack',
    'roles' => $manage,
    'children' => [
        ['label' => 'Bảng lương tháng', 'route' => 'hr.payroll.index', 'patterns' => ['hr.payroll.index', 'hr.payroll.generate', 'hr.payroll.lines.*', 'hr.payroll.approve', 'hr.payroll.reopen', 'hr.payroll.export', 'hr.payroll.my', 'hr.payroll.payslip'], 'roles' => $manage],
        ['label' => 'KPI phòng ban', 'route' => 'hr.kpi.index', 'patterns' => ['hr.kpi.*', 'ky-thuat.kpis.config*', 'sales.kpi.settings*', 'marketing.kpi-payroll.settings*'], 'roles' => $manage],
        ['label' => 'Cài đặt lương', 'route' => 'hr.payroll.settings', 'patterns' => ['hr.payroll.settings*', 'hr.payroll.profiles*'], 'roles' => $manage],
    ],
];
