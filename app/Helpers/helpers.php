<?php

if (! function_exists('active_route')) {
    function active_route(string|array $pattern): string
    {
        return request()->routeIs($pattern) ? 'active' : '';
    }
}
