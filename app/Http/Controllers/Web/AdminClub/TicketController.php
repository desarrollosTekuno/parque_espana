<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:tickets.index')->only('index');
    }

    public function index()
    {
        return Inertia::render('AdminClubs/Tickets/Index');
    }
}
