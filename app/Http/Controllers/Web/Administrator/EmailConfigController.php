<?php

namespace App\Http\Controllers\Web\Administrator;

use Illuminate\Routing\Controller;
use Inertia\Inertia;

class EmailConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:email-configs.index')->only('index');
    }

    public function index()
    {
        return Inertia::render('Administrator/EmailConfigs/Index');
    }
}
