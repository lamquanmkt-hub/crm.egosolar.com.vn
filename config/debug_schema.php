<?php
return [
    'cache_seconds' => 30,
    'groups' => [
        'product' => ['product', 'crm_product'],
        'inventory' => ['invent', 'stock', 'warehouse', 'kho'],
    ],
    // limit để tránh response quá nặng
    'max_columns' => 30,
];
