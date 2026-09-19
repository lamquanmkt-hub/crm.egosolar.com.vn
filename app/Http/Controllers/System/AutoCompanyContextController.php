<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class AutoCompanyContextController extends Controller
{
    public function __invoke(Request $request)
    {
        return $this->activate($request);
    }

    public function store(Request $request)
    {
        return $this->activate($request);
    }

    private function activate(Request $request)
    {
        EgoCompanyLock::apply($request);

        Cookie::queue(Cookie::make(
            name: 'ego_last_company_id',
            value: (string) EgoCompanyLock::id(),
            minutes: 60 * 24 * 365,
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));

        return redirect('/');
    }
}
