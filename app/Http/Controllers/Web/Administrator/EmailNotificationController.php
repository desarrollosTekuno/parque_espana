<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class EmailNotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:email-notifications.index')->only('index');
    }

    public function index()
    {
        return Inertia::render('Administrator/EmailNotifications/Index');
    }
}
