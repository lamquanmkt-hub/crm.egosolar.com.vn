<?php
$tables = [
    'crm_product_catalog',
    'crm_serial_units',
    'crm_serial_unit_states',
    'crm_serial_warranties',
    'crm_product_stock',
    'crm_product_stock_lots'
];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    print_r(\Illuminate\Support\Facades\Schema::getColumnListing($table));
}
