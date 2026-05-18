<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class GuestListPaymentController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function MyFunction(Request $request) {
        return "MyFunction";
    }
}
