<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;

class EgoCompanyContextController extends Controller
{
    public function select(Request $request)
    {
        return $this->activate($request);
    }

    public function store(Request $request)
    {
        return $this->activate($request);
    }

    public function reset(Request $request)
    {
        return $this->activate($request);
    }

    private function activate(Request $request)
    {
        EgoCompanyLock::apply($request);

        return redirect('/');
    }
}
