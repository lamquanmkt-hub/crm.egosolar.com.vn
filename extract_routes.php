<?php
$routes = Route::getRoutes();
$map = [];
foreach ($routes as $route) {
    $action = $route->getAction();
    if (isset($action['controller'])) {
        $map[] = [
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            'action' => $action['controller']
        ];
    }
}
echo json_encode($map, JSON_PRETTY_PRINT);
