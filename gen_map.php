<?php
$routes = json_decode(file_get_contents('route_map.json'), true);
$out = "## 2. B?n d? Route ? View ? Layout (Trích xu?t)\n\n| Route | Method | Action (Controller@Method) | View Kh? nang G?i |\n|---|---|---|---|\n";
$count = 0;
foreach ($routes as $r) {
    if ($count > 50) {
        $out .= "| ... | ... | ... (Xem thêm log route_list) | ... |\n";
        break;
    }
    $methods = implode(', ', $r['methods']);
    $action = $r['action'];
    if (is_string($action)) {
        $actionStr = explode('\\', $action);
        $actionStr = end($actionStr);
        $out .= "| {['uri']} | $methods | $actionStr | (Xác d?nh d?ng) |\n";
        $count++;
    }
}
file_put_contents('route_view_map.md', $out);
